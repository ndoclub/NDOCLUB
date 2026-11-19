<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Points;

final class WorkoutController
{
    public function index(): void
    {
        $id = (int) Auth::id();
        $workouts = DB::all(
            'SELECT w.*,
                    (SELECT COUNT(*) FROM workout_logs l WHERE l.workout_id = w.id AND l.user_id = ? AND l.done_on = ?) AS done_today
               FROM workouts w
              WHERE w.active = 1 AND (w.user_id = ? OR w.user_id IS NULL)
              ORDER BY CASE WHEN w.weekday IS NULL THEN 1 ELSE 0 END, w.weekday, w.sort_order, w.id',
            [$id, today(), $id]
        );

        // Calendário do mês: quais dias tiveram treino feito
        $first = date('Y-m-01');
        $last = date('Y-m-t');
        $logs = DB::all('SELECT done_on, COUNT(*) AS n FROM workout_logs WHERE user_id = ? AND done_on >= ? AND done_on <= ? GROUP BY done_on', [$id, $first, $last]);
        $doneDays = [];
        foreach ($logs as $l) {
            $doneDays[(int) substr($l['done_on'], 8, 2)] = (int) $l['n'];
        }

        View::render('patient/workouts', [
            'title' => 'Treino',
            'workouts' => $workouts,
            'doneDays' => $doneDays,
            'monthTotal' => array_sum($doneDays),
            'daysInMonth' => (int) date('t'),
            'startOffset' => (int) date('N', strtotime($first)) - 1,
            'todayDay' => (int) date('j'),
        ]);
    }

    public function done(int $workoutId): void
    {
        $id = (int) Auth::id();
        $w = DB::one('SELECT id, title FROM workouts WHERE id = ? AND active = 1 AND (user_id = ? OR user_id IS NULL)', [$workoutId, $id]);
        if (!$w) {
            flash('err', 'Treino não encontrado.');
            redirect('/treino');
        }
        $exists = DB::val('SELECT COUNT(*) FROM workout_logs WHERE user_id = ? AND workout_id = ? AND done_on = ?', [$id, $workoutId, today()]);
        if ($exists) {
            flash('ok', 'Este treino já está marcado como feito hoje.');
            redirect('/treino');
        }
        $logId = DB::insert('INSERT INTO workout_logs (user_id, workout_id, done_on, created_at) VALUES (?, ?, ?, ?)', [$id, $workoutId, today(), now()]);
        $earned = Points::award($id, 'workout', 'workout_log', $logId, $w['title']);
        flash('ok', 'Treino feito.' . ($earned > 0 ? ' +' . $earned . ' pontos.' : ' (Limite de pontos de treino de hoje já atingido.)'));
        redirect('/treino');
    }
}
