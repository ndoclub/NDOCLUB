<?php
namespace App\Core;

use App\Services\Settings;

/**
 * Regras de acesso aplicadas antes de abrir uma página.
 *  guest    -> só para quem NÃO está logado (tela de login)
 *  auth_any -> exige login (sem checar primeiro acesso)
 *  auth     -> exige login + senha trocada + consentimento aceito
 *  patient  -> só pacientes (admin é levado ao painel)
 *  admin    -> só administradores
 */
final class Middleware
{
    public static function run(string $name): void
    {
        switch ($name) {
            case 'guest':
                if (Auth::check()) {
                    redirect(Auth::isAdmin() ? '/admin' : '/home');
                }
                break;

            case 'auth_any':
                if (!Auth::check()) {
                    redirect('/login');
                }
                break;

            case 'auth':
                self::run('auth_any');
                $u = Auth::user();
                $needsConsent = $u['role'] === 'patient'
                    && (empty($u['consent_at']) || $u['consent_version'] !== Settings::get('consent_version', '1.0'));
                if ((int) $u['must_change_password'] === 1 || $needsConsent) {
                    redirect('/primeiro-acesso');
                }
                break;

            case 'patient':
                if (Auth::isAdmin()) {
                    redirect('/admin');
                }
                break;

            case 'admin':
                if (!Auth::isAdmin()) {
                    http_response_code(403);
                    View::render('errors/403', ['title' => 'Acesso negado'], 'app');
                    exit;
                }
                break;
        }
    }
}
