<?php
/**
 * Inicialização: autoload, configuração, sessão e tratamento de erros.
 */
define('ROOT', dirname(__DIR__));

// Autoload simples: App\Core\DB  ->  app/Core/DB.php
spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// (Opcional) a variável de ambiente NDO_CONFIG_FILE permite guardar o config fora da pasta pública.
$configFile = getenv('NDO_CONFIG_FILE') ?: ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo 'Arquivo config/config.php n&atilde;o encontrado. Copie config/config.sample.php para config/config.php.';
    exit;
}
$GLOBALS['NDO_CONFIG'] = require $configFile;

require ROOT . '/app/helpers.php';

date_default_timezone_set((string) config('app.timezone', 'America/Sao_Paulo'));
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');

// Caminho base: funciona em /nodaysoffclub/ (XAMPP) ou na raiz do domínio.
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (basename($script) === 'index.php') {
    $scriptDir = rtrim(dirname($script), '/');
} else {
    // Plano B: compara a pasta do sistema com a pasta raiz do servidor web.
    $doc = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $root = str_replace('\\', '/', ROOT);
    $scriptDir = ($doc !== '' && stripos($root, $doc) === 0) ? substr($root, strlen($doc)) : '';
}
define('BASE_PATH', ($scriptDir === '.' || $scriptDir === '/') ? '' : $scriptDir);

// Erros: nunca mostrar detalhes técnicos fora do modo debug.
set_exception_handler(function (Throwable $e): void {
    @file_put_contents(
        ROOT . '/storage/logs/app.log',
        '[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine() . "\n",
        FILE_APPEND
    );
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<body style="font-family:system-ui;background:#000;color:#fff;padding:24px;line-height:1.5">'
        . '<h1 style="font-size:20px">Algo deu errado</h1>';
    if (config('app.debug')) {
        echo '<p>' . e($e->getMessage()) . '</p><pre style="white-space:pre-wrap;color:#bbb">'
            . e($e->getFile() . ':' . $e->getLine()) . '</pre>';
    } else {
        echo '<p>Tente novamente em instantes. Se o erro continuar, avise o suporte.</p>';
    }
    echo '</body>';
    exit;
});

// Sessão: cookie só HTTP, SameSite, 30 dias (o paciente fica logado no celular).
$lifetime = 60 * 60 * 24 * 30;
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string) $lifetime);
session_name('ndo_sess');
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path'     => BASE_PATH === '' ? '/' : BASE_PATH,
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Dados antigos do formulário (para repopular após erro de validação)
$GLOBALS['NDO_OLD'] = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

// Cabeçalhos de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; manifest-src 'self'; worker-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
