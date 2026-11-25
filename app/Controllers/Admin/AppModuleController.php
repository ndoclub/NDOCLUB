<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Services\Audit;
use App\Services\AppModule;
use App\Services\Settings;

/**
 * Módulo "App do paciente" no painel (Atualização 09.5): a página Visão geral e os ajustes do app
 * (níveis, tema do dia, regras de convivência e termo). Treinos, Ranking, Loja, Resgates, Regras de pontos
 * e Moderação continuam nas páginas de sempre; o menu só as agrupa.
 */
final class AppModuleController
{
    public function index(): void
    {
        View::render('admin/app/index', ['title' => 'App do paciente', 'o' => AppModule::overview()], 'admin');
    }

    public function settings(): void
    {
        $keys = ['level_prata', 'level_ouro', 'level_black', 'community_rules', 'consent_version'];
        for ($i = 1; $i <= 7; $i++) {
            $keys[] = 'theme_' . $i;
            $keys[] = 'theme_' . $i . '_q';
        }
        $s = [];
        foreach ($keys as $k) {
            $s[$k] = (string) Settings::get($k, '');
        }
        View::render('admin/app/settings', ['title' => 'Ajustes do app', 's' => $s], 'admin');
    }

    public function saveSettings(): void
    {
        $prata = (int) ($_POST['level_prata'] ?? 500);
        $ouro = (int) ($_POST['level_ouro'] ?? 1500);
        $black = (int) ($_POST['level_black'] ?? 4000);
        if ($prata < 1 || $ouro <= $prata || $black <= $ouro) {
            keep_old();
            flash('err', 'Níveis: Prata < Ouro < Black (ex.: 500, 1500, 4000).');
            redirect('/admin/app/ajustes');
        }

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
        Audit::log((int) Auth::id(), 'settings_update', 'settings', null, 'Ajustes do app');
        flash('ok', 'Ajustes do app salvos.');
        redirect('/admin/app/ajustes');
    }
}
