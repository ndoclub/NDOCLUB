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
        $keys = ['whatsapp_number', 'whatsapp_message', 'birthday_message', 'lead_message', 'lead_message_followup', 'lead_message_call', 'lead_message_proposal', 'lead_message_meeting', 'warn_yellow_days', 'warn_red_days'];
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

        $errors = [];
        if (strlen($number) < 12 || strlen($number) > 13) {
            $errors[] = 'WhatsApp: use o formato 55 + DDD + número (ex.: 5511999998888).';
        }
        if ($red < 1 || $yellow <= $red || $yellow > 90) {
            $errors[] = 'Avisos de renovação: o amarelo precisa ser maior que o vermelho (ex.: 15 e 7).';
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
        foreach (array_keys(\App\Services\Leads::ACTION_MESSAGES) as $type) {
            Settings::set(\App\Services\Leads::messageKey($type), mb_substr(input(\App\Services\Leads::messageKey($type)), 0, 300));
        }
        Settings::set('warn_yellow_days', (string) $yellow);
        Settings::set('warn_red_days', (string) $red);
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
