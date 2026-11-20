<?php
namespace App\Services;

use App\Core\DB;
use DateTimeImmutable;

/**
 * Financeiro do Gabriel: pagamentos (vendas/renovações) e parcelas (recebíveis).
 *
 * - Faturamento do mês = soma do valor TOTAL das vendas feitas no mês (por moeda).
 * - Recebível = parcela com data prevista. Cartão cai todo mês (sem antecipar); Pix o Gabriel confere.
 * - Parcela pendente com data anterior a hoje fica "atrasada".
 * - USD/EUR: o total em reais usa o valor que CREDITOU na conta (received_brl).
 * - Valores brutos por enquanto (sem taxas). Um campo de taxa pode ser acrescentado depois.
 *
 * IMPORTANTE: tudo aqui é só do painel admin. O paciente nunca vê valores.
 */
final class Payments
{
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

    public static function methodOrDefault(?string $m): string
    {
        $m = trim((string) $m);
        return isset(self::methods()[$m]) ? $m : 'outro';
    }

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
     * R$ 100,00 em 3x => 33,33 + 33,33 + 33,34.
     */
    public static function schedule(float $total, int $n, string $firstDue): array
    {
        $n = max(1, min(12, $n));
        $parts = self::splitCents((int) round($total * 100), $n);
        $out = [];
        foreach ($parts as $i => $cents) {
            $out[] = ['number' => $i + 1, 'due_on' => self::addMonths($firstDue, $i), 'amount' => $cents / 100];
        }
        return $out;
    }

    /**
     * Registra um pagamento com suas parcelas. As primeiras $paidCount já entram como recebidas
     * (na data prevista de cada uma, ou hoje se for futura).
     * Para moeda estrangeira, $receivedBrl é o total em reais que creditou (dividido entre as recebidas).
     * Chamar dentro de uma transação quando fizer parte de outra operação (renovação).
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
        ?int $adminId = null
    ): int {
        $currency = currency_or_brl($currency);
        $method = self::methodOrDefault($method);
        $n = max(1, min(12, $installments));
        $paidCount = max(0, min($n, $paidCount));

        $pid = DB::insert(
            'INSERT INTO payments (user_id, renewal_id, plan_id, total, currency, method, installments, sold_on, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $renewalId, $planId, $total, $currency, $method, $n, $soldOn, $adminId, now()]
        );

        $brlParts = ($currency !== 'BRL' && $paidCount > 0 && $receivedBrl !== null)
            ? self::splitCents((int) round($receivedBrl * 100), $paidCount)
            : [];

        foreach (self::schedule($total, $n, $firstDue) as $i => $row) {
            $paid = $i < $paidCount;
            $recOn = null;
            $recBrl = null;
            if ($paid) {
                $recOn = $row['due_on'] > today() ? today() : $row['due_on'];
                $recBrl = $currency === 'BRL' ? $row['amount'] : (isset($brlParts[$i]) ? $brlParts[$i] / 100 : null);
            }
            DB::run(
                'INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$pid, $row['number'], $row['due_on'], $row['amount'], $currency, $paid ? 'received' : 'pending', $recOn, $recBrl]
            );
        }
        return $pid;
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

        $brlValue = $foreign ? $brl : (float) $row['amount'];
        $keepOn = ($row['status'] === 'received' && $row['received_on']) ? $row['received_on'] : $on;
        DB::run(
            "UPDATE payment_installments SET status = 'received', received_on = ?, received_brl = ? WHERE id = ?",
            [$keepOn, $brlValue, $installmentId]
        );
        return null;
    }

    /** Desfaz o "recebi" (volta para pendente). */
    public static function undo(int $installmentId): void
    {
        DB::run(
            "UPDATE payment_installments SET status = 'pending', received_on = NULL, received_brl = NULL WHERE id = ?",
            [$installmentId]
        );
    }

    /** Dados da parcela com paciente (para auditoria e retorno). */
    public static function installment(int $id): ?array
    {
        return DB::one(
            'SELECT i.*, p.user_id FROM payment_installments i JOIN payments p ON p.id = i.payment_id WHERE i.id = ?',
            [$id]
        );
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
     * Resumo do mês (todas as parcelas somem em reais, exceto onde dito):
     * sold      = faturamento do mês (vendas), por moeda
     * received  = recebido no mês em R$ (BRL + valor creditado de USD/EUR)
     * receivedMissing = recebidas em moeda estrangeira ainda sem o valor em R$
     * due       = parcelas com vencimento no mês (recebidas ou não), por moeda
     * pending   = parcelas do mês ainda a receber, por moeda
     */
    public static function monthSummary(string $ym): array
    {
        [$from, $to] = self::range($ym);
        $sold = self::byCurrency(DB::all(
            'SELECT currency, SUM(total) AS t FROM payments WHERE sold_on >= ? AND sold_on < ? GROUP BY currency',
            [$from, $to]
        ));
        $received = (float) DB::val(
            "SELECT COALESCE(SUM(received_brl), 0) FROM payment_installments WHERE status = 'received' AND received_on >= ? AND received_on < ?",
            [$from, $to]
        );
        $missing = (int) DB::val(
            "SELECT COUNT(*) FROM payment_installments WHERE status = 'received' AND received_brl IS NULL AND received_on >= ? AND received_on < ?",
            [$from, $to]
        );
        $due = self::byCurrency(DB::all(
            'SELECT currency, SUM(amount) AS t FROM payment_installments WHERE due_on >= ? AND due_on < ? GROUP BY currency',
            [$from, $to]
        ));
        $pending = self::byCurrency(DB::all(
            "SELECT currency, SUM(amount) AS t FROM payment_installments WHERE status = 'pending' AND due_on >= ? AND due_on < ? GROUP BY currency",
            [$from, $to]
        ));
        return ['sold' => $sold, 'received' => $received, 'receivedMissing' => $missing, 'due' => $due, 'pending' => $pending];
    }

    /** Parcelas atrasadas (pendentes com data anterior a hoje): ['count' => n, 'by' => [moeda => total]]. */
    public static function late(): array
    {
        $rows = DB::all(
            "SELECT currency, COUNT(*) AS n, SUM(amount) AS t FROM payment_installments WHERE status = 'pending' AND due_on < ? GROUP BY currency",
            [today()]
        );
        $count = 0;
        foreach ($rows as $r) {
            $count += (int) $r['n'];
        }
        return ['count' => $count, 'by' => self::byCurrency($rows)];
    }

    /**
     * Agenda de recebíveis: [AAAA-MM => ['label' => 'nov/2026', 'by' => [moeda => total], 'count' => n]] para
     * o mês atual e os $months-1 seguintes. Só parcelas pendentes. Se houver parcelas atrasadas de
     * meses anteriores, vêm numa primeira linha de chave 'late'.
     */
    public static function receivables(int $months = 12): array
    {
        $start = date('Y-m') . '-01';
        $end = date('Y-m-d', strtotime($start . ' +' . $months . ' month'));
        $rows = DB::all(
            "SELECT SUBSTR(due_on, 1, 7) AS ym, currency, COUNT(*) AS n, SUM(amount) AS t
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
            "SELECT currency, COUNT(*) AS n, SUM(amount) AS t FROM payment_installments WHERE status = 'pending' AND due_on < ? GROUP BY currency",
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
     * $filter: month (vencem no mês) | pending (a receber no mês) | received (recebidas no mês) | late (todas as atrasadas)
     */
    public static function installments(string $ym, string $filter): array
    {
        [$from, $to] = self::range($ym);
        $base = "SELECT i.*, p.user_id, p.method, p.installments AS total_n, p.total AS payment_total, p.sold_on,
                        u.name AS patient_name, pl.name AS plan_name
                   FROM payment_installments i
                   JOIN payments p ON p.id = i.payment_id
                   JOIN users u ON u.id = p.user_id
                   LEFT JOIN plans pl ON pl.id = p.plan_id ";
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
                $rows = DB::all($base . 'WHERE i.due_on >= ? AND i.due_on < ? ORDER BY i.due_on ASC, i.id ASC', [$from, $to]);
        }
        $today = today();
        foreach ($rows as &$r) {
            $r['late'] = $r['status'] === 'pending' && $r['due_on'] < $today;
        }
        return $rows;
    }

    /** Pagamentos de um paciente, cada um com suas parcelas (ficha do paciente). */
    public static function forUser(int $userId): array
    {
        $pays = DB::all(
            'SELECT p.*, pl.name AS plan_name FROM payments p LEFT JOIN plans pl ON pl.id = p.plan_id
              WHERE p.user_id = ? ORDER BY p.sold_on DESC, p.id DESC',
            [$userId]
        );
        $today = today();
        foreach ($pays as &$p) {
            $p['items'] = DB::all('SELECT * FROM payment_installments WHERE payment_id = ? ORDER BY number ASC', [$p['id']]);
            $recv = 0;
            $late = 0;
            foreach ($p['items'] as &$i) {
                $i['late'] = $i['status'] === 'pending' && $i['due_on'] < $today;
                $recv += $i['status'] === 'received' ? 1 : 0;
                $late += $i['late'] ? 1 : 0;
            }
            unset($i);
            $p['received_count'] = $recv;
            $p['late_count'] = $late;
        }
        return $pays;
    }
}
