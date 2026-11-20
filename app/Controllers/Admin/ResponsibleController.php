<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;
use App\Services\Responsibles;

/** Responsáveis pelos pacientes ("modo babá"). Não são apagados, só desativados. */
final class ResponsibleController
{
    public function index(): void
    {
        View::render('admin/responsibles/index', ['title' => 'Responsáveis', 'list' => Responsibles::withCounts()], 'admin');
    }

    public function form(?int $id = null): void
    {
        $r = $id ? DB::one('SELECT * FROM responsibles WHERE id = ?', [$id]) : null;
        if ($id && !$r) {
            flash('err', 'Responsável não encontrado.');
            redirect('/admin/responsaveis');
        }
        View::render('admin/responsibles/form', ['title' => $r ? 'Editar responsável' : 'Novo responsável', 'r' => $r], 'admin');
    }

    public function store(): void
    {
        $d = $this->validated('/admin/responsaveis/novo', null);
        $id = DB::insert('INSERT INTO responsibles (name, active, created_at) VALUES (?, ?, ?)', [$d['name'], $d['active'], now()]);
        Audit::log((int) Auth::id(), 'responsible_create', 'responsible', $id, $d['name']);
        flash('ok', 'Responsável criado. Agora, na lista de pacientes, marque quem é dela e use "Atribuir".');
        redirect('/admin/responsaveis');
    }

    public function update(int $id): void
    {
        $r = DB::one('SELECT * FROM responsibles WHERE id = ?', [$id]);
        if (!$r) {
            redirect('/admin/responsaveis');
        }
        $d = $this->validated('/admin/responsaveis/' . $id, $id);
        DB::run('UPDATE responsibles SET name = ?, active = ? WHERE id = ?', [$d['name'], $d['active'], $id]);
        Audit::log((int) Auth::id(), 'responsible_update', 'responsible', $id, $r['name'] . ' -> ' . $d['name'] . ($d['active'] ? '' : ' (desativado)'));
        flash('ok', 'Responsável salvo.');
        redirect('/admin/responsaveis');
    }

    private function validated(string $back, ?int $ignoreId): array
    {
        $name = input('name');
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors[] = 'Informe o nome (2 a 80 letras).';
        } elseif (DB::val('SELECT id FROM responsibles WHERE LOWER(name) = ? AND id <> ?', [mb_strtolower($name), (int) $ignoreId])) {
            $errors[] = 'Já existe um responsável com esse nome.';
        }
        if ($errors) {
            keep_old();
            foreach ($errors as $e) {
                flash('err', $e);
            }
            redirect($back);
        }
        return ['name' => $name, 'active' => empty($_POST['active']) ? 0 : 1];
    }
}
