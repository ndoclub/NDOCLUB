<?php
namespace App\Core;

/**
 * Login, sessão e usuário atual.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        $u = DB::one('SELECT * FROM users WHERE id = ?', [(int) $id]);
        // Paciente importado por planilha ainda sem acesso liberado (app_access = 0) não entra
        if (!$u || $u['status'] !== 'active' || (int) ($u['app_access'] ?? 1) === 0) {
            unset($_SESSION['uid']);
            return null;
        }
        // "Visto por último" (atualiza no máximo a cada 5 minutos)
        if (empty($u['last_seen_at']) || strtotime($u['last_seen_at']) < time() - 300) {
            DB::run('UPDATE users SET last_seen_at = ? WHERE id = ?', [now(), $u['id']]);
        }
        return self::$user = $u;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && $u['role'] === 'admin';
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        self::$user = null;
        self::$loaded = false;
    }

    public static function refresh(): void
    {
        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
        self::$loaded = false;
    }

    /**
     * Valida e-mail e senha, com limite de tentativas.
     * Devolve o usuário ou null (e preenche $error).
     */
    public static function attempt(string $email, string $password, ?string &$error = null): ?array
    {
        $ip = substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
        $since = date('Y-m-d H:i:s', time() - 15 * 60);

        $byEmail = (int) DB::val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at >= ?', [$email, $since]);
        $byIp = (int) DB::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at >= ?', [$ip, $since]);
        if ($byEmail >= 5 || $byIp >= 20) {
            $error = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
            return null;
        }

        $user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
        // Sempre confere uma senha (mesmo sem usuário) para não revelar se o e-mail existe.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsaltthatdoesnotexistxxxxxxxxxxxxxxxx';
        $ok = password_verify($password, $hash);

        if (!$user || !$ok) {
            DB::run('INSERT INTO login_attempts (email, ip, created_at) VALUES (?, ?, ?)', [$email, $ip, now()]);
            $error = 'E-mail ou senha incorretos.';
            return null;
        }
        if ($user['status'] !== 'active') {
            $error = 'Seu acesso está desativado. Fale com o Gabriel.';
            return null;
        }
        if ((int) ($user['app_access'] ?? 1) === 0) {
            $error = 'Seu acesso ao app ainda não foi liberado. Fale com o Gabriel.';
            return null;
        }

        DB::run('DELETE FROM login_attempts WHERE email = ?', [$email]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        return $user;
    }
}
