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

    /** Link do WhatsApp com mensagem pronta para renovar. */
    public static function whatsappUrl(array $user): string
    {
        $number = preg_replace('/\D+/', '', (string) Settings::get('whatsapp_number', ''));
        $msg = (string) Settings::get('whatsapp_message', 'Quero renovar meu acompanhamento.');
        $msg .= ' (' . $user['name'] . ')';
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($msg);
    }

    /**
     * Soma dias à renovação: parte da data atual de vencimento
     * (ou de hoje, se já venceu), registra no histórico e dá o bônus de pontos.
     */
    public static function renew(int $userId, int $days, ?int $adminId): string
    {
        return DB::tx(function () use ($userId, $days, $adminId) {
            $u = DB::one('SELECT renewal_date FROM users WHERE id = ?', [$userId]);
            $prev = $u['renewal_date'] ?? null;
            $base = ($prev && substr($prev, 0, 10) > today()) ? substr($prev, 0, 10) : today();
            $new = date('Y-m-d', strtotime($base . ' +' . $days . ' day'));

            DB::run('UPDATE users SET renewal_date = ? WHERE id = ?', [$new, $userId]);
            $rid = DB::insert(
                'INSERT INTO renewals (user_id, previous_date, new_date, days_added, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $prev, $new, $days, $adminId, now()]
            );
            Points::award($userId, 'renewal', 'renewal', $rid, 'Bônus por renovar');
            return $new;
        });
    }
}
