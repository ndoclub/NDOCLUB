<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Athletes;
use App\Services\Audit;

/** Modalidades esportivas (Corrida, Musculação, Hyrox, Natação...). Não são apagadas, só desativadas. */
final class SportController
{
    public function index(): void
    {
        View::render('admin/sports/index', ['title' => 'Modalidades', 'sports' => Athletes::sportsWithCounts()], 'admin');
    }

    public function form(?int $id = null): void
    {
        $sport = $id ? DB::one('SELECT * FROM sports WHERE id = ?', [$id]) : null;
        if ($id && !$sport) {
            flash('err', 'Modalidade não encontrada.');
            redirect('/admin/modalidades');
        }
        View::render('admin/sports/form', ['title' => $sport ? 'Editar modalidade' : 'Nova modalidade', 'sport' => $sport], 'admin');
    }

    public function store(): void
    {
        $d = $this->validated('/admin/modalidades/novo', null);
        $id = DB::insert('INSERT INTO sports (name, active, created_at) VALUES (?, ?, ?)', [$d['name'], $d['active'], now()]);
        Audit::log((int) Auth::id(), 'sport_create', 'sport', $id, $d['name']);
        flash('ok', 'Modalidade criada.');
        redirect('/admin/modalidades');
    }

    public function update(int $id): void
    {
        $sport = DB::one('SELECT * FROM sports WHERE id = ?', [$id]);
        if (!$sport) {
            redirect('/admin/modalidades');
        }
        $d = $this->validated('/admin/modalidades/' . $id, $id);
        DB::run('UPDATE sports SET name = ?, active = ? WHERE id = ?', [$d['name'], $d['active'], $id]);
        Audit::log((int) Auth::id(), 'sport_update', 'sport', $id, $sport['name'] . ' -> ' . $d['name'] . ($d['active'] ? '' : ' (desativada)'));
        flash('ok', 'Modalidade salva.');
        redirect('/admin/modalidades');
    }

    private function validated(string $back, ?int $ignoreId): array
    {
        $name = input('name');
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 40) {
            $errors[] = 'Informe o nome da modalidade (2 a 40 letras).';
        } else {
            $dup = DB::val('SELECT id FROM sports WHERE LOWER(name) = ? AND id <> ?', [mb_strtolower($name), (int) $ignoreId]);
            if ($dup) {
                $errors[] = 'Já existe uma modalidade com esse nome.';
            }
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
