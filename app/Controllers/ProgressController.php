<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;

/** Evolução: peso e cintura. Dados de saúde, visíveis só para o paciente e o admin. */
final class ProgressController
{
    public function index(): void
    {
        $id = (int) Auth::id();
        $rows = DB::all('SELECT * FROM measurements WHERE user_id = ? ORDER BY measured_on ASC, id ASC', [$id]);
        View::render('patient/progress', ['title' => 'Evolução', 'rows' => $rows]);
    }

    public function save(): void
    {
        $id = (int) Auth::id();
        $weight = parse_decimal(input('weight'));
        $waistRaw = input('waist');
        $waist = $waistRaw === '' ? null : parse_decimal($waistRaw);
        $date = input('date', today());

        $okDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false && $date <= today();
        if ($weight === null || $weight < 30 || $weight > 300 || !$okDate || ($waistRaw !== '' && ($waist === null || $waist < 30 || $waist > 250))) {
            keep_old();
            flash('err', 'Confira os dados: peso entre 30 e 300 kg, cintura entre 30 e 250 cm e data que não seja futura.');
            redirect('/evolucao');
        }

        $exists = DB::one('SELECT id FROM measurements WHERE user_id = ? AND measured_on = ?', [$id, $date]);
        if ($exists) {
            DB::run('UPDATE measurements SET weight_kg = ?, waist_cm = ? WHERE id = ?', [round($weight, 2), $waist, $exists['id']]);
        } else {
            DB::run('INSERT INTO measurements (user_id, measured_on, weight_kg, waist_cm, created_at) VALUES (?, ?, ?, ?, ?)', [$id, $date, round($weight, 2), $waist, now()]);
        }
        flash('ok', 'Medida salva.');
        redirect('/evolucao');
    }

    public function delete(int $mid): void
    {
        DB::run('DELETE FROM measurements WHERE id = ? AND user_id = ?', [$mid, Auth::id()]);
        flash('ok', 'Medida removida.');
        redirect('/evolucao');
    }
}
