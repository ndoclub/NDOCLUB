<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Points;

final class StoreController
{
    public function index(): void
    {
        $id = (int) Auth::id();
        $items = DB::all('SELECT * FROM store_items WHERE active = 1 ORDER BY cost_points ASC, id ASC');
        $mine = DB::all(
            'SELECT r.*, i.name, i.image FROM redemptions r JOIN store_items i ON i.id = r.item_id
              WHERE r.user_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 20',
            [$id]
        );
        View::render('patient/store', [
            'title' => 'Loja',
            'items' => $items,
            'mine' => $mine,
            'balance' => Points::balance($id),
        ]);
    }

    public function redeem(int $itemId): void
    {
        $id = (int) Auth::id();
        $result = DB::tx(function () use ($id, $itemId) {
            $item = DB::one('SELECT * FROM store_items WHERE id = ? AND active = 1', [$itemId]);
            if (!$item) {
                return ['err', 'Este brinde não está mais disponível.'];
            }
            if ((int) $item['stock'] < 1) {
                return ['err', 'Este brinde está esgotado.'];
            }
            $cost = (int) $item['cost_points'];
            if (Points::balance($id) < $cost) {
                return ['err', 'Você ainda não tem pontos suficientes para este brinde.'];
            }
            // Baixa o estoque só se ainda houver (evita vender além do estoque)
            if (DB::run('UPDATE store_items SET stock = stock - 1 WHERE id = ? AND stock > 0', [$itemId]) !== 1) {
                return ['err', 'Este brinde acabou de esgotar.'];
            }
            $rid = DB::insert(
                "INSERT INTO redemptions (user_id, item_id, cost_points, status, created_at) VALUES (?, ?, ?, 'pending', ?)",
                [$id, $itemId, $cost, now()]
            );
            Points::add($id, 'redeem', -$cost, false, 'redemption', $rid, 'Resgate: ' . $item['name'], null);
            return ['ok', 'Resgate pedido: ' . $item['name'] . '. O Gabriel vai confirmar e combinar a entrega.'];
        });
        flash($result[0], $result[1]);
        redirect('/loja');
    }
}
