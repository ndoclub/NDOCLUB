<?php
/**
 * Porta de entrada do sistema. Todas as páginas passam por aqui.
 */

// Servidor embutido do PHP (apenas para testes): entrega arquivos estáticos direto.
if (PHP_SAPI === 'cli-server') {
    $static = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($static) && !preg_match('/\.php$/', $static)) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';

use App\Core\Router;

$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = substr($uri, strlen(BASE_PATH));
$path = preg_replace('#^/index\.php#', '', (string) $path);
$path = '/' . trim((string) $path, '/');

$router = new Router();
require ROOT . '/app/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
