<?php
/**
 * Funções auxiliares usadas em todo o sistema.
 */

function config(string $path, $default = null)
{
    $value = $GLOBALS['NDO_CONFIG'] ?? [];
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Escapa texto para HTML (use SEMPRE ao imprimir dados). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return BASE_PATH . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function redirect(string $path): void
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function redirect_back(string $fallback = '/home'): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref !== '' && $host !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function app_name(): string
{
    return (string) config('app.name', 'No Days Off Club');
}

// ---------- Segurança (CSRF) ----------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['_csrf'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

// ---------- Mensagens e formulários ----------
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $message];
}

function flashes(): array
{
    $list = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $list;
}

/** Guarda o que foi digitado para repopular o formulário após um erro. */
function keep_old(): void
{
    $copy = $_POST;
    unset($copy['_csrf'], $copy['password'], $copy['password_confirm'], $copy['current_password'], $copy['new_password']);
    $_SESSION['_old'] = $copy;
}

function old(string $key, $default = ''): string
{
    $old = $GLOBALS['NDO_OLD'] ?? [];
    return isset($old[$key]) && is_string($old[$key]) ? $old[$key] : (string) $default;
}

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------- Datas e números ----------
function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $a = new DateTimeImmutable('today');
    $b = new DateTimeImmutable(substr($date, 0, 10));
    $diff = $a->diff($b);
    return $diff->invert ? -$diff->days : $diff->days;
}

function fmt_date(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $t = strtotime($date);
    return $t ? date('d/m/Y', $t) : '-';
}

function fmt_datetime(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $t = strtotime($date);
    return $t ? date('d/m/Y H:i', $t) : '-';
}

function month_name(int $m): string
{
    $names = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return $names[$m] ?? '';
}

function weekday_name(int $isoDay): string
{
    $names = [1 => 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
    return $names[$isoDay] ?? '';
}

function time_ago(?string $datetime): string
{
    $t = $datetime ? strtotime($datetime) : false;
    if (!$t) {
        return '';
    }
    $diff = time() - $t;
    if ($diff < 60) {
        return 'agora';
    }
    if ($diff < 3600) {
        return 'há ' . floor($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        return 'há ' . floor($diff / 3600) . ' h';
    }
    if ($diff < 172800) {
        return 'ontem';
    }
    if ($diff < 604800) {
        return 'há ' . floor($diff / 86400) . ' dias';
    }
    return date('d/m/Y', $t);
}

/** Formata pontos: 1250 -> "1.250" */
function pts($n): string
{
    return number_format((int) $n, 0, ',', '.');
}

function initials(string $name): string
{
    $name = trim(preg_replace('/\([^)]*\)/u', '', $name));
    $parts = preg_split('/\s+/', $name) ?: [];
    $first = mb_substr($parts[0] ?? '?', 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function first_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return $parts[0] ?? $name;
}

/** Avatar: foto ou iniciais. */
function avatar(array $u, int $size = 40): string
{
    $s = max(16, $size);
    if (!empty($u['avatar'])) {
        return '<img class="avatar" src="' . e(url('/media/img/' . $u['avatar'])) . '" alt="" width="' . $s . '" height="' . $s . '" loading="lazy">';
    }
    return '<span class="avatar avatar-ini" style="width:' . $s . 'px;height:' . $s . 'px;font-size:' . round($s * 0.38) . 'px" aria-hidden="true">'
        . e(initials((string) ($u['name'] ?? '?'))) . '</span>';
}

/** Inclui um pedaço de HTML reutilizável (app/Views/partials/...). */
function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require ROOT . '/app/Views/partials/' . $name . '.php';
}

/** Texto com quebras de linha e links desativados (seguro). */
function text_html(string $text): string
{
    return nl2br(e($text), false);
}

/** Caminho atual relativo ao sistema (ex.: /feed?p=2). */
function current_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $query = parse_url($uri, PHP_URL_QUERY);
    $path = substr($path, strlen(BASE_PATH)) ?: '/';
    return $path . ($query ? '?' . $query : '');
}

/** Aceita só caminhos internos (evita redirecionar para sites de fora). */
function safe_back(?string $path, string $fallback = '/home'): string
{
    if (is_string($path) && $path !== '' && $path[0] === '/' && !str_starts_with($path, '//') && !str_contains($path, '\\') && !preg_match('/[\r\n]/', $path)) {
        return $path;
    }
    return $fallback;
}

function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Converte "72,5" ou "72.5" em número decimal (ou null se inválido). */
function parse_decimal(string $s): ?float
{
    $s = str_replace(',', '.', trim($s));
    return is_numeric($s) ? (float) $s : null;
}

/**
 * Moedas aceitas nos valores (só no painel admin): sigla => [símbolo, nome].
 * O padrão é o real (BRL).
 */
function currencies(): array
{
    return [
        'BRL' => ['R$', 'Real (R$)'],
        'USD' => ['US$', 'Dólar (US$)'],
        'EUR' => ['€', 'Euro (€)'],
    ];
}

/** Sigla válida (BRL, USD, EUR). Qualquer outra coisa vira BRL. */
function currency_or_brl(?string $cur): string
{
    $cur = strtoupper(trim((string) $cur));
    return isset(currencies()[$cur]) ? $cur : 'BRL';
}

/** Formata um valor na moeda: money(1234.5) => "R$ 1.234,50"; money(900, 2, 'USD') => "US$ 900,00". */
function money(?float $v, int $dec = 2, string $cur = 'BRL'): string
{
    if ($v === null) {
        return '-';
    }
    $sym = currencies()[currency_or_brl($cur)][0];
    return $sym . ' ' . number_format($v, $dec, ',', '.');
}

/** Vários totais de moedas diferentes numa linha: ['BRL' => 100, 'USD' => 50] => "R$ 100,00 · US$ 50,00". */
function money_multi(array $byCurrency, string $empty = '-'): string
{
    $parts = [];
    foreach (array_keys(currencies()) as $cur) {
        if (!empty($byCurrency[$cur])) {
            $parts[] = money((float) $byCurrency[$cur], 2, $cur);
        }
    }
    return $parts ? implode(' · ', $parts) : $empty;
}

/**
 * Lê um valor digitado ("300", "300,50", "1.234,50", "R$ 300", "US$ 900", "€ 50").
 * Retorna null se estiver vazio e false se for inválido (negativo, texto ou acima de 99.999,99).
 */
function parse_money(string $s): float|false|null
{
    $s = trim(str_ireplace(['US$', 'R$', '€', 'USD', 'EUR', 'BRL', ' '], '', $s));
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    if (!is_numeric($s) || (float) $s < 0 || (float) $s > 99999.99) {
        return false;
    }
    return round((float) $s, 2);
}

function temp_password(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < 8; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

/** Endereço do sistema (para montar mensagens de boas-vindas). */
function app_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https' : 'http') . '://' . $host . BASE_PATH;
}

/** Link de WhatsApp para um telefone brasileiro (adiciona 55 se faltar). */
function wa_phone_url(?string $phone, string $message = ''): ?string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if (strlen($digits) < 10) {
        return null;
    }
    if (strlen($digits) <= 11) {
        $digits = '55' . $digits;
    }
    return 'https://wa.me/' . $digits . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

/** Ícones de linha (SVG inline), no estilo do app. */
function icon(string $name, int $size = 24): string
{
    static $paths = [
        'home'     => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'chat'     => '<path d="M4 5h16v11H9l-5 4z"/>',
        'dumbbell' => '<path d="M3 9v6M6 7v10M18 7v10M21 9v6M6 12h12"/>',
        'trophy'   => '<path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3"/><path d="M12 14v4M8 20h8"/>',
        'bag'      => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c.8-3.5 3.3-5 7-5s6.2 1.5 7 5"/><path d="M16 5a3.5 3.5 0 0 1 0 7M18 15c2 .5 3.4 2 4 5"/>',
        'heart'    => '<path d="M12 20s-7-4.5-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.5-9 9-9 9z"/>',
        'flag'     => '<path d="M5 21V4M5 5h12l-2 4 2 4H5"/>',
        'apple'    => '<path d="M12 7c-3-2-8 0-8 6 0 4 3 8 5 8 1 0 2-.7 3-.7s2 .7 3 .7c2 0 5-4 5-8 0-6-5-8-8-6z"/><path d="M12 7c0-2 1-4 3-4"/>',
        'chart'    => '<path d="M4 20V4M4 20h16M8 15l4-5 3 3 5-7"/>',
        'list'     => '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        'check'    => '<path d="M5 12l5 5 9-10"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'logout'   => '<path d="M10 4H5v16h5M15 8l4 4-4 4M19 12H9"/>',
        'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/>',
        'shield'   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'star'     => '<path d="M12 3l2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8 6.6 19.7l1.1-6.1L3.2 9.4l6.1-.8z"/>',
        'file'     => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
        'grid'     => '<path d="M4 4h7v9H4zM13 4h7v5h-7zM13 11h7v9h-7zM4 15h7v5H4z"/>',
        'camera'   => '<path d="M4 8h4l2-3h4l2 3h4v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'x'        => '<path d="M6 6l12 12M18 6L6 18"/>',
        'pin'      => '<path d="M9 4h6l-1 6 3 3H7l3-3zM12 13v8"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
        'doc'      => '<path d="M6 3h9l4 4v14H6z"/><path d="M9 13h6M9 17h6"/>',
        'tag'      => '<path d="M4 4h8l8 8-8 8-8-8z"/><circle cx="8.5" cy="8.5" r="1"/>',
        'card'     => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'cash'     => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6.5 9.5h.01M17.5 14.5h.01"/>',
        'left'     => '<path d="M15 5l-7 7 7 7"/>',
        'right'    => '<path d="M9 5l7 7-7 7"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="icon icon-' . e($name) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}
