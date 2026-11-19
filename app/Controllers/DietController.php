<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;

final class DietController
{
    public function index(): void
    {
        $diet = DB::one('SELECT * FROM diets WHERE user_id = ?', [Auth::id()]);
        View::render('patient/diet', ['title' => 'Dieta', 'diet' => $diet]);
    }
}
