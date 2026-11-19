<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\Ranking;

final class RankingController
{
    public function index(): void
    {
        $ym = Ranking::normalize($_GET['m'] ?? null);
        $list = Ranking::month($ym);
        $me = null;
        foreach ($list as $r) {
            if ((int) $r['id'] === (int) Auth::id()) {
                $me = $r;
            }
        }
        $isCurrent = $ym === date('Y-m');
        $resetDays = $isCurrent ? (int) days_until(date('Y-m-d', strtotime('first day of next month'))) : null;

        $prev = date('Y-m', strtotime($ym . '-01 -1 month'));
        $next = date('Y-m', strtotime($ym . '-01 +1 month'));

        View::render('patient/ranking', [
            'title' => 'Ranking',
            'ym' => $ym,
            'monthLabel' => month_name((int) substr($ym, 5, 2)) . ' de ' . substr($ym, 0, 4),
            'list' => array_slice($list, 0, 30),
            'total' => count($list),
            'me' => $me,
            'isCurrent' => $isCurrent,
            'resetDays' => $resetDays,
            'prev' => $prev,
            'next' => $isCurrent ? null : $next,
        ]);
    }
}
