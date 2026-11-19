<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Points;
use App\Services\Streak;

final class CheckinController
{
    public const SLEEP = [5 => 'Até 5 h', 6 => '6 h', 7 => '7 h', 8 => '8 h ou mais'];
    public const WATER = [1000 => 'Até 1 L', 2000 => '2 L', 3000 => '3 L', 4000 => '4 L ou mais'];
    public const DIET = [2 => 'Segui o plano', 1 => 'Segui em parte', 0 => 'Não segui'];

    public function form(): void
    {
        $id = (int) Auth::id();
        $today = DB::one('SELECT * FROM checkins WHERE user_id = ? AND checkin_date = ?', [$id, today()]);
        View::render('patient/checkin', [
            'title' => 'Check-in',
            'today' => $today,
            'streak' => Streak::current($id),
            'sleep' => self::SLEEP,
            'water' => self::WATER,
            'diet' => self::DIET,
        ]);
    }

    public function save(): void
    {
        $id = (int) Auth::id();
        if (Streak::doneToday($id)) {
            flash('ok', 'Você já fez o check-in de hoje.');
            redirect('/home');
        }

        $sleep = (int) ($_POST['sleep'] ?? -1);
        $water = (int) ($_POST['water'] ?? -1);
        $diet = (int) ($_POST['diet'] ?? -1);
        if (!isset(self::SLEEP[$sleep]) || !isset(self::WATER[$water]) || !isset(self::DIET[$diet])) {
            flash('err', 'Responda as 3 perguntas para concluir o check-in.');
            redirect('/checkin');
        }

        try {
            $checkinId = DB::insert(
                'INSERT INTO checkins (user_id, checkin_date, sleep_hours, water_ml, diet_score, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, today(), $sleep, $water, $diet, now()]
            );
        } catch (\PDOException $e) {
            redirect('/home');
        }

        $earned = Points::award($id, 'checkin', 'checkin', $checkinId, 'Check-in de ' . fmt_date(today()));
        $streak = Streak::current($id);
        $bonus = 0;
        if (in_array($streak, [7, 30, 100], true)) {
            $bonus = Points::award($id, 'streak_' . $streak, 'checkin', $checkinId, $streak . ' dias seguidos');
        }

        $msg = 'Check-in feito. Sequência: ' . $streak . ($streak === 1 ? ' dia.' : ' dias.');
        if ($earned + $bonus > 0) {
            $msg .= ' +' . ($earned + $bonus) . ' pontos.';
        }
        flash('ok', $msg);
        redirect('/home');
    }
}
