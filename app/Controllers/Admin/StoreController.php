<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Upload;
use App\Core\View;
use App\Services\Audit;
use App\Services\Points;

final class StoreController
{
    public function index(): void
    {
        View::render('admin/store/index', [
            'title' => 'Loja',
            'items' => DB::all('SELECT * FROM store_items ORDER BY active DESC, cost_points ASC, id ASC'),
        ], 'admin');
    }

    public function form(?int $id = null): void
    {
        $item = $id ? DB::one('SELECT * FROM store_items WHERE id = ?', [$id]) : null;
        if ($id && !$item) {
            flash('err', 'Brinde não encontrado.');
            redirect('/admin/loja');
        }
        View::render('admin/store/form', ['title' => $item ? 'Editar brinde' : 'Novo brinde', 'item' => $item], 'admin');
    }

    public function store(): void
    {
        $d = $this->validated('/admin/loja/novo');
        $image = Upload::image($_FILES['image'] ?? null, 900, $err);
        if ($err) {
            keep_old();
            flash('err', $err);
            redirect('/admin/loja/novo');
        }
        $id = DB::insert(
            'INSERT INTO store_items (name, description, image, cost_points, stock, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$d['name'], $d['description'], $image, $d['cost'], $d['stock'], $d['active'], now()]
        );
        Audit::log((int) Auth::id(), 'item_create', 'item', $id, $d['name']);
        flash('ok', 'Brinde criado.');
        redirect('/admin/loja');
    }

    public function update(int $id): void
    {
        $item = DB::one('SELECT * FROM store_items WHERE id = ?', [$id]);
        if (!$item) {
            redirect('/admin/loja');
        }
        $d = $this->validated('/admin/loja/' . $id);
        $image = $item['image'];
        $new = Upload::image($_FILES['image'] ?? null, 900, $err);
        if ($err) {
            keep_old();
            flash('err', $err);
            redirect('/admin/loja/' . $id);
        }
        if ($new) {
            Upload::delete($image);
            $image = $new;
        }
        DB::run(
            'UPDATE store_items SET name = ?, description = ?, image = ?, cost_points = ?, stock = ?, active = ? WHERE id = ?',
            [$d['name'], $d['description'], $image, $d['cost'], $d['stock'], $d['active'], $id]
        );
        Audit::log((int) Auth::id(), 'item_update', 'item', $id, $d['name']);
        flash('ok', 'Brinde salvo.');
        redirect('/admin/loja');
    }

    public function redemptions(): void
    {
        $filter = (string) ($_GET['s'] ?? 'open');
        $where = match ($filter) {
            'delivered' => "r.status = 'delivered'",
            'rejected' => "r.status = 'rejected'",
            'all' => '1 = 1',
            default => "r.status IN ('pending','approved')",
        };
        $rows = DB::all(
            "SELECT r.*, i.name AS item_name, u.name AS user_name, u.phone
               FROM redemptions r JOIN store_items i ON i.id = r.item_id JOIN users u ON u.id = r.user_id
              WHERE $where ORDER BY r.created_at DESC, r.id DESC LIMIT 100"
        );
        View::render('admin/store/redemptions', ['title' => 'Resgates', 'rows' => $rows, 'filter' => $filter], 'admin');
    }

    /** pendente -> aprovado -> entregue, ou recusado (devolve pontos e estoque). */
    public function setStatus(int $id): void
    {
        $new = input('status');
        $note = input('note');
        $allowed = ['approved', 'delivered', 'rejected'];
        $r = DB::one('SELECT * FROM redemptions WHERE id = ?', [$id]);
        if (!$r || !in_array($new, $allowed, true) || in_array($r['status'], ['delivered', 'rejected'], true)) {
            flash('err', 'Este resgate não pode mais ser alterado.');
            redirect('/admin/resgates');
        }
        if ($new === 'delivered' && $r['status'] !== 'approved' && $r['status'] !== 'pending') {
            redirect('/admin/resgates');
        }

        DB::tx(function () use ($r, $new, $note, $id) {
            DB::run('UPDATE redemptions SET status = ?, admin_note = ?, updated_at = ? WHERE id = ?', [$new, $note ?: null, now(), $id]);
            if ($new === 'rejected') {
                Points::add((int) $r['user_id'], 'refund', (int) $r['cost_points'], false, 'redemption_refund', (int) $r['id'], 'Estorno: resgate recusado', (int) Auth::id());
                DB::run('UPDATE store_items SET stock = stock + 1 WHERE id = ?', [$r['item_id']]);
            }
        });
        Audit::log((int) Auth::id(), 'redemption_' . $new, 'redemption', $id, $note ?: null);
        flash('ok', match ($new) {
            'approved' => 'Resgate aprovado. Combine a entrega com o paciente.',
            'delivered' => 'Resgate marcado como entregue.',
            default => 'Resgate recusado. Os pontos voltaram para o paciente.',
        });
        redirect('/admin/resgates');
    }

    private function validated(string $backTo): array
    {
        $name = input('name');
        $cost = (int) ($_POST['cost_points'] ?? 0);
        $stock = (int) ($_POST['stock'] ?? 0);
        if ($name === '' || mb_strlen($name) > 100 || $cost < 1 || $cost > 1000000 || $stock < 0 || $stock > 100000) {
            keep_old();
            flash('err', 'Confira: nome, custo em pontos (1 ou mais) e estoque (0 ou mais).');
            redirect($backTo);
        }
        return [
            'name' => $name,
            'description' => mb_substr(input('description'), 0, 300) ?: null,
            'cost' => $cost,
            'stock' => $stock,
            'active' => !empty($_POST['active']) ? 1 : 0,
        ];
    }
}
