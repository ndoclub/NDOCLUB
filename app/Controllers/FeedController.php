<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Upload;
use App\Core\View;
use App\Services\Points;
use App\Services\Settings;

final class FeedController
{
    private const POST_SQL = "SELECT p.*, u.name, u.avatar, u.role,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.status = 'visible') AS comments,
            (SELECT COUNT(*) FROM post_likes pl2 WHERE pl2.post_id = p.id AND pl2.user_id = ?) AS liked
        FROM posts p JOIN users u ON u.id = p.user_id";

    public function index(): void
    {
        $me = (int) Auth::id();
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $per = 12;

        $pinned = $page === 1
            ? DB::all(self::POST_SQL . " WHERE p.status = 'visible' AND p.is_pinned = 1 ORDER BY p.created_at DESC, p.id DESC LIMIT 3", [$me])
            : [];
        $posts = DB::all(
            self::POST_SQL . " WHERE p.status = 'visible' AND p.is_pinned = 0 ORDER BY p.created_at DESC, p.id DESC LIMIT " . ($per + 1) . ' OFFSET ' . (($page - 1) * $per),
            [$me]
        );
        $hasMore = count($posts) > $per;
        $posts = array_slice($posts, 0, $per);

        $iso = (int) date('N');
        View::render('patient/feed', [
            'title' => 'Comunidade',
            'pinned' => $pinned,
            'posts' => $posts,
            'page' => $page,
            'hasMore' => $hasMore,
            'theme' => [
                'title' => (string) Settings::get('theme_' . $iso, ''),
                'question' => (string) Settings::get('theme_' . $iso . '_q', ''),
            ],
            'rules' => (string) Settings::get('community_rules', ''),
        ], Auth::isAdmin() ? 'admin' : 'app');
    }

    public function store(): void
    {
        $u = Auth::user();
        $body = input('body');
        if ($body === '' || mb_strlen($body) > 1000) {
            keep_old();
            flash('err', 'Escreva algo (até 1000 caracteres) para publicar.');
            redirect('/feed');
        }
        $image = Upload::image($_FILES['image'] ?? null, 1280, $err);
        if ($err) {
            keep_old();
            flash('err', $err);
            redirect('/feed');
        }

        $theme = null;
        if (!empty($_POST['with_theme'])) {
            $theme = Settings::get('theme_' . date('N'), null);
        }

        $postId = DB::insert(
            "INSERT INTO posts (user_id, body, image, theme, is_pinned, status, created_at) VALUES (?, ?, ?, ?, 0, 'visible', ?)",
            [$u['id'], $body, $image, $theme, now()]
        );

        $earned = $u['role'] === 'patient' ? Points::award((int) $u['id'], 'post', 'post', $postId, 'Publicação no feed') : 0;
        flash('ok', 'Publicado.' . ($earned > 0 ? ' +' . $earned . ' pontos.' : ''));
        redirect('/feed');
    }

    public function show(int $id): void
    {
        $me = (int) Auth::id();
        $post = DB::one(self::POST_SQL . " WHERE p.id = ? AND p.status = 'visible'", [$me, $id]);
        if (!$post) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Publicação não encontrada'], Auth::isAdmin() ? 'admin' : 'app');
            return;
        }
        $comments = DB::all(
            "SELECT c.*, u.name, u.avatar, u.role FROM comments c JOIN users u ON u.id = c.user_id
              WHERE c.post_id = ? AND c.status = 'visible' ORDER BY c.created_at ASC, c.id ASC",
            [$id]
        );
        View::render('patient/post', ['title' => 'Publicação', 'post' => $post, 'comments' => $comments], Auth::isAdmin() ? 'admin' : 'app');
    }

    public function like(int $id): void
    {
        $u = Auth::user();
        $post = DB::one("SELECT id, user_id FROM posts WHERE id = ? AND status = 'visible'", [$id]);
        if (!$post) {
            is_ajax() ? json_out(['ok' => false], 404) : redirect('/feed');
        }
        $has = DB::val('SELECT COUNT(*) FROM post_likes WHERE post_id = ? AND user_id = ?', [$id, $u['id']]);
        $earned = 0;
        if ($has) {
            DB::run('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?', [$id, $u['id']]);
        } else {
            try {
                DB::run('INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, ?)', [$id, $u['id'], now()]);
                // Só paga ponto por curtir post de outra pessoa
                if ($u['role'] === 'patient' && (int) $post['user_id'] !== (int) $u['id']) {
                    $earned = Points::award((int) $u['id'], 'like', 'post', $id, 'Curtida');
                }
            } catch (\PDOException $e) {
                // curtida duplicada: ignora
            }
        }
        $count = (int) DB::val('SELECT COUNT(*) FROM post_likes WHERE post_id = ?', [$id]);

        if (is_ajax()) {
            json_out(['ok' => true, 'liked' => !$has, 'count' => $count, 'earned' => $earned]);
        }
        redirect(safe_back($_POST['back'] ?? null, '/feed') . '#p' . $id);
    }

    public function comment(int $id): void
    {
        $u = Auth::user();
        $post = DB::one("SELECT id FROM posts WHERE id = ? AND status = 'visible'", [$id]);
        $body = input('body');
        if (!$post) {
            redirect('/feed');
        }
        if ($body === '' || mb_strlen($body) > 500) {
            flash('err', 'Escreva um comentário de até 500 caracteres.');
            redirect('/post/' . $id);
        }
        $cid = DB::insert("INSERT INTO comments (post_id, user_id, body, status, created_at) VALUES (?, ?, ?, 'visible', ?)", [$id, $u['id'], $body, now()]);
        $earned = $u['role'] === 'patient' ? Points::award((int) $u['id'], 'comment', 'comment', $cid, 'Comentário') : 0;
        flash('ok', 'Comentário publicado.' . ($earned > 0 ? ' +' . $earned . ' pontos.' : ''));
        redirect('/post/' . $id . '#comentarios');
    }

    public function deletePost(int $id): void
    {
        $u = Auth::user();
        $post = DB::one('SELECT id, user_id, image FROM posts WHERE id = ?', [$id]);
        if (!$post || ((int) $post['user_id'] !== (int) $u['id'] && $u['role'] !== 'admin')) {
            flash('err', 'Você não pode excluir esta publicação.');
            redirect('/feed');
        }
        DB::run("UPDATE posts SET status = 'removed', is_pinned = 0 WHERE id = ?", [$id]);
        Points::reverseFor('post', $id, 'post', $u['role'] === 'admin' ? (int) $u['id'] : null);
        flash('ok', 'Publicação excluída.');
        redirect(safe_back($_POST['back'] ?? null, '/feed'));
    }

    public function deleteComment(int $id): void
    {
        $u = Auth::user();
        $c = DB::one('SELECT id, user_id, post_id FROM comments WHERE id = ?', [$id]);
        if (!$c || ((int) $c['user_id'] !== (int) $u['id'] && $u['role'] !== 'admin')) {
            flash('err', 'Você não pode excluir este comentário.');
            redirect('/feed');
        }
        DB::run("UPDATE comments SET status = 'removed' WHERE id = ?", [$id]);
        Points::reverseFor('comment', $id, 'comment', $u['role'] === 'admin' ? (int) $u['id'] : null);
        flash('ok', 'Comentário excluído.');
        redirect('/post/' . $c['post_id'] . '#comentarios');
    }

    public function report(): void
    {
        $u = Auth::user();
        $type = input('type');
        $targetId = (int) ($_POST['target_id'] ?? 0);
        $reason = input('reason');
        $extra = input('extra');
        $back = safe_back($_POST['back'] ?? null, '/feed');

        if (!in_array($type, ['post', 'comment'], true) || $targetId < 1 || $reason === '') {
            flash('err', 'Escolha o motivo da denúncia.');
            redirect($back);
        }
        $table = $type === 'post' ? 'posts' : 'comments';
        $target = DB::one("SELECT id, user_id FROM $table WHERE id = ? AND status = 'visible'", [$targetId]);
        if (!$target) {
            flash('err', 'Conteúdo não encontrado.');
            redirect($back);
        }
        if ((int) $target['user_id'] === (int) $u['id']) {
            flash('err', 'Você não pode denunciar o seu próprio conteúdo.');
            redirect($back);
        }
        $text = mb_substr($reason . ($extra !== '' ? ': ' . $extra : ''), 0, 200);
        try {
            DB::run("INSERT INTO reports (reporter_id, target_type, target_id, reason, status, created_at) VALUES (?, ?, ?, ?, 'open', ?)", [$u['id'], $type, $targetId, $text, now()]);
            flash('ok', 'Denúncia enviada. O Gabriel vai analisar.');
        } catch (\PDOException $e) {
            flash('ok', 'Você já denunciou este conteúdo. Ele está em análise.');
        }
        redirect($back);
    }
}
