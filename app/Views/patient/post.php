<?php partial('post', ['post' => $post, 'user' => $user, 'back' => current_path(), 'detail' => true]); ?>

<section class="comments" id="comentarios">
    <h2 class="section-title" style="padding-top:18px"><?= count($comments) ?> <?= count($comments) === 1 ? 'comentário' : 'comentários' ?></h2>
    <?php foreach ($comments as $c): ?>
        <div class="comment" id="c<?= (int) $c['id'] ?>">
            <a href="<?= e(url('/u/' . $c['user_id'])) ?>" aria-label="Perfil de <?= e($c['name']) ?>"><?= avatar($c, 36) ?></a>
            <div class="comment-body">
                <a class="post-name" href="<?= e(url('/u/' . $c['user_id'])) ?>"><?= e($c['name']) ?><?= $c['role'] === 'admin' ? ' <span class="tag">Equipe</span>' : '' ?></a>
                <span class="post-meta"><?= e(time_ago($c['created_at'])) ?></span>
                <p><?= text_html($c['body']) ?></p>
                <div class="row small" style="gap:16px">
                    <?php if ((int) $c['user_id'] !== (int) $user['id']): ?>
                        <details class="menu">
                            <summary class="link-btn muted" style="font-weight:600">Denunciar</summary>
                            <div class="menu-panel">
                                <form method="post" action="<?= e(url('/denunciar')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="type" value="comment">
                                    <input type="hidden" name="target_id" value="<?= (int) $c['id'] ?>">
                                    <input type="hidden" name="back" value="<?= e(current_path()) ?>">
                                    <select name="reason" required aria-label="Motivo da denúncia">
                                        <option value="">Escolha o motivo</option>
                                        <option>Desrespeito ou ofensa</option>
                                        <option>Conteúdo impróprio</option>
                                        <option>Spam ou venda</option>
                                        <option>Outro motivo</option>
                                    </select>
                                    <button class="btn btn-ghost btn-sm btn-block" type="submit">Enviar denúncia</button>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                    <?php if ((int) $c['user_id'] === (int) $user['id'] || $user['role'] === 'admin'): ?>
                        <form class="inline-form" method="post" action="<?= e(url('/comentario/' . $c['id'] . '/excluir')) ?>" data-confirm="Excluir este comentário?">
                            <?= csrf_field() ?><button class="link-btn danger" type="submit">Excluir</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$comments): ?><p class="muted">Seja o primeiro a comentar.</p><?php endif; ?>

    <form method="post" action="<?= e(url('/post/' . $post['id'] . '/comentar')) ?>" class="mt">
        <?= csrf_field() ?>
        <label class="sr-only" for="cbody">Escreva um comentário</label>
        <textarea class="input" id="cbody" name="body" maxlength="500" rows="3" placeholder="Escreva um comentário" required style="min-height:84px"></textarea>
        <div class="form-actions"><button class="btn btn-sm" type="submit">Comentar</button></div>
    </form>
</section>
