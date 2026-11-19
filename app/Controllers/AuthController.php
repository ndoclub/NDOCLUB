<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Settings;

final class AuthController
{
    public function root(): void
    {
        if (Auth::check()) {
            redirect(Auth::isAdmin() ? '/admin' : '/home');
        }
        redirect('/login');
    }

    public function loginForm(): void
    {
        View::render('auth/login', ['title' => 'Entrar'], 'guest');
    }

    public function login(): void
    {
        $email = mb_strtolower(input('email'));
        $password = (string) ($_POST['password'] ?? '');

        $user = Auth::attempt($email, $password, $error);
        if (!$user) {
            keep_old();
            flash('err', $error ?? 'Não foi possível entrar.');
            redirect('/login');
        }
        Auth::login($user);
        redirect($user['role'] === 'admin' ? '/admin' : '/home');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }

    public function terms(): void
    {
        $layout = Auth::check() ? (Auth::isAdmin() ? 'admin' : 'app') : 'guest';
        View::render('auth/terms', [
            'title' => 'Termos e privacidade',
            'rules' => (string) Settings::get('community_rules', ''),
            'version' => (string) Settings::get('consent_version', '1.0'),
        ], $layout);
    }

    public function firstAccessForm(): void
    {
        $u = Auth::user();
        View::render('auth/first_access', [
            'title' => 'Primeiro acesso',
            'needPassword' => (int) $u['must_change_password'] === 1,
            'needConsent' => $this->needsConsent($u),
            'version' => (string) Settings::get('consent_version', '1.0'),
        ], 'guest');
    }

    public function firstAccess(): void
    {
        $u = Auth::user();
        $needPassword = (int) $u['must_change_password'] === 1;
        $needConsent = $this->needsConsent($u);
        $errors = [];

        $newHash = null;
        if ($needPassword) {
            $p1 = (string) ($_POST['password'] ?? '');
            $p2 = (string) ($_POST['password_confirm'] ?? '');
            if (mb_strlen($p1) < 8) {
                $errors[] = 'A senha precisa ter pelo menos 8 caracteres.';
            } elseif ($p1 !== $p2) {
                $errors[] = 'As duas senhas não são iguais.';
            } elseif (password_verify($p1, $u['password_hash'])) {
                $errors[] = 'Escolha uma senha diferente da provisória.';
            } else {
                $newHash = password_hash($p1, PASSWORD_DEFAULT);
            }
        }
        if ($needConsent && empty($_POST['accept'])) {
            $errors[] = 'Para continuar, aceite o termo de consentimento.';
        }
        if ($errors) {
            foreach ($errors as $err) {
                flash('err', $err);
            }
            redirect('/primeiro-acesso');
        }

        if ($newHash !== null) {
            DB::run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [$newHash, $u['id']]);
        }
        if ($needConsent) {
            DB::run('UPDATE users SET consent_at = ?, consent_version = ? WHERE id = ?', [now(), Settings::get('consent_version', '1.0'), $u['id']]);
        }
        Auth::refresh();
        flash('ok', 'Tudo certo. Bem-vindo ao ' . app_name() . '!');
        redirect($u['role'] === 'admin' ? '/admin' : '/home');
    }

    private function needsConsent(array $u): bool
    {
        return $u['role'] === 'patient'
            && (empty($u['consent_at']) || $u['consent_version'] !== Settings::get('consent_version', '1.0'));
    }
}
