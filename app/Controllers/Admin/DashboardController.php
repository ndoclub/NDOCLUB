<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\View;
use App\Services\Plans;
use App\Services\Renewal;
use App\Services\Settings;

final class DashboardController
{
    public function index(): void
    {
        $yellow = (int) Settings::get('warn_yellow_days', 15);
        $limitDate = date('Y-m-d', strtotime('+' . $yellow . ' day'));
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 day'));

        $stats = [
            'patients' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active'"),
            'expiring' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND renewal_date IS NOT NULL AND renewal_date >= ? AND renewal_date <= ?", [today(), $limitDate]),
            'expired' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND renewal_date IS NOT NULL AND renewal_date < ?", [today()]),
            'checkins' => (int) DB::val('SELECT COUNT(*) FROM checkins WHERE checkin_date = ?', [today()]),
            'redemptions' => (int) DB::val("SELECT COUNT(*) FROM redemptions WHERE status = 'pending'"),
            'reports' => (int) DB::val("SELECT COUNT(*) FROM reports WHERE status = 'open'"),
            'active7' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND last_seen_at >= ?", [$weekAgo]),
        ];

        // Painel de risco: quem vence logo (ou já venceu) e quanto tempo está sem abrir o app
        $rows = DB::all(
            "SELECT u.id, u.name, u.avatar, u.phone, u.renewal_date, u.last_seen_at,
                    pl.name AS plan_name, COALESCE(bl.price, pl.price) AS plan_value,
                    (SELECT MAX(c.checkin_date) FROM checkins c WHERE c.user_id = u.id) AS last_checkin
               FROM users u
               LEFT JOIN plans pl ON pl.id = u.plan_id
               LEFT JOIN patient_billing bl ON bl.user_id = u.id
              WHERE u.role = 'patient' AND u.status = 'active' AND u.renewal_date IS NOT NULL AND u.renewal_date <= ?
              ORDER BY u.renewal_date ASC LIMIT 40",
            [$limitDate]
        );
        $risk = [];
        foreach ($rows as $r) {
            $r['renew'] = Renewal::info($r['renewal_date']);
            $r['away'] = empty($r['last_seen_at']) || $r['last_seen_at'] < $weekAgo;
            $r['wa'] = wa_phone_url($r['phone'], 'Oi, ' . first_name($r['name']) . '! Aqui é do No Days Off Club. Vamos conversar sobre a renovação do seu plano' . ($r['plan_name'] ? ' ' . $r['plan_name'] : '') . '?');
            $risk[] = $r;
        }
        // Quem está sumido do app aparece primeiro
        usort($risk, fn($a, $b) => [(int) !$a['away'], $a['renewal_date']] <=> [(int) !$b['away'], $b['renewal_date']]);

        View::render('admin/dashboard', ['title' => 'Painel', 'stats' => $stats, 'risk' => $risk, 'yellow' => $yellow, 'due30' => Plans::dueWithin(30)], 'admin');
    }
}
