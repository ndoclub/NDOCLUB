<?php
namespace App\Services;

use App\Core\DB;
use App\Core\Upload;
use DateTimeImmutable;

/**
 * Financeiro do Gabriel: pagamentos (vendas/renovações) e parcelas (recebíveis).
 *
 * Conceitos (os mesmos que a equipe usa na planilha):
 * - FATURAMENTO do mês = soma do valor TOTAL das vendas feitas no mês (por moeda).
 * - RECEITA = o que entra na conta no mês: parcelas que caíram (líquido, depois da taxa do cartão).
 * - Cartão (em R$): a parcela cai SOZINHA no vencimento (se for fim de semana/feriado, no próximo dia útil).
 *   O sistema marca como recebida sozinho (syncCards). O valor líquido = parcela menos a taxa da bandeira.
 * - Pix / Wise / dinheiro / outro: a equipe cobra e confere. Clica em "Recebi" (pode anexar o comprovante).
 * - Parcela cancelada (estorno, paciente que parou) não entra em nenhuma soma.
 * - USD/EUR: o total em reais usa o valor que CREDITOU na conta (received_brl), que já é o líquido.
 *
 * IMPORTANTE: tudo aqui é só do painel admin. O paciente nunca vê valores.
 */
final class Payments
{
    // ---------------------------------------------------------------
    // Formas de pagamento, bandeiras e faixas de taxa
    // ---------------------------------------------------------------

    /** Formas de pagamento: chave => nome. */
    public static function methods(): array
    {
        return [
            'cartao'   => 'Cartão de crédito',
            'pix'      => 'Pix',
            'wise'     => 'Wise (exterior)',
            'dinheiro' => 'Dinheiro',
            'outro'    => 'Outro',
        ];
    }

    public static function methodLabel(?string $m): string
    {
        return self::methods()[$m ?? ''] ?? 'Outro';
    }

    /** Texto curto para a lista: "Cartão · 12x", "Pix parcelado · 6x", "Pix à vista". */
    public static function methodText(?string $m, int $n): string
    {
        if ($m === 'pix') {
            return $n > 1 ? 'Pix parcelado · ' . $n . 'x' : 'Pix à vista';
        }
        $label = $m === 'cartao' ? 'Cartão' : self::methodLabel($m);
        return $label . ' · ' . ($n > 1 ? $n . 'x' : 'à vista');
    }

    public static function methodOrDefault(?string $m): string
    {
        $m = trim((string) $m);
        return isset(self::methods()[$m]) ? $m : 'outro';
    }

    /** Parcela que cai sozinha: cartão em reais. (Cartão em dólar/euro é conferido à mão: precisa do R$.) */
    public static function isAuto(?string $method, ?string $currency): bool
    {
        return $method === 'cartao' && ($currency ?? 'BRL') === 'BRL';
    }

    /** Bandeiras do cartão: chave => nome. */
    public static function brands(): array
    {
        return [
            'visa'       => 'Visa',
            'mastercard' => 'Mastercard',
            'elo'        => 'Elo',
            'amex'       => 'American Express',
            'hipercard'  => 'Hipercard',
            'outra'      => 'Outra bandeira',
        ];
    }

    public static function brandLabel(?string $b): string
    {
        return self::brands()[$b ?? ''] ?? '';
    }

    /** Faixas de parcelas da taxa: 1 = à vista, 2 = 2 a 6x, 3 = 7 a 12x. */
    public static function bands(): array
    {
        return [1 => 'À vista (1x)', 2 => '2 a 6x', 3 => '7 a 12x'];
    }

    public static function bandFor(int $installments): int
    {
        return $installments <= 1 ? 1 : ($installments <= 6 ? 2 : 3);
    }

    /** Tabela de taxas salva: [bandeira => [faixa => %]]. */
    public static function feeTable(): array
    {
        $out = [];
        foreach (DB::all('SELECT brand, band, pct FROM payment_fees') as $r) {
            if (isset(self::brands()[$r['brand']]) && isset(self::bands()[(int) $r['band']])) {
                $out[$r['brand']][(int) $r['band']] = (float) $r['pct'];
            }
        }
        return $out;
    }

    /** Taxa (%) cadastrada para a bandeira e o número de parcelas (0 se não houver). */
    public static function feeFor(?string $brand, int $installments): float
    {
        if (!$brand || !isset(self::brands()[$brand])) {
            return 0.0;
        }
        $v = DB::val('SELECT pct FROM payment_fees WHERE brand = ? AND band = ?', [$brand, self::bandFor($installments)]);
        return $v === null ? 0.0 : (float) $v;
    }

    /** Lê uma porcentagem digitada ("3,5", "3.5", "3,5%"). Vazio = null; inválido (ou fora de 0 a 30) = false. */
    public static function parsePct(?string $s): float|false|null
    {
        $s = trim(str_replace(['%', ' '], '', (string) $s));
        if ($s === '') {
            return null;
        }
        $s = str_replace(',', '.', $s);
        if (!is_numeric($s) || (float) $s < 0 || (float) $s > 30) {
            return false;
        }
        return round((float) $s, 2);
    }

    /**
     * Salva a tabela de taxas. $input = ['visa' => [1 => '2,5', 2 => '', 3 => '4'], ...].
     * Campo vazio = sem taxa. Retorna null se deu certo, ou o texto do erro.
     */
    public static function saveFees(array $input): ?string
    {
        $rows = [];
        foreach (self::brands() as $brand => $label) {
            foreach (self::bands() as $band => $bandLabel) {
                $raw = $input[$brand][$band] ?? '';
                $pct = self::parsePct(is_string($raw) ? $raw : '');
                if ($pct === false) {
                    return 'Taxa inválida em ' . $label . ' (' . $bandLabel . '). Use um número de 0 a 30, por exemplo 2,5.';
                }
                if ($pct !== null) {
                    $rows[] = [$brand, $band, $pct];
                }
            }
        }
        DB::tx(function () use ($rows) {
            DB::run('DELETE FROM payment_fees');
            foreach ($rows as $r) {
                DB::run('INSERT INTO payment_fees (brand, band, pct) VALUES (?, ?, ?)', $r);
            }
        });
        return null;
    }

    // ---------------------------------------------------------------
    // Datas: soma de meses e dia útil
    // ---------------------------------------------------------------

    /** Data válida no formato AAAA-MM-DD? */
    public static function validDate(?string $d): bool
    {
        if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return false;
        }
        [$y, $m, $day] = array_map('intval', explode('-', $d));
        return checkdate($m, $day, $y);
    }

    /** Soma meses a uma data sem estourar o fim do mês (31/01 + 1 mês = 28/02). */
    public static function addMonths(string $date, int $months): string
    {
        $d = new DateTimeImmutable($date);
        $day = (int) $d->format('j');
        $first = $d->modify('first day of this month')->modify(($months >= 0 ? '+' : '') . $months . ' month');
        $last = (int) $first->format('t');
        return $first->format('Y-m-') . str_pad((string) min($day, $last), 2, '0', STR_PAD_LEFT);
    }

    /** Domingo de Páscoa do ano (AAAA-MM-DD), pelo algoritmo gregoriano de Meeus/Jones/Butcher. */
    public static function easter(int $year): string
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Feriados nacionais em que o banco não opera: fixos + Carnaval (seg e ter), Sexta-feira Santa e
     * Corpus Christi. Feriado só da sua cidade ou estado NÃO entra. Retorna [AAAA-MM-DD => true].
     */
    public static function holidays(int $year): array
    {
        static $cache = [];
        if (isset($cache[$year])) {
            return $cache[$year];
        }
        $set = [];
        foreach (['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25'] as $md) {
            $set[$year . '-' . $md] = true;
        }
        $easter = new DateTimeImmutable(self::easter($year));
        foreach ([-48, -47, -2, 60] as $offset) {
            $set[$easter->modify(($offset >= 0 ? '+' : '') . $offset . ' day')->format('Y-m-d')] = true;
        }
        return $cache[$year] = $set;
    }

    public static function isBusinessDay(string $date): bool
    {
        $d = new DateTimeImmutable($date);
        if ((int) $d->format('N') >= 6) {
            return false;
        }
        return !isset(self::holidays((int) $d->format('Y'))[$date]);
    }

    /** A própria data se for dia útil; senão o próximo dia útil (cartão que cairia no fim de semana ou feriado). */
    public static function nextBusinessDay(string $date): string
    {
        $d = new DateTimeImmutable($date);
        for ($i = 0; $i < 10 && !self::isBusinessDay($d->format('Y-m-d')); $i++) {
            $d = $d->modify('+1 day');
        }
        return $d->format('Y-m-d');
    }

    // ---------------------------------------------------------------
    // Criar a venda e as parcelas
    // ---------------------------------------------------------------

    /** Divide em centavos exatos: o último recebe o resto. */
    private static function splitCents(int $cents, int $n): array
    {
        $base = intdiv($cents, $n);
        $parts = array_fill(0, $n, $base);
        $parts[$n - 1] += $cents - $base * $n;
        return $parts;
    }

    /**
     * Calendário de parcelas mensais: [['number', 'due_on', 'amount'], ...].
     * R$ 100,00 em 3x => 33,33 + 33,33 + 33,34. Com $business = true (cartão), cada data que cair em
     * fim de semana ou feriado vai para o próximo dia útil (o dia combinado continua o mesmo nos meses seguintes).
     */
    public static function schedule(float $total, int $n, string $firstDue, bool $business = false): array
    {
        $n = max(1, min(12, $n));
        $parts = self::splitCents((int) round($total * 100), $n);
        $out = [];
        foreach ($parts as $i => $cents) {
            $due = self::addMonths($firstDue, $i);
            $out[] = ['number' => $i + 1, 'due_on' => $business ? self::nextBusinessDay($due) : $due, 'amount' => $cents / 100];
        }
        return $out;
    }

    /**
     * Lê e confere os campos de pagamento do formulário: method, installments, first_due e o
     * recebimento (checkbox $paidKey = 1ª parcela já recebida; ou paid_count no registro avulso).
     * Para USD/EUR com parcela já recebida é obrigatório informar o R$ creditado (received_brl).
     * Retorna o array para Payments::create ou um texto de erro.
     * Usado pelo cadastro e pela renovação do paciente e pela conversão de lead (CRM): uma regra só para todos.
     */
    public static function parseForm(float $total, string $cur, string $paidKey): array|string
    {
        $method = Payments::methodOrDefault($_POST['method'] ?? 'outro');
        $n = (int) ($_POST['installments'] ?? 1);
        if ($n < 1 || $n > 12) {
            return 'Parcelas: escolha de 1 a 12.';
        }
        $firstDue = trim((string) ($_POST['first_due'] ?? ''));
        if ($firstDue === '') {
            $firstDue = today();
        }
        if (!Payments::validDate($firstDue)) {
            return 'Data da 1ª parcela inválida.';
        }
        $paid = $paidKey === 'paid_count' ? (int) ($_POST['paid_count'] ?? 0) : (!empty($_POST[$paidKey]) ? 1 : 0);
        if ($paid < 0 || $paid > $n) {
            return 'As parcelas já recebidas não podem ser mais que o total de parcelas.';
        }
        // Cartão em reais cai sozinho no vencimento: não há "já recebida" para marcar
        $auto = Payments::isAuto($method, $cur);
        if ($auto) {
            $paid = 0;
        }
        $brl = null;
        if ($cur !== 'BRL' && $paid > 0) {
            $brl = parse_money((string) ($_POST['received_brl'] ?? ''));
            if ($brl === false || $brl === null || $brl <= 0) {
                return 'Para ' . currencies()[$cur][1] . ' já recebido, informe quanto caiu em reais (R$).';
            }
        }
        // Bandeira e taxa do cartão (taxa em branco = usa a tabela de Financeiro > Taxas do cartão)
        $brand = null;
        $fee = null;
        if ($auto) {
            $b = trim((string) ($_POST['card_brand'] ?? ''));
            $brand = isset(Payments::brands()[$b]) ? $b : null;
            $fee = Payments::parsePct((string) ($_POST['fee_pct'] ?? ''));
            if ($fee === false) {
                return 'Taxa do cartão inválida. Use um número de 0 a 30, por exemplo 2,5.';
            }
        }
        return ['method' => $method, 'installments' => $n, 'first_due' => $firstDue, 'paid_count' => $paid, 'received_brl' => $brl, 'brand' => $brand, 'fee_pct' => $fee];
    }

    /**
     * Registra um pagamento com suas parcelas.
     * - Cartão em R$: a bandeira e a taxa (%) valem para a venda toda; cada parcela guarda o valor líquido.
     *   As parcelas caem sozinhas no vencimento, então "já recebidas" não se aplica ($paidCount é ignorado).
     * - Outras formas: as primeiras $paidCount já entram como recebidas (na data prevista, ou hoje se for futura).
     * - Moeda estrangeira: $receivedBrl é o total em reais que creditou (dividido entre as recebidas).
     * Chamar dentro de uma transação quando fizer parte de outra operação (renovação, cadastro).
     */
    public static function create(
        int $userId,
        ?int $planId,
        ?int $renewalId,
        float $total,
        string $currency,
        string $method,
        int $installments,
        string $soldOn,
        string $firstDue,
        int $paidCount = 0,
        ?float $receivedBrl = null,
        ?int $adminId = null,
        ?string $brand = null,
        ?float $feePct = null
    ): int {
        $currency = currency_or_brl($currency);
        $method = self::methodOrDefault($method);
        $n = max(1, min(12, $installments));
        $auto = self::isAuto($method, $currency);
        $paidCount = $auto ? 0 : max(0, min($n, $paidCount));

        $brand = ($method === 'cartao' && $brand && isset(self::brands()[$brand])) ? $brand : null;
        if ($auto) {
            $fee = $feePct !== null ? max(0.0, min(30.0, $feePct)) : self::feeFor($brand, $n);
        } else {
            $fee = 0.0;
        }

        $pid = DB::insert(
            'INSERT INTO payments (user_id, renewal_id, plan_id, total, currency, method, card_brand, fee_pct, installments, sold_on, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $renewalId, $planId, $total, $currency, $method, $brand, $fee, $n, $soldOn, $adminId, now()]
        );

        $brlParts = ($currency !== 'BRL' && $paidCount > 0 && $receivedBrl !== null)
            ? self::splitCents((int) round($receivedBrl * 100), $paidCount)
            : [];

        foreach (self::schedule($total, $n, $firstDue, $auto) as $i => $row) {
            $paid = $i < $paidCount;
            $net = $currency === 'BRL' ? round($row['amount'] * (1 - $fee / 100), 2) : null;
            $recOn = null;
            $recBrl = null;
            if ($paid) {
                $recOn = $row['due_on'] > today() ? today() : $row['due_on'];
                $recBrl = $currency === 'BRL' ? $net : (isset($brlParts[$i]) ? $brlParts[$i] / 100 : null);
            }
            DB::run(
                'INSERT INTO payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$pid, $row['number'], $row['due_on'], $row['amount'], $net, $currency, $paid ? 'received' : 'pending', $recOn, $recBrl]
            );
        }
        return $pid;
    }

    // ---------------------------------------------------------------
    // Ações sobre uma parcela
    // ---------------------------------------------------------------

    /**
     * Cartão em reais cai sozinho: toda parcela pendente que já venceu vira "recebida" na data do vencimento,
     * pelo valor líquido. Chamado ao abrir o Painel, o Financeiro e a ficha do paciente. Retorna quantas mudaram.
     */
    public static function syncCards(): int
    {
        return DB::run(
            "UPDATE payment_installments
                SET status = 'received', received_on = due_on, received_brl = COALESCE(net_amount, amount)
              WHERE status = 'pending' AND currency = 'BRL' AND due_on <= ?
                AND payment_id IN (SELECT id FROM payments WHERE method = 'cartao')",
            [today()]
        );
    }

    /**
     * Marca uma parcela como recebida. Em USD/EUR é preciso informar quanto creditou em reais.
     * Retorna null se deu certo, ou o texto do erro.
     */
    public static function receive(int $installmentId, ?string $receivedOn, ?float $brl): ?string
    {
        $row = DB::one('SELECT * FROM payment_installments WHERE id = ?', [$installmentId]);
        if (!$row) {
            return 'Parcela não encontrada.';
        }
        if ($row['status'] === 'canceled') {
            return 'Esta parcela está cancelada. Reative antes de marcar como recebida.';
        }
        $on = ($receivedOn !== null && $receivedOn !== '') ? $receivedOn : today();
        if (!self::validDate($on)) {
            return 'Data inválida.';
        }
        if ($on > today()) {
            return 'A data do recebimento não pode ser no futuro.';
        }

        $foreign = $row['currency'] !== 'BRL';
        if ($foreign && ($brl === null || $brl <= 0)) {
            return 'Informe o valor em reais (R$) que caiu na conta.';
        }

        if ($row['status'] === 'received' && !$foreign) {
            return null; // já estava recebida: não faz nada (pode clicar duas vezes sem problema)
        }

        $brlValue = $foreign ? $brl : (float) ($row['net_amount'] ?? $row['amount']);
        $keepOn = ($row['status'] === 'received' && $row['received_on']) ? $row['received_on'] : $on;
        DB::run(
            "UPDATE payment_installments SET status = 'received', received_on = ?, received_brl = ? WHERE id = ?",
            [$keepOn, $brlValue, $installmentId]
        );
        return null;
    }

    /**
     * Desfaz o "recebi" (volta para pendente) ou reativa uma parcela cancelada.
     * Cartão em reais que já caiu não se desfaz (cai sozinho); se houve estorno, use "Cancelar".
     * Retorna null se deu certo, ou o texto do erro.
     */
    public static function undo(int $installmentId): ?string
    {
        $row = self::installment($installmentId);
        if (!$row) {
            return 'Parcela não encontrada.';
        }
        if ($row['status'] === 'received' && self::isAuto($row['method'], $row['currency'])) {
            return 'Parcela de cartão cai sozinha no vencimento. Se houve estorno ou cancelamento, use "Cancelar".';
        }
        DB::run(
            "UPDATE payment_installments SET status = 'pending', received_on = NULL, received_brl = NULL WHERE id = ?",
            [$installmentId]
        );
        return null;
    }

    /** Cancela a parcela (estorno, paciente que parou de pagar): sai de todas as somas e listas de cobrança. */
    public static function cancel(int $installmentId): void
    {
        DB::run(
            "UPDATE payment_installments SET status = 'canceled', received_on = NULL, received_brl = NULL WHERE id = ?",
            [$installmentId]
        );
    }

    /** Guarda o comprovante (e apaga o anterior, se havia). */
    public static function setProof(int $installmentId, string $file): void
    {
        $old = DB::val('SELECT proof_file FROM payment_installments WHERE id = ?', [$installmentId]);
        DB::run('UPDATE payment_installments SET proof_file = ? WHERE id = ?', [$file, $installmentId]);
        if ($old && $old !== $file) {
            Upload::deleteProof((string) $old);
        }
    }

    /** Dados da parcela com paciente e forma de pagamento (auditoria, regras e comprovante). */
    public static function installment(int $id): ?array
    {
        return DB::one(
            'SELECT i.*, p.user_id, p.method FROM payment_installments i JOIN payments p ON p.id = i.payment_id WHERE i.id = ?',
            [$id]
        );
    }

    // ---------------------------------------------------------------
    // Textos e links (cobrança no WhatsApp, situação da parcela)
    // ---------------------------------------------------------------

    /** "20/11" (ou "20/11/2027" se for de outro ano). */
    public static function shortDate(?string $date): string
    {
        if (!$date) {
            return '-';
        }
        $t = strtotime($date);
        return date('Y', $t) === date('Y') ? date('d/m', $t) : date('d/m/Y', $t);
    }

    /** Mensagem pronta para cobrar uma parcela no WhatsApp (o paciente já sabe o combinado). */
    public static function chargeMessage(array $r): string
    {
        $first = first_name((string) ($r['patient_name'] ?? ''));
        $n = (int) $r['number'];
        $t = (int) ($r['total_n'] ?? 1);
        $what = $t > 1 ? 'a parcela ' . $n . '/' . $t : 'o pagamento';
        $plan = !empty($r['plan_name']) ? ' do plano ' . $r['plan_name'] : '';
        $when = $r['due_on'] < today() ? 'que venceu no dia ' . self::shortDate($r['due_on']) : 'combinada para hoje';
        $ask = ($r['method'] ?? '') === 'pix'
            ? 'Quando fizer o Pix, me manda o comprovante por aqui, combinado?'
            : 'Assim que pagar, me avisa por aqui, combinado?';
        return 'Oi, ' . $first . '! Aqui é do No Days Off Club. Passando para lembrar ' . $what . ' do seu acompanhamento' . $plan
            . ', ' . $when . ': ' . money((float) $r['amount'], 2, (string) $r['currency']) . '. ' . $ask;
    }

    /**
     * Acrescenta à parcela o que as telas precisam: auto (cai sozinha), late, net, fee,
     * st = situação (tag + texto) e wa = link de cobrança (só se for cobrar à mão e houver telefone).
     */
    private static function decorate(array $r): array
    {
        $today = today();
        $auto = self::isAuto($r['method'] ?? null, $r['currency'] ?? 'BRL');
        $r['auto'] = $auto;
        $r['late'] = $r['status'] === 'pending' && $r['due_on'] < $today;
        $brl = ($r['currency'] ?? 'BRL') === 'BRL';
        $r['net'] = $brl ? (float) ($r['net_amount'] ?? $r['amount']) : null;
        $r['fee'] = $brl ? round((float) $r['amount'] - (float) $r['net'], 2) : 0.0;

        $d = self::shortDate($r['due_on']);
        if ($r['status'] === 'canceled') {
            $st = ['', 'Cancelada'];
        } elseif ($r['status'] === 'received') {
            $on = self::shortDate($r['received_on']);
            $st = ['ok', $auto ? 'Caiu em ' . $on : 'Recebida em ' . $on];
        } elseif ($auto) {
            $st = ['', 'Cai em ' . $d];
        } elseif ($r['due_on'] < $today) {
            $st = ['bad', 'Atrasada · venceu ' . $d];
        } elseif ($r['due_on'] === $today) {
            $st = ['warn', 'Vence hoje'];
        } else {
            $st = ['', 'Vence em ' . $d];
        }
        $r['st'] = ['tag' => $st[0], 'text' => $st[1]];

        $r['wa'] = null;
        if ($r['status'] === 'pending' && !$auto && $r['due_on'] <= $today && !empty($r['phone'])) {
            $r['wa'] = wa_phone_url((string) $r['phone'], self::chargeMessage($r));
        }
        return $r;
    }

    // ---------------------------------------------------------------
    // Consultas do painel
    // ---------------------------------------------------------------

    /** Mês no formato AAAA-MM (inválido vira o mês atual). */
    public static function normalizeMonth(?string $ym): string
    {
        if (is_string($ym) && preg_match('/^(\d{4})-(\d{2})$/', $ym, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000 && (int) $m[1] <= 2100) {
            return $ym;
        }
        return date('Y-m');
    }

    /** [primeiro dia, primeiro dia do mês seguinte] */
    private static function range(string $ym): array
    {
        $first = $ym . '-01';
        return [$first, date('Y-m-d', strtotime($first . ' +1 month'))];
    }

    private static function byCurrency(array $rows, string $valueKey = 't'): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r['currency']] = (float) $r[$valueKey];
        }
        return $out;
    }

    /**
     * Valor "Vendido" de uma venda (use com a tabela payments apelidada de $p): o total da venda MENOS as parcelas
     * canceladas (estorno, paciente que parou de pagar, venda lançada por engano). Cancelar tudo zera a venda;
     * reativar a parcela devolve o valor.
     */
    public static function soldExpr(string $p = 'p'): string
    {
        return "({$p}.total - COALESCE((SELECT SUM(ci.amount) FROM payment_installments ci WHERE ci.payment_id = {$p}.id AND ci.status = 'canceled'), 0))";
    }

    /** Valor que ainda vai cair: em reais é o líquido (depois da taxa); em dólar/euro, o valor da parcela. */
    private static function netExpr(string $a = ''): string
    {
        $p = $a === '' ? '' : $a . '.';
        return "CASE WHEN {$p}currency = 'BRL' THEN COALESCE({$p}net_amount, {$p}amount) ELSE {$p}amount END";
    }

    /**
     * Resumo do mês:
     * sold      = faturamento (vendas do mês, valor bruto), por moeda;  soldCount = quantas vendas
     * received  = o que caiu no mês em R$ (líquido; USD/EUR pelo valor creditado);  receivedCount = quantas parcelas
     * receivedMissing = recebidas em moeda estrangeira ainda sem o valor em R$
     * pending   = o que ainda falta cair no mês, por moeda (líquido em R$);  pendingCount = quantas parcelas
     */
    public static function monthSummary(string $ym): array
    {
        [$from, $to] = self::range($ym);
        $soldExpr = self::soldExpr();
        $sold = DB::all(
            "SELECT p.currency, SUM({$soldExpr}) AS t, SUM(CASE WHEN {$soldExpr} > 0.004 THEN 1 ELSE 0 END) AS n FROM payments p WHERE p.sold_on >= ? AND p.sold_on < ? GROUP BY p.currency",
            [$from, $to]
        );
        // venda toda cancelada não conta nem aparece (nem como "US$ 0")
        $sold = array_values(array_filter($sold, fn($r) => (float) $r['t'] > 0.004));
        $soldCount = 0;
        foreach ($sold as $r) {
            $soldCount += (int) $r['n'];
        }
        $recv = DB::one(
            "SELECT COALESCE(SUM(received_brl), 0) AS t, COUNT(*) AS n FROM payment_installments
              WHERE status = 'received' AND received_on >= ? AND received_on < ?",
            [$from, $to]
        );
        $missing = (int) DB::val(
            "SELECT COUNT(*) FROM payment_installments WHERE status = 'received' AND received_brl IS NULL AND received_on >= ? AND received_on < ?",
            [$from, $to]
        );
        $pend = DB::all(
            'SELECT currency, SUM(' . self::netExpr() . ") AS t, COUNT(*) AS n FROM payment_installments
              WHERE status = 'pending' AND due_on >= ? AND due_on < ? GROUP BY currency",
            [$from, $to]
        );
        $pendingCount = 0;
        foreach ($pend as $r) {
            $pendingCount += (int) $r['n'];
        }
        return [
            'sold' => self::byCurrency($sold),
            'soldCount' => $soldCount,
            'received' => (float) $recv['t'],
            'receivedCount' => (int) $recv['n'],
            'receivedMissing' => $missing,
            'pending' => self::byCurrency($pend),
            'pendingCount' => $pendingCount,
        ];
    }

    /**
     * Parcelas para cobrar ou conferir à mão: pendentes que vencem hoje ou já venceram, exceto cartão em R$
     * (esse cai sozinho). ['count' => n, 'late' => n atrasadas, 'today' => n que vencem hoje, 'by' => [moeda => total]]
     */
    public static function attentionStats(): array
    {
        static $cache = null;
        if ($cache !== null && $cache['_day'] === today()) {
            return $cache;
        }
        $rows = DB::all(
            "SELECT i.currency, COUNT(*) AS n, SUM(i.amount) AS t, SUM(CASE WHEN i.due_on < ? THEN 1 ELSE 0 END) AS late
               FROM payment_installments i JOIN payments p ON p.id = i.payment_id
              WHERE i.status = 'pending' AND i.due_on <= ? AND NOT (p.method = 'cartao' AND i.currency = 'BRL')
              GROUP BY i.currency",
            [today(), today()]
        );
        $count = 0;
        $late = 0;
        foreach ($rows as $r) {
            $count += (int) $r['n'];
            $late += (int) $r['late'];
        }
        return $cache = ['_day' => today(), 'count' => $count, 'late' => $late, 'today' => $count - $late, 'by' => self::byCurrency($rows)];
    }

    /** As parcelas da lista "para cobrar e conferir", das mais antigas para as de hoje. */
    public static function attention(): array
    {
        $rows = DB::all(
            self::listSelect() . "WHERE i.status = 'pending' AND i.due_on <= ? AND NOT (p.method = 'cartao' AND i.currency = 'BRL')
              ORDER BY i.due_on ASC, i.id ASC",
            [today()]
        );
        return array_map([self::class, 'decorate'], $rows);
    }

    private static function listSelect(): string
    {
        return "SELECT i.*, p.user_id, p.method, p.card_brand, p.fee_pct, p.installments AS total_n, p.total AS payment_total,
                       p.sold_on, u.name AS patient_name, u.phone, pl.name AS plan_name
                  FROM payment_installments i
                  JOIN payments p ON p.id = i.payment_id
                  JOIN users u ON u.id = p.user_id
                  LEFT JOIN plans pl ON pl.id = p.plan_id ";
    }

    /**
     * Agenda de recebíveis: [AAAA-MM => ['label' => 'nov/2026', 'by' => [moeda => total], 'count' => n]] para
     * o mês atual e os $months-1 seguintes. Só parcelas pendentes, em valor líquido. Se houver parcelas
     * atrasadas de meses anteriores, vêm numa primeira linha de chave 'late'.
     */
    public static function receivables(int $months = 12): array
    {
        $start = date('Y-m') . '-01';
        $end = date('Y-m-d', strtotime($start . ' +' . $months . ' month'));
        $net = self::netExpr();
        $rows = DB::all(
            "SELECT SUBSTR(due_on, 1, 7) AS ym, currency, COUNT(*) AS n, SUM($net) AS t
               FROM payment_installments
              WHERE status = 'pending' AND due_on >= ? AND due_on < ?
              GROUP BY SUBSTR(due_on, 1, 7), currency",
            [$start, $end]
        );
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $d = strtotime($start . ' +' . $i . ' month');
            $out[date('Y-m', $d)] = ['label' => mb_substr(month_name((int) date('n', $d)), 0, 3, 'UTF-8') . '/' . date('Y', $d), 'by' => [], 'count' => 0];
        }
        foreach ($rows as $r) {
            if (isset($out[$r['ym']])) {
                $out[$r['ym']]['by'][$r['currency']] = (float) $r['t'];
                $out[$r['ym']]['count'] += (int) $r['n'];
            }
        }
        // Parcelas que já deveriam ter caído em meses anteriores: linha "late" no começo
        $old = DB::all(
            "SELECT currency, COUNT(*) AS n, SUM($net) AS t FROM payment_installments WHERE status = 'pending' AND due_on < ? GROUP BY currency",
            [$start]
        );
        if ($old) {
            $by = self::byCurrency($old);
            $n = 0;
            foreach ($old as $r) {
                $n += (int) $r['n'];
            }
            $out = ['late' => ['label' => 'Atrasadas (meses anteriores)', 'by' => $by, 'count' => $n]] + $out;
        }
        return $out;
    }

    /**
     * Lista de parcelas para a tela Financeiro.
     * $filter: month (todas que vencem no mês) | pending (falta cair no mês) | received (caíram no mês) | late (todas as atrasadas)
     * Parcelas canceladas não aparecem.
     */
    public static function installments(string $ym, string $filter): array
    {
        [$from, $to] = self::range($ym);
        $base = self::listSelect();
        switch ($filter) {
            case 'late':
                $rows = DB::all($base . "WHERE i.status = 'pending' AND i.due_on < ? ORDER BY i.due_on ASC, i.id ASC", [today()]);
                break;
            case 'pending':
                $rows = DB::all($base . "WHERE i.status = 'pending' AND i.due_on >= ? AND i.due_on < ? ORDER BY i.due_on ASC, i.id ASC", [$from, $to]);
                break;
            case 'received':
                $rows = DB::all($base . "WHERE i.status = 'received' AND i.received_on >= ? AND i.received_on < ? ORDER BY i.received_on ASC, i.id ASC", [$from, $to]);
                break;
            default:
                $rows = DB::all($base . "WHERE i.status <> 'canceled' AND i.due_on >= ? AND i.due_on < ? ORDER BY i.due_on ASC, i.id ASC", [$from, $to]);
        }
        return array_map([self::class, 'decorate'], $rows);
    }

    /** Pagamentos de um paciente, cada um com suas parcelas (ficha do paciente). */
    public static function forUser(int $userId): array
    {
        $who = DB::one('SELECT name, phone FROM users WHERE id = ?', [$userId]) ?? ['name' => '', 'phone' => null];
        $pays = DB::all(
            'SELECT p.*, pl.name AS plan_name FROM payments p LEFT JOIN plans pl ON pl.id = p.plan_id
              WHERE p.user_id = ? ORDER BY p.sold_on DESC, p.id DESC',
            [$userId]
        );
        foreach ($pays as &$p) {
            $items = DB::all('SELECT * FROM payment_installments WHERE payment_id = ? ORDER BY number ASC', [$p['id']]);
            $recv = 0;
            $late = 0;
            $canceled = 0;
            foreach ($items as &$i) {
                $i['method'] = $p['method'];
                $i['total_n'] = $p['installments'];
                $i['patient_name'] = $who['name'];
                $i['phone'] = $who['phone'];
                $i['plan_name'] = $p['plan_name'];
                $i = self::decorate($i);
                $recv += $i['status'] === 'received' ? 1 : 0;
                $late += $i['late'] ? 1 : 0;
                $canceled += $i['status'] === 'canceled' ? 1 : 0;
            }
            unset($i);
            $p['items'] = $items;
            $p['received_count'] = $recv;
            $p['late_count'] = $late;
            $p['canceled_count'] = $canceled;
        }
        return $pays;
    }
}
