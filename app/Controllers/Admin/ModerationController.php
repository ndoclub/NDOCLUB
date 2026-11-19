<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Upload;
use App\Core\View;
use App\Services\Audit;
use App\Services\Points;

final class ModerationController
{
    public function index(): void
    {
        $reports = DB::all(
            "SELECT r.*, ru.name AS reporter_name FROM reports r JOIN users ru ON ru.id = r.reporter_id
              WHERE r.status = 'open' ORDER BY r.created_at ASC, r.id ASC"
        );
        foreach ($reports as &$r) {
            if ($r['target_type'] === 'post') {
                $t = DB::one('SELECT p.id, p.body, p.image, p.status, u.name, u.id AS uid FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?', [$r['target_id']]);
            } else {
                $t = DB::one('SELECT c.id, c.body, NULL AS image, c.status, c.post_id, u.name, u.id AS uid FROM comments c JOIN users u ON u.id = c.user_id WHERE c.id = ?', [$r['target_id']]);
            }
            $r['target'] = $t;
        }
        unset($r);

        $posts = DB::all(
            "SELECT p.*, u.name, u.role FROM posts p JOIN users u ON u.id = p.user_id
              WHERE p.status = 'visible' ORDER BY p.is_pinned DESC, p.created_at DESC, p.id DESC LIMIT 25"
        );
        View::render('admin/moderation', ['title' => 'Moderação', 'reports' => $reports, 'posts' => $posts], 'admin');
    }

    /** Mural de avisos do Gabriel: post fixado no topo do feed. */
    public function announce(): void
    {
        $body = input('body');
        if ($body === '' || mb_strlen($body) > 1000) {
            keep_old();
            flash('err', 'Escreva o aviso (até 1000 caracteres).');
            redirect('/admin/moderacao');
        }
        $image = Upload::image($_FILES['image'] ?? null, 1280, $err);
        if ($err) {
            keep_old();
            flash('err', $err);
            redirect('/admin/moderacao');
        }
        $id = DB::insert(
            "INSERT INTO posts (user_id, body, image, theme, is_pinned, status, created_at) VALUES (?, ?, ?, NULL, 1, 'visible', ?)",
            [Auth::id(), $body, $image, now()]
        );
        Audit::log((int) Auth::id(), 'announce', 'post', $id);
        flash('ok', 'Aviso publicado e fixado no topo do feed.');
        redirect('/admin/moderacao');
    }

    public function removePost(int $id): void
    {
        $this->removePostById($id);
        flash('ok', 'Publicação removida.');
        redirect('/admin/moderacao');
    }

    public function pin(int $id): void
    {
        $p = DB::one("SELECT id, is_pinned FROM posts WHERE id = ? AND status = 'visible'", [$id]);
        if ($p) {
            DB::run('UPDATE posts SET is_pinned = ? WHERE id = ?', [$p['is_pinned'] ? 0 : 1, $id]);
            flash('ok', $p['is_pinned'] ? 'Publicação desafixada.' : 'Publicação fixada no topo do feed.');
        }
        redirect('/admin/moderacao');
    }

    public function removeComment(int $id): void
    {
        $this->removeCommentById($id);
        flash('ok', 'Comentário removido.');
        redirect('/admin/moderacao');
    }

    /** Resolve uma denúncia: remover o conteúdo ou manter. */
    public function resolve(int $id): void
    {
        $r = DB::one("SELECT * FROM reports WHERE id = ? AND status = 'open'", [$id]);
        if (!$r) {
            redirect('/admin/moderacao');
        }
        $action = input('action');
        if ($action === 'remove') {
            $r['target_type'] === 'post' ? $this->removePostById((int) $r['target_id']) : $this->removeCommentById((int) $r['target_id']);
        }
        DB::run(
            "UPDATE reports SET status = 'resolved', resolved_by = ? WHERE target_type = ? AND target_id = ? AND status = 'open'",
            [Auth::id(), $r['target_type'], $r['target_id']]
        );
        Audit::log((int) Auth::id(), 'report_' . ($action === 'remove' ? 'remove' : 'keep'), $r['target_type'], (int) $r['target_id']);
        flash('ok', $action === 'remove' ? 'Conteúdo removido e denúncia encerrada.' : 'Denúncia encerrada. O conteúdo foi mantido.');
        redirect('/admin/moderacao');
    }

    private function removePostById(int $id): void
    {
        DB::run("UPDATE posts SET status = 'removed', is_pinned = 0 WHERE id = ?", [$id]);
        Points::reverseFor('post', $id, 'post', (int) Auth::id());
        Audit::log((int) Auth::id(), 'post_remove', 'post', $id);
    }

    private function removeCommentById(int $id): void
    {
        DB::run("UPDATE comments SET status = 'removed' WHERE id = ?", [$id]);
        Points::reverseFor('comment', $id, 'comment', (int) Auth::id());
        Audit::log((int) Auth::id(), 'comment_remove', 'comment', $id);
    }
}
