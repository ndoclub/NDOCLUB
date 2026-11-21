<?php
namespace App\Controllers\Admin;

use App\Core\View;
use App\Services\Birthdays;

/** Aniversariantes (só admin). */
final class BirthdayController
{
    public function index(): void
    {
        $key = Birthdays::windowKey($_GET['q'] ?? null);
        [$from, $to] = Birthdays::range($key);
        $rows = Birthdays::between($from, $to);
        foreach ($rows as &$r) {
            $r['wa'] = Birthdays::whatsappUrl($r['phone'] ?? null, (string) $r['name']);
        }
        unset($r);
        View::render('admin/birthdays', [
            'title' => 'Aniversários',
            'key' => $key,
            'windows' => Birthdays::windows(),
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'missing' => Birthdays::missingCount(),
            'sample' => Birthdays::message('Maria Exemplo'),
        ], 'admin');
    }
}
