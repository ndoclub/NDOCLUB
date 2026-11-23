<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;
use App\Services\Settings;

final class SettingsController
{
    public function index(): void
    {
        $keys = ['whatsapp_number', 'whatsapp_message', 'birthday_message', 'lead_message', 'level_prata', 'level_ouro', 'level_black', 'warn_yellow_days', 'warn_red_days', 'community_rules', 'consent_version'];
        for ($i = 1; $i <= 7; $i++) {
            $keys[] = 'theme_' . $i;
            $keys[] = 'theme_' . $i . '_q';
        }
        $s = [];
        foreach ($keys as $k) {
            $s[$k] = (string) Settings::get($k, '');
        }
        View::render('admin/settings', ['title' => 'Configurações', 's' => $s], 'admin');
    }

    public function save(): void
    {
        $number = preg_replace('/\D+/', '', input('whatsapp_number'));
        $yellow = (int) ($_POST['warn_yellow_days'] ?? 15);
        $red = (int) ($_POST['warn_red_days'] ?? 7);
        $prata = (int) ($_POST['level_prata'] ?? 500);
        $ouro = (int) ($_POST['level_ouro'] ?? 1500);
        $black = (int) ($_POST['level_black'] ?? 4000);

        $errors = [];
        if (strlen($number) < 12 || strlen($number) > 13) {
            $errors[] = 'WhatsApp: use o formato 55 + DDD + número (ex.: 5511999998888).';
        }
        if ($red < 1 || $yellow <= $red || $yellow > 90) {
            $errors[] = 'Avisos de renovação: o amarelo precisa ser maior que o vermelho (ex.: 15 e 7).';
        }
        if ($prata < 1 || $ouro <= $prata || $black <= $ouro) {
            $errors[] = 'Níveis: Prata < Ouro < Black (ex.: 500, 1500, 4000).';
        }
        if ($errors) {
            keep_old();
            foreach ($errors as $e) {
                flash('err', $e);
            }
            redirect('/admin/config');
        }

        Settings::set('whatsapp_number', $number);
        Settings::set('whatsapp_message', mb_substr(input('whatsapp_message'), 0, 200));
        Settings::set('birthday_message', mb_substr(input('birthday_message'), 0, 300));
        Settings::set('lead_message', mb_substr(input('lead_message'), 0, 300));
        Settings::set('warn_yellow_days', (string) $yellow);
        Settings::set('warn_red_days', (string) $red);
        Settings::set('level_prata', (string) $prata);
        Settings::set('level_ouro', (string) $ouro);
        Settings::set('level_black', (string) $black);
        Settings::set('community_rules', mb_substr(input('community_rules'), 0, 1500));
        for ($i = 1; $i <= 7; $i++) {
            Settings::set('theme_' . $i, mb_substr(input('theme_' . $i), 0, 80));
            Settings::set('theme_' . $i . '_q', mb_substr(input('theme_' . $i . '_q'), 0, 160));
        }
        // Subir a versão do termo faz todos os pacientes aceitarem de novo no próximo acesso.
        if (!empty($_POST['bump_consent'])) {
            $v = (float) Settings::get('consent_version', '1.0') + 0.1;
            Settings::set('consent_version', number_format($v, 1, '.', ''));
        }
        Audit::log((int) Auth::id(), 'settings_update', 'settings');
        flash('ok', 'Configurações salvas.');
        redirect('/admin/config');
    }

    public function audit(): void
    {
        $rows = DB::all(
            'SELECT a.*, u.name AS admin_name FROM audit_log a LEFT JOIN users u ON u.id = a.admin_id ORDER BY a.created_at DESC, a.id DESC LIMIT 150'
        );
        View::render('admin/audit', ['title' => 'Auditoria', 'rows' => $rows], 'admin');
    }
}
