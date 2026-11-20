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
     */
    public static function renew(int $userId, int $days, ?int $adminId, ?float $amount = null, ?int $planId = null): string
    {
        return DB::tx(function () use ($userId, $days, $adminId, $amount, $planId) {
            $u = DB::one('SELECT renewal_date FROM users WHERE id = ?', [$userId]);
            $prev = $u['renewal_date'] ?? null;
            $base = ($prev && substr($prev, 0, 10) > today()) ? substr($prev, 0, 10) : today();
            $new = date('Y-m-d', strtotime($base . ' +' . $days . ' day'));

            DB::run('UPDATE users SET renewal_date = ? WHERE id = ?', [$new, $userId]);
            $rid = DB::insert(
                'INSERT INTO renewals (user_id, previous_date, new_date, days_added, plan_id, amount, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, $prev, $new, $days, $planId, $amount, $adminId, now()]
            );
            Points::award($userId, 'renewal', 'renewal', $rid, 'Bônus por renovar');
            return $new;
        });
    }
}
