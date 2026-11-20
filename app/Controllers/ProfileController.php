<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Upload;
use App\Core\View;
use App\Services\Athletes;
use App\Services\Levels;
use App\Services\Plans;
use App\Services\Points;
use App\Services\Ranking;
use App\Services\Renewal;
use App\Services\Streak;

final class ProfileController
{
    /** Meu perfil (com dados privados). */
    public function me(): void
    {
        $u = Auth::user();
        $this->render($u, true);
    }

    /** Perfil de outro membro (só dados públicos). */
    public function show(int $id): void
    {
        if ($id === (int) Auth::id()) {
            redirect(Auth::isAdmin() ? '/admin' : '/perfil');
        }
        $u = DB::one("SELECT * FROM users WHERE id = ? AND status = 'active'", [$id]);
        if (!$u) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Perfil não encontrado'], Auth::isAdmin() ? 'admin' : 'app');
            return;
        }
        $this->render($u, false);
    }

    private function render(array $u, bool $isMe): void
    {
        $id = (int) $u['id'];
        $isPatient = $u['role'] === 'patient';

        $posts = DB::all(
            "SELECT p.*, 
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.status = 'visible') AS comments
               FROM posts p WHERE p.user_id = ? AND p.status = 'visible' ORDER BY p.created_at DESC, p.id DESC LIMIT 12",
            [$id]
        );

        $data = [
            'title' => $isMe ? 'Meu perfil' : $u['name'],
            'p' => $u,
            'isMe' => $isMe,
            'isPatient' => $isPatient,
            'posts' => $posts,
            'level' => $isPatient ? Levels::forPoints(Points::lifetime($id)) : null,
            'streak' => $isPatient ? Streak::current($id) : 0,
            'rank' => $isPatient ? Ranking::positionOf($id) : null,
            'since' => $u['created_at'],
            // modalidades: o dono vê as dele sempre; os outros só veem se ele escolheu mostrar
            'sportNames' => $isPatient ? (Athletes::namesFor([$id], !$isMe)[$id] ?? []) : [],
        ];
        if ($isMe && $isPatient) {
            $data['renew'] = Renewal::info($u['renewal_date']);
            $data['planName'] = !empty($u['plan_id']) ? Plans::name((int) $u['plan_id']) : null;
            $data['whatsapp'] = Renewal::whatsappUrl($u);
            $data['balance'] = Points::balance($id);
            $data['athlete'] = Athletes::profile($id);
        }
        View::render('patient/profile', $data, Auth::isAdmin() ? 'admin' : 'app');
    }

    /** Meu perfil esportivo (o paciente edita o dele). */
    public function sportForm(): void
    {
        $id = (int) Auth::id();
        View::render('patient/profile_sport', [
            'title' => 'Perfil esportivo',
            'athlete' => Athletes::profile($id),
            'choices' => Athletes::choicesFor($id),
            'selected' => Athletes::sportIds($id),
        ]);
    }

    public function sportSave(): void
    {
        $id = (int) Auth::id();
        $parsed = Athletes::parse($id);
        if ($parsed['errors']) {
            foreach ($parsed['errors'] as $e) {
                flash('err', $e);
            }
            redirect('/perfil/esporte');
        }
        Athletes::save($id, $parsed, !empty($_POST['show_public']) ? 1 : 0);
        flash('ok', 'Perfil esportivo salvo.');
        redirect('/perfil');
    }

    public function editForm(): void
    {
        View::render('patient/profile_edit', ['title' => 'Editar perfil', 'p' => Auth::user()]);
    }

    public function edit(): void
    {
        $u = Auth::user();
        $name = input('name');
        $bio = input('bio');
        $goal = input('goal');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            keep_old();
            flash('err', 'Informe seu nome (2 a 120 letras).');
            redirect('/perfil/editar');
        }
        if (mb_strlen($bio) > 280 || mb_strlen($goal) > 120) {
            keep_old();
            flash('err', 'Bio até 280 caracteres e objetivo até 120.');
            redirect('/perfil/editar');
        }

        $avatar = $u['avatar'];
        $new = Upload::image($_FILES['avatar'] ?? null, 480, $err);
        if ($err) {
            keep_old();
            flash('err', $err);
            redirect('/perfil/editar');
        }
        if ($new) {
            Upload::delete($u['avatar']);
            $avatar = $new;
        }

        DB::run('UPDATE users SET name = ?, bio = ?, goal = ?, avatar = ? WHERE id = ?', [$name, $bio ?: null, $goal ?: null, $avatar, $u['id']]);
        flash('ok', 'Perfil atualizado.');
        redirect('/perfil');
    }

    public function removePhoto(): void
    {
        $u = Auth::user();
        Upload::delete($u['avatar']);
        DB::run('UPDATE users SET avatar = NULL WHERE id = ?', [$u['id']]);
        flash('ok', 'Foto removida.');
        redirect('/perfil/editar');
    }

    public function password(): void
    {
        $u = Auth::user();
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (!password_verify($current, $u['password_hash'])) {
            flash('err', 'A senha atual está incorreta.');
        } elseif (mb_strlen($new) < 8) {
            flash('err', 'A nova senha precisa ter pelo menos 8 caracteres.');
        } elseif ($new !== $confirm) {
            flash('err', 'As duas senhas novas não são iguais.');
        } else {
            DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            flash('ok', 'Senha alterada.');
        }
        redirect('/perfil/editar');
    }
}
