<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;
use App\Services\Levels;
use App\Services\Points;
use App\Services\Ranking;

final class PointsController
{
    public function index(): void
    {
        View::render('admin/points', [
            'title' => 'Regras de pontos',
            'rules' => DB::all('SELECT * FROM point_rules ORDER BY sort_order, rkey'),
        ], 'admin');
    }

    public function save(): void
    {
        $rules = DB::all('SELECT rkey FROM point_rules');
        foreach ($rules as $r) {
            $k = $r['rkey'];
            $points = max(0, min(100000, (int) ($_POST['points'][$k] ?? 0)));
            $limit = max(0, min(1000, (int) ($_POST['limit'][$k] ?? 0)));
            $active = !empty($_POST['active'][$k]) ? 1 : 0;
            DB::run('UPDATE point_rules SET points = ?, daily_limit = ?, active = ? WHERE rkey = ?', [$points, $limit, $active, $k]);
        }
        Audit::log((int) Auth::id(), 'rules_update', 'rules');
        flash('ok', 'Regras de pontos salvas. Valem a partir de agora (o extrato antigo não muda).');
        redirect('/admin/pontos');
    }

    public function ranking(): void
    {
        $ym = Ranking::normalize($_GET['m'] ?? null);
        $list = Ranking::month($ym);
        foreach ($list as &$r) {
            $r['balance'] = Points::balance((int) $r['id']);
            $r['level'] = Levels::forPoints(Points::lifetime((int) $r['id']))['name'];
        }
        unset($r);
        View::render('admin/ranking', [
            'title' => 'Ranking',
            'list' => $list,
            'ym' => $ym,
            'months' => Ranking::availableMonths(),
        ], 'admin');
    }
}
