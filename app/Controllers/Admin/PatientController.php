<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Upload;
use App\Core\View;
use App\Services\Audit;
use App\Services\Levels;
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

        $sql = "SELECT u.id, u.name, u.email, u.phone, u.avatar, u.status, u.renewal_date, u.last_seen_at,
                       (SELECT COALESCE(SUM(l.points), 0) FROM points_ledger l WHERE l.user_id = u.id) AS balance
                  FROM users u WHERE u.role = 'patient'";
        $params = [];
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
            $list[] = $r;
        }
        View::render('admin/patients/index', ['title' => 'Pacientes', 'list' => $list, 'q' => $q, 'filter' => $filter], 'admin');
    }

    public function create(): void
    {
        View::render('admin/patients/create', [
            'title' => 'Novo paciente',
            'password' => temp_password(),
            'defaultRenewal' => date('Y-m-d', strtotime('+30 day')),
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

        $errors = [];
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

        $id = DB::insert(
            "INSERT INTO users (role, name, email, password_hash, phone, goal, status, renewal_date, must_change_password, created_at)
             VALUES ('patient', ?, ?, ?, ?, ?, 'active', ?, 1, ?)",
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone ?: null, $goal ?: null, $renewal ?: null, now()]
        );
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
        View::render('admin/patients/show', [
            'title' => $p['name'],
            'p' => $p,
            'welcome' => $welcome,
            'renew' => Renewal::info($p['renewal_date']),
            'renewals' => DB::all('SELECT * FROM renewals WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 8', [$id]),
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

        $errors = [];
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
            'UPDATE users SET name = ?, email = ?, phone = ?, goal = ?, status = ?, renewal_date = ? WHERE id = ?',
            [$name, $email, $phone ?: null, $goal ?: null, $status, $renewal ?: null, $id]
        );
        Audit::log((int) Auth::id(), 'patient_update', 'user', $id, $p['name']);
        flash('ok', 'Dados salvos.');
        redirect('/admin/pacientes/' . $id);
    }

    public function renew(int $id): void
    {
        $p = $this->find($id);
        $days = (int) ($_POST['days'] ?? 0);
        if (!in_array($days, [30, 60, 90, 180, 365], true)) {
            flash('err', 'Escolha um período de renovação.');
            redirect('/admin/pacientes/' . $id);
        }
        $new = Renewal::renew($id, $days, (int) Auth::id());
        Audit::log((int) Auth::id(), 'renewal', 'user', $id, '+' . $days . ' dias, até ' . $new);
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
