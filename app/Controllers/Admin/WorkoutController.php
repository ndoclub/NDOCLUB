<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;

final class WorkoutController
{
    public function index(): void
    {
        $list = DB::all(
            'SELECT w.*, u.name AS patient FROM workouts w LEFT JOIN users u ON u.id = w.user_id
              ORDER BY CASE WHEN w.user_id IS NULL THEN 0 ELSE 1 END, u.name, w.sort_order, w.id'
        );
        View::render('admin/workouts/index', ['title' => 'Treinos', 'list' => $list], 'admin');
    }

    public function form(?int $id = null): void
    {
        $w = $id ? DB::one('SELECT * FROM workouts WHERE id = ?', [$id]) : null;
        if ($id && !$w) {
            flash('err', 'Treino não encontrado.');
            redirect('/admin/treinos');
        }
        View::render('admin/workouts/form', [
            'title' => $w ? 'Editar treino' : 'Novo treino',
            'w' => $w,
            'patients' => DB::all("SELECT id, name FROM users WHERE role = 'patient' AND status = 'active' ORDER BY name"),
            'presetUser' => (int) ($_GET['user_id'] ?? 0),
        ], 'admin');
    }

    public function store(): void
    {
        $data = $this->validated('/admin/treinos/novo');
        $id = DB::insert(
            'INSERT INTO workouts (user_id, title, details, weekday, sort_order, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$data['user_id'], $data['title'], $data['details'], $data['weekday'], $data['sort_order'], $data['active'], now()]
        );
        Audit::log((int) Auth::id(), 'workout_create', 'workout', $id, $data['title']);
        flash('ok', 'Treino criado.');
        redirect('/admin/treinos');
    }

    public function update(int $id): void
    {
        if (!DB::val('SELECT COUNT(*) FROM workouts WHERE id = ?', [$id])) {
            redirect('/admin/treinos');
        }
        $d = $this->validated('/admin/treinos/' . $id);
        DB::run(
            'UPDATE workouts SET user_id = ?, title = ?, details = ?, weekday = ?, sort_order = ?, active = ? WHERE id = ?',
            [$d['user_id'], $d['title'], $d['details'], $d['weekday'], $d['sort_order'], $d['active'], $id]
        );
        Audit::log((int) Auth::id(), 'workout_update', 'workout', $id, $d['title']);
        flash('ok', 'Treino salvo.');
        redirect('/admin/treinos');
    }

    public function delete(int $id): void
    {
        DB::run('DELETE FROM workouts WHERE id = ?', [$id]);
        Audit::log((int) Auth::id(), 'workout_delete', 'workout', $id);
        flash('ok', 'Treino excluído (o histórico de "feito" dele também).');
        redirect('/admin/treinos');
    }

    private function validated(string $backTo): array
    {
        $title = input('title');
        $details = input('details');
        $weekday = (int) ($_POST['weekday'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($title === '' || mb_strlen($title) > 80) {
            keep_old();
            flash('err', 'Informe o nome do treino (até 80 letras).');
            redirect($backTo);
        }
        if ($userId > 0 && !DB::val("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'patient'", [$userId])) {
            $userId = 0;
        }
        return [
            'user_id' => $userId ?: null,
            'title' => $title,
            'details' => $details ?: null,
            'weekday' => ($weekday >= 1 && $weekday <= 7) ? $weekday : null,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'active' => !empty($_POST['active']) ? 1 : 0,
        ];
    }
}
