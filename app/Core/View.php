<?php
namespace App\Core;

/**
 * Renderiza uma tela (app/Views/...) dentro de um layout
 * (app = paciente, admin = painel, guest = login).
 */
final class View
{
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $user = Auth::user();
        extract($data, EXTR_SKIP);

        ob_start();
        require ROOT . '/app/Views/' . $view . '.php';
        $content = ob_get_clean();

        $title = $title ?? app_name();
        require ROOT . '/app/Views/layouts/' . $layout . '.php';
    }
}
