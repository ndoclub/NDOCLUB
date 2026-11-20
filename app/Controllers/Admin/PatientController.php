<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Upload;
use App\Core\View;
use App\Services\Athletes;
use App\Services\Audit;
use App\Services\Levels;
use App\Services\Plans;
use App\Services\Points;
use App\Services\Ranking;
use App\Services\Renewal;
use App\Services\Streak;

final class PatientController
{
    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $filter = (string) ($_GET['f'] ?? 'all');
        $sportId = (int) ($_GET['s'] ?? 0);

        $sql = "SELECT u.id, u.name, u.email, u.phone, u.avatar, u.status, u.renewal_date, u.last_seen_at,
                       pl.name AS plan_name, COALESCE(bl.price, pl.price) AS plan_value,
                       ap.is_athlete, ap.competes, ap.has_coach, ap.coach_name, ap.event_name, ap.event_date,
                       (SELECT COALESCE(SUM(l.points), 0) FROM points_ledger l WHERE l.user_id = u.id) AS balance
                  FROM users u
                  LEFT JOIN plans pl ON pl.id = u.plan_id
                  LEFT JOIN patient_billing bl ON bl.user_id = u.id
                  LEFT JOIN athlete_profiles ap ON ap.user_id = u.id
                 WHERE u.role = 'patient'";
        $params = [];
        if ($sportId > 0) {
            $sql .= ' AND ap.is_athlete = 1 AND EXISTS (SELECT 1 FROM user_sports us WHERE us.user_id = u.id AND us.sport_id = ?)';
            $params[] = $sportId;
        }
        if ($q !== '') {
            $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY u.name ASC';

        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 day'));
        $list = [];
        foreach (DB::all($sql, $params) as $r) {
            $r['renew'] = Renewal::info($r['renewal_date']);
            $r['away'] = empty($r['last_seen_at']) || $r['last_seen_at'] < $weekAgo;
            if ($filter === 'inactive' && $r['status'] !== 'inactive') {
                continue;
            }
            if (in_array($filter, ['green', 'yellow', 'red'], true) && ($r['status'] !== 'active' || $r['renew']['seal'] !== $filter)) {
                continue;
            }
            if ($filter === 'away' && ($r['status'] !== 'active' || !$r['away'])) {
                continue;
            }
            $isAthlete = (int) ($r['is_athlete'] ?? 0) === 1;
            if ($filter === 'athletes' && !$isAthlete) {
                continue;
            }
            if ($filter === 'compete' && !($isAthlete && (int) $r['competes'] === 1)) {
                continue;
            }
            if ($filter === 'nocoach' && !($isAthlete && (int) $r['has_coach'] === 0)) {
                continue;
            }
            $list[] = $r;
        }
        View::render('admin/patients/index', [
            'title' => 'Pacientes', 'list' => $list, 'q' => $q, 'filter' => $filter,
            'sportId' => $sportId, 'sports' => Athletes::sports(false),
            'sportNames' => Athletes::namesFor(array_column($list, 'id')),
        ], 'admin');
    }

    public function create(): void
    {
        View::render('admin/patients/create', [
            'title' => 'Novo paciente',
            'password' => temp_password(),
            'defaultRenewal' => date('Y-m-d', strtotime('+30 day')),
            'plans' => Plans::active(),
        ], 'admin');
    }

    public function store(): void
    {
        $name = input('name');
        $email = mb_strtolower(input('email'));
        $phone = input('phone');
        $goal = input('goal');
        $renewal = input('renewal_date');
        $password = input('password');
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $custom = parse_money((string) ($_POST['plan_price'] ?? ''));
        $plan = $planId ? DB::one('SELECT * FROM plans WHERE id = ? AND active = 1', [$planId]) : null;

        $errors = [];
        if ($planId && !$plan) {
            $errors[] = 'Plano inválido.';
        }
        if ($custom === false) {
            $errors[] = 'Valor combinado inválido. Use números, por exemplo 450,00.';
        }
        if (mb_strlen($name) < 2) {
            $errors[] = 'Informe o nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        } elseif (DB::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
            $errors[] = 'Já existe um usuário com este e-mail.';
        }
        if ($renewal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewal)) {
            $errors[] = 'Data de renovação inválida.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'A senha provisória precisa ter pelo menos 8 caracteres.';
        }
        if ($errors) {
            keep_old();
            foreach ($errors as $e) {
                flash('err', $e);
            }
            redirect('/admin/pacientes/novo');
        }

        if ($renewal === '' && $plan) {
            $renewal = date('Y-m-d', strtotime('+' . (int) $plan['duration_days'] . ' day'));
        }
        $id = DB::insert(
            "INSERT INTO users (role, name, email, password_hash, phone, goal, status, renewal_date, plan_id, must_change_password, created_at)
             VALUES ('patient', ?, ?, ?, ?, ?, 'active', ?, ?, 1, ?)",
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone ?: null, $goal ?: null, $renewal ?: null, $plan ? (int) $plan['id'] : null, now()]
        );
        if ($custom !== null) {
            Plans::setCustomPrice($id, $custom);
        }
        Audit::log((int) Auth::id(), 'patient_create', 'user', $id, $name);
        $_SESSION['_creds'] = ['name' => $name, 'email' => $email, 'password' => $password];
        flash('ok', 'Paciente criado. Envie o acesso pelo WhatsApp (mensagem pronta abaixo).');
        redirect('/admin/pacientes/' . $id);
    }

    public function show(int $id): void
    {
        $p = DB::one("SELECT * FROM users WHERE id = ? AND role = 'patient'", [$id]);
        if (!$p) {
            flash('err', 'Paciente não encontrado.');
            redirect('/admin/pacientes');
        }

        $creds = $_SESSION['_creds'] ?? null;
        unset($_SESSION['_creds']);
        $welcome = null;
        if ($creds) {
            $welcome = "Oi, " . first_name($creds['name']) . "! Seu acesso ao No Days Off Club está pronto.\n\n"
                . "Link: " . app_url() . "\nE-mail: " . $creds['email'] . "\nSenha provisória: " . $creds['password'] . "\n\n"
                . "No primeiro acesso você escolhe a sua senha. Bora!";
        }

        $life = Points::lifetime($id);
        $plansList = Plans::active();
        if (!empty($p['plan_id']) && !in_array((int) $p['plan_id'], array_map(fn($x) => (int) $x['id'], $plansList), true)) {
            $cur = DB::one('SELECT * FROM plans WHERE id = ?', [(int) $p['plan_id']]);
            if ($cur) {
                $plansList[] = $cur;
            }
        }
        $planRow = !empty($p['plan_id']) ? DB::one('SELECT * FROM plans WHERE id = ?', [(int) $p['plan_id']]) : null;
        View::render('admin/patients/show', [
            'plans' => $plansList,
            'planRow' => $planRow,
            'customPrice' => Plans::customPrice($id),
            'planPrice' => Plans::priceFor($id),
            'renewChoices' => Plans::renewalChoices(),
            'athlete' => Athletes::profile($id),
            'athleteSportIds' => Athletes::sportIds($id),
            'sportChoices' => Athletes::choicesFor($id),
            'title' => $p['name'],
            'p' => $p,
            'welcome' => $welcome,
            'renew' => Renewal::info($p['renewal_date']),
            'renewals' => DB::all(
                'SELECT r.*, pl.name AS plan_name FROM renewals r LEFT JOIN plans pl ON pl.id = r.plan_id
                  WHERE r.user_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 8',
                [$id]
            ),
            'balance' => Points::balance($id),
            'monthPts' => Points::monthTotal($id),
            'level' => Levels::forPoints($life),
            'rank' => Ranking::positionOf($id),
            'streak' => Streak::current($id),
            'ledger' => array_map(function ($r) {
                $r['label'] = Points::label($r['rule_key']);
                return $r;
            }, DB::all('SELECT * FROM points_ledger WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 12', [$id])),
            'rules' => DB::all('SELECT rkey, label, points FROM point_rules ORDER BY sort_order'),
            'diet' => DB::one('SELECT * FROM diets WHERE user_id = ?', [$id]),
            'workouts' => DB::all('SELECT id, title, weekday, active FROM workouts WHERE user_id = ? ORDER BY sort_order, id', [$id]),
            'measures' => DB::all('SELECT * FROM measurements WHERE user_id = ? ORDER BY measured_on DESC LIMIT 6', [$id]),
            'lastCheckin' => DB::val('SELECT MAX(checkin_date) FROM checkins WHERE user_id = ?', [$id]),
            'workoutsMonth' => (int) DB::val('SELECT COUNT(*) FROM workout_logs WHERE user_id = ? AND done_on >= ?', [$id, date('Y-m-01')]),
        ], 'admin');
    }

    public function update(int $id): void
    {
        $p = $this->find($id);
        $name = input('name');
        $email = mb_strtolower(input('email'));
        $phone = input('phone');
        $goal = input('goal');
        $status = input('status') === 'inactive' ? 'inactive' : 'active';
        $renewal = input('renewal_date');
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $custom = parse_money((string) ($_POST['plan_price'] ?? ''));

        $errors = [];
        if ($planId && !DB::one('SELECT id FROM plans WHERE id = ? AND (active = 1 OR id = ?)', [$planId, (int) ($p['plan_id'] ?? 0)])) {
            $errors[] = 'Plano inválido.';
        }
        if ($custom === false) {
            $errors[] = 'Valor combinado inválido. Use números, por exemplo 450,00.';
        }
        if (mb_strlen($name) < 2) {
            $errors[] = 'Informe o nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        } elseif (DB::val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $id])) {
            $errors[] = 'Este e-mail já é de outro usuário.';
        }
        if ($renewal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewal)) {
            $errors[] = 'Data de renovação inválida.';
        }
        if ($errors) {
            foreach ($errors as $e) {
                flash('err', $e);
            }
            redirect('/admin/pacientes/' . $id);
        }
        DB::run(
            'UPDATE users SET name = ?, email = ?, phone = ?, goal = ?, status = ?, renewal_date = ?, plan_id = ? WHERE id = ?',
            [$name, $email, $phone ?: null, $goal ?: null, $status, $renewal ?: null, $planId ?: null, $id]
        );
        Plans::setCustomPrice($id, $custom);
        Audit::log((int) Auth::id(), 'patient_update', 'user', $id, $p['name']);
        flash('ok', 'Dados salvos.');
        redirect('/admin/pacientes/' . $id);
    }

    public function renew(int $id): void
    {
        $p = $this->find($id);
        $days = (int) ($_POST['days'] ?? 0);
        if (!in_array($days, Plans::renewalChoices(), true)) {
            flash('err', 'Escolha um período de renovação.');
            redirect('/admin/pacientes/' . $id);
        }
        $amount = parse_money((string) ($_POST['amount'] ?? ''));
        if ($amount === false) {
            flash('err', 'Valor da renovação inválido. Use números, por exemplo 450,00.');
            redirect('/admin/pacientes/' . $id);
        }
        $new = Renewal::renew($id, $days, (int) Auth::id(), $amount, !empty($p['plan_id']) ? (int) $p['plan_id'] : null);
        Audit::log((int) Auth::id(), 'renewal', 'user', $id, '+' . $days . ' dias, até ' . $new . ($amount !== null ? ', ' . money($amount) : ''));
        flash('ok', 'Renovado até ' . fmt_date($new) . '. O bônus de pontos foi lançado.');
        redirect('/admin/pacientes/' . $id);
    }

    public function points(int $id): void
    {
        $p = $this->find($id);
        $type = input('type');
        $note = input('note');

        if ($type === 'referral') {
            $rule = Points::rule('referral');
            if (!$rule || (int) $rule['active'] !== 1 || (int) $rule['points'] <= 0) {
                flash('err', 'A regra de indicação está desativada.');
                redirect('/admin/pacientes/' . $id);
            }
            Points::add($id, 'referral', (int) $rule['points'], true, null, null, $note ?: 'Indicação que virou paciente', (int) Auth::id());
            Audit::log((int) Auth::id(), 'points_referral', 'user', $id, '+' . $rule['points']);
            flash('ok', '+' . $rule['points'] . ' pontos pela indicação.');
        } else {
            $points = (int) ($_POST['points'] ?? 0);
            if ($points === 0 || abs($points) > 5000 || $note === '') {
                flash('err', 'Informe os pontos (de -5000 a 5000, diferente de 0) e o motivo.');
                redirect('/admin/pacientes/' . $id);
            }
            $counts = !empty($_POST['counts_ranking']) && $points > 0;
            Points::add($id, 'manual', $points, $counts, null, null, $note, (int) Auth::id());
            Audit::log((int) Auth::id(), 'points_manual', 'user', $id, $points . ' pts: ' . $note);
            flash('ok', 'Ajuste lançado no extrato.');
        }
        redirect('/admin/pacientes/' . $id);
    }

    public function resetPassword(int $id): void
    {
        $p = $this->find($id);
        $pass = temp_password();
        DB::run('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
        DB::run('DELETE FROM login_attempts WHERE email = ?', [$p['email']]);
        Audit::log((int) Auth::id(), 'password_reset', 'user', $id, $p['name']);
        $_SESSION['_creds'] = ['name' => $p['name'], 'email' => $p['email'], 'password' => $pass];
        flash('ok', 'Nova senha provisória gerada. Envie pelo WhatsApp (mensagem pronta abaixo).');
        redirect('/admin/pacientes/' . $id);
    }

    public function diet(int $id): void
    {
        $this->find($id);
        $title = input('title') ?: 'Plano alimentar';
        $link = input('link_url');
        $notes = input('notes');

        if ($link !== '' && !preg_match('#^https?://#i', $link)) {
            flash('err', 'O link precisa começar com http:// ou https://');
            redirect('/admin/pacientes/' . $id);
        }
        $current = DB::one('SELECT * FROM diets WHERE user_id = ?', [$id]);
        $pdf = Upload::pdf($_FILES['pdf'] ?? null, $err);
        if ($err) {
            flash('err', $err);
            redirect('/admin/pacientes/' . $id);
        }
        $pdfFile = $current['pdf_file'] ?? null;
        if ($pdf) {
            Upload::delete($pdfFile, 'pdf');
            $pdfFile = $pdf;
        }
        if ($current) {
            DB::run('UPDATE diets SET title = ?, link_url = ?, pdf_file = ?, notes = ?, updated_at = ? WHERE user_id = ?', [$title, $link ?: null, $pdfFile, $notes ?: null, now(), $id]);
        } else {
            DB::run('INSERT INTO diets (user_id, title, link_url, pdf_file, notes, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, $title, $link ?: null, $pdfFile, $notes ?: null, now()]);
        }
        Audit::log((int) Auth::id(), 'diet_update', 'user', $id);
        flash('ok', 'Dieta atualizada.');
        redirect('/admin/pacientes/' . $id);
    }

    public function removeDietPdf(int $id): void
    {
        $this->find($id);
        $current = DB::one('SELECT pdf_file FROM diets WHERE user_id = ?', [$id]);
        if ($current && $current['pdf_file']) {
            Upload::delete($current['pdf_file'], 'pdf');
            DB::run('UPDATE diets SET pdf_file = NULL, updated_at = ? WHERE user_id = ?', [now(), $id]);
        }
        flash('ok', 'PDF removido.');
        redirect('/admin/pacientes/' . $id);
    }

    /** Perfil esportivo (atleta) preenchido pelo admin. A escolha de "mostrar no perfil" é do paciente e não muda aqui. */
    public function sport(int $id): void
    {
        $this->find($id);
        $parsed = Athletes::parse($id);
        if ($parsed['errors']) {
            foreach ($parsed['errors'] as $e) {
                flash('err', $e);
            }
            redirect('/admin/pacientes/' . $id);
        }
        Athletes::save($id, $parsed, null);
        $d = $parsed['data'];
        Audit::log((int) Auth::id(), 'athlete_update', 'user', $id, $d['is_athlete'] ? count($parsed['sports']) . ' modalidade(s)' . ($d['competes'] ? ', compete' : '') : 'não é atleta');
        flash('ok', 'Perfil esportivo salvo.');
        redirect('/admin/pacientes/' . $id);
    }

    /** Exclusão definitiva (direito de exclusão da LGPD): apaga o paciente e todos os dados dele. */
    public function delete(int $id): void
    {
        $p = $this->find($id);
        if (empty($_POST['confirm'])) {
            flash('err', 'Marque a confirmação para excluir.');
            redirect('/admin/pacientes/' . $id);
        }
        foreach (DB::all('SELECT image FROM posts WHERE user_id = ? AND image IS NOT NULL', [$id]) as $row) {
            Upload::delete($row['image']);
        }
        Upload::delete($p['avatar']);
        $d = DB::one('SELECT pdf_file FROM diets WHERE user_id = ?', [$id]);
        if ($d) {
            Upload::delete($d['pdf_file'], 'pdf');
        }
        DB::run("DELETE FROM reports WHERE target_type = 'post' AND target_id IN (SELECT id FROM posts WHERE user_id = ?)", [$id]);
        DB::run("DELETE FROM reports WHERE target_type = 'comment' AND target_id IN (SELECT id FROM comments WHERE user_id = ?)", [$id]);
        DB::run('DELETE FROM redemptions WHERE user_id = ?', [$id]);
        DB::run('DELETE FROM users WHERE id = ?', [$id]);
        Audit::log((int) Auth::id(), 'patient_delete', 'user', $id, $p['name']);
        flash('ok', 'Paciente e todos os dados dele foram excluídos.');
        redirect('/admin/pacientes');
    }

    private function find(int $id): array
    {
        $p = DB::one("SELECT * FROM users WHERE id = ? AND role = 'patient'", [$id]);
        if (!$p) {
            flash('err', 'Paciente não encontrado.');
            redirect('/admin/pacientes');
        }
        return $p;
    }
}
