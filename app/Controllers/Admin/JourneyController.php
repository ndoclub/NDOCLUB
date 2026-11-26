<?php
namespace App\Controllers\Admin;

use App\Controllers\Admin\PatientController;
use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;
use App\Services\Journey;
use App\Services\Payments;
use App\Services\Settings;

/**
 * Jornada do paciente (Atualização 11, parte 1: Renovação e Saída).
 * Página com 3 abas (Tarefas, Renovações, Vencidos), ajustes e as ações da aba "Jornada" da ficha.
 * O sistema só lembra e registra: nunca manda mensagem.
 */
final class JourneyController
{
    public const ABAS = ['tarefas' => 'Tarefas', 'renovacoes' => 'Renovações', 'vencidos' => 'Vencidos'];

    /** Mostra o resultado de uma ação (['ok' => ...] ou ['err' => ...]) como aviso. */
    private function say(array $r): void
    {
        if (isset($r['err'])) {
            flash('err', $r['err']);
        } else {
            flash('ok', $r['ok'] ?? 'Feito.');
        }
    }

    private function patientOr404(int $id): array
    {
        $p = DB::one("SELECT id, name FROM users WHERE id = ? AND role = 'patient'", [$id]);
        if (!$p) {
            flash('err', 'Paciente não encontrado.');
            redirect('/admin/pacientes');
        }
        return $p;
    }

    // ---------------------------------------------------------------- página

    public function index(): void
    {
        try {
            Journey::sync(false);
            $aba = is_string($_GET['aba'] ?? null) ? $_GET['aba'] : 'tarefas';
            if (!isset(self::ABAS[$aba])) {
                $aba = 'tarefas';
            }
            $who = is_string($_GET['r'] ?? null) ? trim($_GET['r']) : '';
            $byWho = Journey::pendingByWho();
            if ($who !== '' && $who !== '__none' && !isset($byWho[$who])) {
                $who = '';
            }
            $tasks = Journey::pending($who);
            $t = today();
            $week = Journey::addDays($t, 7);
            $groups = ['late' => [], 'today' => [], 'week' => [], 'later' => []];
            foreach ($tasks as $row) {
                $d = substr((string) $row['due_on'], 0, 10);
                $k = $d < $t ? 'late' : ($d === $t ? 'today' : ($d <= $week ? 'week' : 'later'));
                $groups[$k][] = $row;
            }
            $renewals = Journey::renewals();
            $expired = Journey::expired();
            View::render('admin/journey/index', [
                'title' => 'Jornada do paciente', 'aba' => $aba, 'who' => $who, 'byWho' => $byWho, 'groups' => $groups,
                'total' => count($tasks), 'renewals' => $renewals, 'expired' => $expired, 'cfg' => Journey::cfg(),
                'attn' => Journey::attention(), 'exitReasons' => Journey::EXIT_REASONS, 'back' => current_path(),
            ], 'admin');
        } catch (\PDOException $e) {
            View::render('admin/journey/missing', ['title' => 'Jornada do paciente'], 'admin');
        }
    }

    // ---------------------------------------------------------------- tarefas

    public function complete(int $id): void
    {
        $back = safe_back((string) ($_POST['back'] ?? ''), '/admin/jornada');
        $r = Journey::complete($id, (int) Auth::id(), input('note'));
        $this->say($r);
        redirect($back);
    }

    public function reopen(int $id): void
    {
        $back = safe_back((string) ($_POST['back'] ?? ''), '/admin/jornada');
        $r = Journey::reopen($id, (int) Auth::id());
        $this->say($r);
        redirect($back);
    }

    // ---------------------------------------------------------------- ajustes

    public function settings(): void
    {
        View::render('admin/journey/settings', ['title' => 'Ajustes da Jornada', 'cfg' => Journey::cfg()], 'admin');
    }

    public function saveSettings(): void
    {
        $errors = [];
        $int = function (string $k, int $min, int $max, string $label) use (&$errors): int {
            $raw = trim((string) ($_POST[$k] ?? ''));
            if ($raw === '' || !ctype_digit($raw) || (int) $raw < $min || (int) $raw > $max) {
                $errors[] = $label . ': use um número de ' . $min . ' a ' . $max . '.';
                return 0;
            }
            return (int) $raw;
        };
        $start = input('start');
        if (!Payments::validDate($start)) {
            $errors[] = 'Início da Jornada: informe uma data válida.';
        }
        $window = $int('window', 15, 365, 'Janela da aba Renovações');
        $dDossie = $int('d_dossie', 0, 60, 'Dossiê pronto antes da última consulta');
        $dContato = $int('d_contato', 0, 90, 'Contato de renovação antes do prazo');
        $dPausa = $int('d_pausa', 1, 365, 'Recontato da pausa');
        $rMin = $int('raise_min', 0, 100, 'Reajuste mínimo de referência');
        $rMax = $int('raise_max', 0, 100, 'Reajuste máximo de referência');
        if (!$errors && $rMax < $rMin) {
            $errors[] = 'O reajuste máximo precisa ser maior ou igual ao mínimo.';
        }
        $who = [];
        foreach (['dossie' => 'Dossiê', 'ultima' => 'Última consulta', 'contato' => 'Contatos', 'suporte' => 'Suporte'] as $k => $label) {
            $v = mb_substr(input('who_' . $k), 0, 40);
            if (mb_strlen($v) < 2) {
                $errors[] = 'Quem faz — ' . $label . ': informe o nome (ou "Suporte").';
            }
            $who[$k] = $v;
        }
        if ($errors) {
            keep_old();
            foreach ($errors as $e) {
                flash('err', $e);
            }
            redirect('/admin/jornada/ajustes');
        }
        Settings::set('journey_start_on', $start);
        Settings::set('journey_window', (string) $window);
        Settings::set('journey_days_dossie', (string) $dDossie);
        Settings::set('journey_days_contact', (string) $dContato);
        Settings::set('journey_days_pause', (string) $dPausa);
        Settings::set('journey_raise_min', (string) $rMin);
        Settings::set('journey_raise_max', (string) $rMax);
        Settings::set('journey_who_dossie', $who['dossie']);
        Settings::set('journey_who_ultima', $who['ultima']);
        Settings::set('journey_who_contact', $who['contato']);
        Settings::set('journey_who_support', $who['suporte']);
        Journey::sync(true); // prazos e responsáveis novos valem já para as tarefas abertas
        Audit::log((int) Auth::id(), 'settings_update', 'settings', null, 'Ajustes da Jornada');
        flash('ok', 'Ajustes da Jornada salvos.');
        redirect('/admin/jornada/ajustes');
    }

    // ---------------------------------------------------------------- ações da ficha (aba Jornada)

    public function lastConsult(int $id): void
    {
        $this->patientOr404($id);
        $this->say(Journey::setLastConsult($id, input('last_consult_on'), (int) Auth::id()));
        redirect(PatientController::ficha($id, 'jornada', 'ultima-consulta'));
    }

    public function answer(int $id): void
    {
        $this->patientOr404($id);
        $r = Journey::recordAnswer($id, input('answer'), input('agreed_on'), input('next_consult_on'), (int) Auth::id());
        if (isset($r['ok'])) {
            Audit::log((int) Auth::id(), 'journey_answer', 'user', $id, input('answer'));
        }
        $this->say($r);
        redirect(PatientController::ficha($id, 'jornada', 'resposta'));
    }

    public function inactivate(int $id): void
    {
        $p = $this->patientOr404($id);
        $r = Journey::inactivate($id, input('reason'), input('note'), (int) Auth::id());
        if (isset($r['ok'])) {
            Audit::log((int) Auth::id(), 'journey_exit', 'user', $id, $p['name'] . ': ' . input('reason'));
        }
        $this->say($r);
        $back = safe_back((string) ($_POST['back'] ?? ''), '');
        redirect($back !== '' && isset($r['ok']) ? $back : PatientController::ficha($id, 'jornada', 'saida'));
    }

    public function reactivate(int $id): void
    {
        $p = $this->patientOr404($id);
        $r = Journey::reactivate($id, (int) Auth::id());
        if (isset($r['ok'])) {
            Audit::log((int) Auth::id(), 'journey_return', 'user', $id, $p['name']);
        }
        $this->say($r);
        redirect(PatientController::ficha($id, 'jornada', 'saida'));
    }

    public function note(int $id): void
    {
        $this->patientOr404($id);
        $this->say(Journey::addNote($id, input('text'), (int) Auth::id()));
        redirect(PatientController::ficha($id, 'jornada', 'linha-do-tempo'));
    }
}
