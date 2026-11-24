<?php
namespace App\Services;

use App\Core\DB;

/** Contador de dias para renovar o acompanhamento. */
final class Renewal
{
    /**
     * Selo por proximidade do vencimento:
     * verde (mais de 15 dias), amarelo (8 a 15), vermelho (7 ou menos / vencido).
     * Os limites 15 e 7 podem ser mudados em Admin > Configurações.
     */
    public static function info(?string $date): array
    {
        if (!$date) {
            return ['days' => null, 'seal' => 'none', 'label' => 'Sem data definida', 'short' => 'Sem data', 'word' => 'Sem data'];
        }
        $days = (int) days_until($date);
        $yellow = (int) Settings::get('warn_yellow_days', 15);
        $red = (int) Settings::get('warn_red_days', 7);
        $seal = $days <= $red ? 'red' : ($days <= $yellow ? 'yellow' : 'green');

        if ($days < 0) {
            $label = 'Vencido há ' . abs($days) . ($days === -1 ? ' dia' : ' dias');
        } elseif ($days === 0) {
            $label = 'Vence hoje';
        } elseif ($days === 1) {
            $label = 'Falta 1 dia';
        } else {
            $label = 'Faltam ' . $days . ' dias';
        }
        $short = $days < 0 ? 'Vencido' : ($days === 0 ? 'Hoje' : $days . ' d');
        $word = $seal === 'green' ? 'Em dia' : ($days < 0 ? 'Vencido' : ($seal === 'yellow' ? 'Atenção' : 'Urgente'));
        return ['days' => $days, 'seal' => $seal, 'label' => $label, 'short' => $short, 'word' => $word];
    }

    /**
     * Status do paciente para o admin (lista e ficha):
     *  Ativo = plano vigente (vence hoje ou depois) · Inativo = plano venceu e não renovou ·
     *  Desativado = conta bloqueada pelo admin · Sem data = ainda sem vencimento.
     * Retorna key (active, expired, disabled, nodate), label e tag (classe visual).
     */
    public static function planStatus(string $accountStatus, ?string $date): array
    {
        if ($accountStatus === 'inactive') {
            return ['key' => 'disabled', 'label' => 'Desativado', 'tag' => ''];
        }
        if (!$date) {
            return ['key' => 'nodate', 'label' => 'Sem data', 'tag' => ''];
        }
        return (int) days_until($date) < 0
            ? ['key' => 'expired', 'label' => 'Inativo', 'tag' => 'bad']
            : ['key' => 'active', 'label' => 'Ativo', 'tag' => 'ok'];
    }

    /** Frase curta sobre o vencimento ("Seu plano Trimestral vence em 5 dias."). Sem valores em R$. */
    public static function headline(array $renew, ?string $planName): string
    {
        $what = $planName ? 'Seu plano ' . $planName : 'Seu acompanhamento';
        $d = $renew['days'];
        if ($d === null) {
            return '';
        }
        if ($d < 0) {
            return $what . ' venceu.';
        }
        if ($d === 0) {
            return $what . ' vence hoje.';
        }
        return $what . ($renew['seal'] === 'red' ? ' vence em ' : ' termina em ') . $d . ($d === 1 ? ' dia.' : ' dias.');
    }

    /** Aviso completo mostrado no cartão de renovação do paciente. */
    public static function message(array $renew, ?string $planName): string
    {
        $head = self::headline($renew, $planName);
        if ($head === '') {
            return '';
        }
        if ($renew['days'] < 0) {
            return $head . ' Fale com o Gabriel para retomar de onde parou.';
        }
        if ($renew['seal'] === 'red') {
            return $head . ' Renove para não ficar sem acompanhamento e manter seus pontos rendendo.';
        }
        return $head . ' Fale com o Gabriel para renovar.';
    }

    /** Link do WhatsApp com mensagem pronta para renovar. */
    public static function whatsappUrl(array $user): string
    {
        $number = preg_replace('/\D+/', '', (string) Settings::get('whatsapp_number', ''));
        $msg = (string) Settings::get('whatsapp_message', 'Quero renovar meu acompanhamento.');
        $plan = !empty($user['plan_id']) ? Plans::name((int) $user['plan_id']) : null;
        if ($plan) {
            $msg .= ' Plano atual: ' . $plan . '.';
        }
        $msg .= ' (' . $user['name'] . ')';
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($msg);
    }

    /**
     * Soma dias à renovação: parte da data atual de vencimento
     * (ou de hoje, se já venceu), registra no histórico e dá o bônus de pontos.
     *
     * $amount/$currency = valor total vendido nesta renovação (admin). Se vier $pay, também registra
     * o pagamento e as parcelas (financeiro), tudo na mesma operação. $pay:
     *   method, installments, first_due (AAAA-MM-DD), paid_count (parcelas já recebidas),
     *   received_brl (R$ que creditou, para USD/EUR), brand e fee_pct (cartão em R$)
     * Quem estava só mantendo o valor antigo de um plano reajustado passa para o valor atual do plano.
     * Retorna a nova data de vencimento.
     */
    public static function renew(
        int $userId,
        int $days,
        ?int $adminId,
        ?float $amount = null,
        ?int $planId = null,
        ?array $pay = null,
        string $currency = 'BRL'
    ): string {
        return DB::tx(fn() => self::applyRenewal($userId, $days, $adminId, $amount, $planId, $pay, $currency)['date']);
    }

    /**
     * A mesma renovação de renew(), mas SEM abrir transação própria: para quem já está dentro de uma
     * (a conversão de lead em paciente). Retorna ['date' => nova data, 'renewal_id' => id, 'payment_id' => id ou null].
     */
    public static function applyRenewal(
        int $userId,
        int $days,
        ?int $adminId,
        ?float $amount = null,
        ?int $planId = null,
        ?array $pay = null,
        string $currency = 'BRL'
    ): array {
        $currency = currency_or_brl($currency);
        $u = DB::one('SELECT renewal_date FROM users WHERE id = ?', [$userId]);
        $prev = $u['renewal_date'] ?? null;
        $base = ($prev && substr($prev, 0, 10) > today()) ? substr($prev, 0, 10) : today();
        $new = date('Y-m-d', strtotime($base . ' +' . $days . ' day'));

        DB::run('UPDATE users SET renewal_date = ? WHERE id = ?', [$new, $userId]);
        $rid = DB::insert(
            'INSERT INTO renewals (user_id, previous_date, new_date, days_added, plan_id, amount, currency, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $prev, $new, $days, $planId, $amount, $currency, $adminId, now()]
        );
        $pid = null;
        if ($amount !== null && $pay !== null) {
            $pid = Payments::create(
                $userId,
                $planId,
                $rid,
                $amount,
                $currency,
                (string) ($pay['method'] ?? 'outro'),
                (int) ($pay['installments'] ?? 1),
                today(),
                (string) ($pay['first_due'] ?? today()),
                (int) ($pay['paid_count'] ?? 0),
                isset($pay['received_brl']) ? (float) $pay['received_brl'] : null,
                $adminId,
                $pay['brand'] ?? null,
                isset($pay['fee_pct']) ? (float) $pay['fee_pct'] : null
            );
        }
        Plans::clearReajuste($userId);
        Points::award($userId, 'renewal', 'renewal', $rid, 'Bônus por renovar');
        return ['date' => $new, 'renewal_id' => $rid, 'payment_id' => $pid];
    }
}
