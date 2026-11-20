<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Levels;
use App\Services\Points;
use App\Services\Ranking;
use App\Services\Renewal;
use App\Services\Settings;
use App\Services\Streak;

final class HomeController
{
    public function index(): void
    {
        $u = Auth::user();
        $id = (int) $u['id'];

        $life = Points::lifetime($id);
        $iso = (int) date('N');

        // Treinos de hoje (da ficha do paciente ou gerais)
        $todayWorkouts = DB::all(
            'SELECT w.id, w.title,
                    (SELECT COUNT(*) FROM workout_logs l WHERE l.workout_id = w.id AND l.user_id = ? AND l.done_on = ?) AS done
               FROM workouts w
              WHERE w.active = 1 AND (w.user_id = ? OR w.user_id IS NULL) AND w.weekday = ?
              ORDER BY w.sort_order, w.id',
            [$id, today(), $id, $iso]
        );

        $pinned = DB::one(
            "SELECT p.id, p.body, p.created_at, u.name FROM posts p JOIN users u ON u.id = p.user_id
              WHERE p.status = 'visible' AND p.is_pinned = 1 ORDER BY p.created_at DESC, p.id DESC LIMIT 1"
        );

        $pendingRedemptions = (int) DB::val(
            "SELECT COUNT(*) FROM redemptions WHERE user_id = ? AND status IN ('pending','approved')",
            [$id]
        );

        // Plano do paciente: só nome e duração (nunca o valor em R$, que é exclusivo do admin)
        $plan = !empty($u['plan_id']) ? DB::one('SELECT name, duration_days FROM plans WHERE id = ?', [(int) $u['plan_id']]) : null;
        $renew = Renewal::info($u['renewal_date']);
        $meterTotal = $plan ? max(1, (int) $plan['duration_days']) : 30;
        $meterPct = $renew['days'] === null ? 0 : (int) max(4, min(100, round(max(0, $renew['days']) / $meterTotal * 100)));

        View::render('patient/home', [
            'title' => 'Início',
            'streak' => Streak::current($id),
            'week' => Streak::week($id),
            'doneToday' => Streak::doneToday($id),
            'renew' => $renew,
            'planName' => $plan['name'] ?? null,
            'meterPct' => $meterPct,
            'whatsapp' => Renewal::whatsappUrl($u),
            'balance' => Points::balance($id),
            'level' => Levels::forPoints($life),
            'rank' => Ranking::positionOf($id),
            'monthPts' => Points::monthTotal($id),
            'theme' => [
                'title' => (string) Settings::get('theme_' . $iso, ''),
                'question' => (string) Settings::get('theme_' . $iso . '_q', ''),
            ],
            'pinned' => $pinned,
            'todayWorkouts' => $todayWorkouts,
            'pendingRedemptions' => $pendingRedemptions,
        ]);
    }
}
