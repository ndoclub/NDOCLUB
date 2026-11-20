<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;

/** Planos do acompanhamento (Trimestral, Semestral, Anual...) com valor em R$ (só o admin vê). */
final class PlanController
{
    public function index(): void
    {
        View::render('admin/plans/index', [
            'title' => 'Planos',
            'plans' => \App\Services\Plans::withCounts(),
        ], 'admin');
    }

    public function form(?int $id = null): void
    {
        $plan = $id ? DB::one('SELECT * FROM plans WHERE id = ?', [$id]) : null;
        if ($id && !$plan) {
            flash('err', 'Plano não encontrado.');
            redirect('/admin/planos');
        }
        View::render('admin/plans/form', ['title' => $plan ? 'Editar plano' : 'Novo plano', 'plan' => $plan], 'admin');
    }

    public function store(): void
    {
        $d = $this->validated('/admin/planos/novo');
        $id = DB::insert(
            'INSERT INTO plans (name, duration_days, price, active, created_at) VALUES (?, ?, ?, ?, ?)',
            [$d['name'], $d['days'], $d['price'], $d['active'], now()]
        );
        Audit::log((int) Auth::id(), 'plan_create', 'plan', $id, $d['name'] . ' (' . $d['days'] . ' dias)');
        flash('ok', 'Plano criado.');
        redirect('/admin/planos');
    }

    public function update(int $id): void
    {
        $plan = DB::one('SELECT * FROM plans WHERE id = ?', [$id]);
        if (!$plan) {
            redirect('/admin/planos');
        }
        $d = $this->validated('/admin/planos/' . $id);
        DB::run(
            'UPDATE plans SET name = ?, duration_days = ?, price = ?, active = ? WHERE id = ?',
            [$d['name'], $d['days'], $d['price'], $d['active'], $id]
        );
        $old = $plan['price'] === null ? 'sem valor' : money((float) $plan['price']);
        $new = $d['price'] === null ? 'sem valor' : money($d['price']);
        Audit::log((int) Auth::id(), 'plan_update', 'plan', $id, $d['name'] . ': ' . $old . ' -> ' . $new);
        flash('ok', 'Plano salvo.');
        redirect('/admin/planos');
    }

    private function validated(string $back): array
    {
        $name = input('name');
        $days = (int) ($_POST['duration_days'] ?? 0);
        $price = parse_money((string) ($_POST['price'] ?? ''));

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $errors[] = 'Informe o nome do plano (até 60 letras).';
        }
        if ($days < 7 || $days > 1100) {
            $errors[] = 'A duração precisa estar entre 7 e 1100 dias.';
        }
        if ($price === false) {
            $errors[] = 'Valor inválido. Use números, por exemplo 450 ou 450,00.';
        }
        if ($errors) {
            keep_old();
            foreach ($errors as $e) {
                flash('err', $e);
            }
            redirect($back);
        }
        return ['name' => $name, 'days' => $days, 'price' => $price, 'active' => empty($_POST['active']) ? 0 : 1];
    }
}
