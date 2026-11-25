<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\View;
use App\Services\Athletes;
use App\Services\Birthdays;
use App\Services\Insights;
use App\Services\Leads;
use App\Services\Payments;
use App\Services\Plans;
use App\Services\Renewal;
use App\Services\Settings;

final class DashboardController
{
    public function index(): void
    {
        Payments::syncCards(); // cartão em R$ que já venceu cai sozinho

        // Duas abas: "Operação" (o dia a dia, abre primeiro) e "Visão executiva" (indicadores do negócio).
        // Endereços antigos com período/gráfico (?p= ou ?g=) continuam abrindo a Visão executiva.
        $aba = (string) ($_GET['aba'] ?? '');
        $executiva = $aba === 'executiva' || ($aba === '' && (isset($_GET['p']) || isset($_GET['g'])));
        if ($executiva) {
            $period = Insights::periodKey($_GET['p'] ?? null);
            $graph = Insights::graphKey($_GET['g'] ?? null);
            View::render('admin/dashboard', [
                'title' => 'Painel', 'aba' => 'executiva', 'period' => $period, 'graph' => $graph,
                'x' => Insights::build($period, $graph),
            ], 'admin');
            return;
        }

        $yellow = (int) Settings::get('warn_yellow_days', 15);
        $limitDate = date('Y-m-d', strtotime('+' . $yellow . ' day'));

        $stats = [
            'patients' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND renewal_date >= ?", [today()]),
            'expiring' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND renewal_date IS NOT NULL AND renewal_date >= ? AND renewal_date <= ?", [today(), $limitDate]),
            'expired' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND renewal_date IS NOT NULL AND renewal_date < ?", [today()]),
        ];

        // Painel de risco: quem vence logo (ou já venceu). O que é do app do paciente fica no módulo App do paciente (09.5)
        $rows = DB::all(
            "SELECT u.id, u.name, u.avatar, u.phone, u.renewal_date,
                    pl.name AS plan_name,
                    CASE WHEN bl.price IS NOT NULL AND bl.source <> 'reajuste' THEN bl.price ELSE pl.price END AS plan_value,
                    CASE WHEN bl.price IS NOT NULL AND bl.source <> 'reajuste' THEN bl.currency ELSE 'BRL' END AS plan_currency
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
            $r['wa'] = wa_phone_url($r['phone'], 'Oi, ' . first_name($r['name']) . '! Aqui é do No Days Off Club. Vamos conversar sobre a renovação do seu plano' . ($r['plan_name'] ? ' ' . $r['plan_name'] : '') . '?');
            $risk[] = $r;
        }

        $events = Athletes::upcoming(8);
        $stats['athletes'] = Athletes::countAthletes();

        $birthdays = Birthdays::forWindow('7');
        foreach ($birthdays as &$b) {
            $b['wa'] = Birthdays::whatsappUrl($b['phone'] ?? null, (string) $b['name']);
        }
        unset($b);

        View::render('admin/dashboard', ['title' => 'Painel', 'aba' => 'operacao', 'stats' => $stats, 'risk' => $risk, 'yellow' => $yellow, 'due30' => Plans::dueWithin(30), 'pix' => Payments::attentionStats(), 'crm' => Leads::attention(),
            'events' => $events, 'eventSports' => Athletes::namesFor(array_column($events, 'id')), 'birthdays' => $birthdays,
        ], 'admin');
    }
}
