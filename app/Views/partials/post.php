<?php
/* Uma publicação. Variáveis: $post, $user, $back (caminho atual) */
$mine = (int) $post['user_id'] === (int) $user['id'];
$canDelete = $mine || $user['role'] === 'admin';
$pinned = !empty($post['is_pinned']);
$pid = (int) $post['id'];
?>
<article class="post<?= $pinned ? ' pinned' : '' ?>" id="p<?= $pid ?>">
    <?php if ($pinned): ?><span class="pin-tag" style="margin-bottom:12px">Mural do Gabriel</span><?php endif; ?>
    <header class="post-head">
        <a href="<?= e(url('/u/' . $post['user_id'])) ?>" aria-label="Perfil de <?= e($post['name']) ?>"><?= avatar($post, 42) ?></a>
        <div>
            <a class="post-name" href="<?= e(url('/u/' . $post['user_id'])) ?>"><?= e($post['name']) ?><?= $post['role'] === 'admin' ? ' <span class="tag">Equipe</span>' : '' ?></a>
            <span class="post-meta"><?= e(time_ago($post['created_at'])) ?></span>
        </div>
    </header>
    <?php if (!empty($post['theme'])): ?><span class="post-theme"><?= e($post['theme']) ?></span><?php endif; ?>
    <div class="post-body"><?= text_html($post['body']) ?></div>
    <?php if (!empty($post['image'])): ?>
        <img class="post-img" src="<?= e(url('/media/img/' . $post['image'])) ?>" alt="Foto enviada por <?= e($post['name']) ?>" loading="lazy">
    <?php endif; ?>

    <div class="post-actions">
        <form class="inline-form" method="post" action="<?= e(url('/post/' . $pid . '/curtir')) ?>" data-like>
            <?= csrf_field() ?>
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <button class="act<?= !empty($post['liked']) ? ' on' : '' ?>" type="submit" aria-pressed="<?= !empty($post['liked']) ? 'true' : 'false' ?>">
                <?= icon('heart', 22) ?><span data-count><?= (int) $post['likes'] ?></span><span class="sr-only">curtidas</span>
            </button>
        </form>
        <a class="act" href="<?= e(url('/post/' . $pid . '#comentarios')) ?>"><?= icon('chat', 22) ?><?= (int) $post['comments'] ?><span class="sr-only">comentários</span></a>
        <span class="act-spacer"></span>
        <details class="menu">
            <summary class="act" aria-label="Mais opções">Mais</summary>
            <div class="menu-panel">
                <?php if (!$mine): ?>
                    <form method="post" action="<?= e(url('/denunciar')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="post">
                        <input type="hidden" name="target_id" value="<?= $pid ?>">
                        <input type="hidden" name="back" value="<?= e($back) ?>">
                        <label class="label" for="r<?= $pid ?>">Denunciar publicação</label>
                        <select id="r<?= $pid ?>" name="reason" required>
                            <option value="">Escolha o motivo</option>
                            <option>Desrespeito ou ofensa</option>
                            <option>Conteúdo impróprio</option>
                            <option>Spam ou venda</option>
                            <option>Informação de saúde perigosa</option>
                            <option>Outro motivo</option>
                        </select>
                        <input class="input" type="text" name="extra" maxlength="120" placeholder="Detalhe (opcional)">
                        <button class="btn btn-ghost btn-sm btn-block" type="submit"><?= icon('flag', 18) ?> Enviar denúncia</button>
                    </form>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                    <form method="post" action="<?= e(url('/post/' . $pid . '/excluir')) ?>" data-confirm="Excluir esta publicação?" <?= !$mine ? 'style="margin-top:12px"' : '' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="back" value="<?= e($detail ?? false ? '/feed' : $back) ?>">
                        <button class="link-btn danger" type="submit">Excluir publicação</button>
                    </form>
                <?php endif; ?>
            </div>
        </details>
    </div>
</article>
