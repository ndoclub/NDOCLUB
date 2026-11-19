<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Points;

final class LedgerController
{
    public function index(): void
    {
        $id = (int) Auth::id();
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $per = 30;
        $rows = DB::all(
            'SELECT * FROM points_ledger WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . ($per + 1) . ' OFFSET ' . (($page - 1) * $per),
            [$id]
        );
        $hasMore = count($rows) > $per;
        $rows = array_slice($rows, 0, $per);
        foreach ($rows as &$r) {
            $r['label'] = Points::label($r['rule_key']);
        }
        unset($r);

        View::render('patient/ledger', [
            'title' => 'Extrato de pontos',
            'rows' => $rows,
            'page' => $page,
            'hasMore' => $hasMore,
            'balance' => Points::balance($id),
            'monthPts' => Points::monthTotal($id),
        ]);
    }
}
