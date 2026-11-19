<?php $withTheme = isset($_GET['tema']); ?>
<form class="composer" id="novo" method="post" action="<?= e(url('/feed/postar')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label class="sr-only" for="body">Escreva uma publicação</label>
    <textarea class="input" id="body" name="body" maxlength="1000" required
        placeholder="<?= e($theme['question'] !== '' ? $theme['question'] : 'Compartilhe um treino, uma refeição ou uma conquista.') ?>"><?= e(old('body')) ?></textarea>
    <div class="field" style="margin:10px 0 0">
        <label class="sr-only" for="image">Foto (opcional)</label>
        <input class="input" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
    </div>
    <div class="composer-tools">
        <?php if ($theme['title'] !== ''): ?>
            <label class="checkbox small"><input type="checkbox" name="with_theme" value="1"<?= $withTheme ? ' checked' : '' ?>><span>Tema de hoje: <?= e($theme['title']) ?></span></label>
        <?php else: ?><span></span><?php endif; ?>
        <button class="btn btn-sm" type="submit">Publicar</button>
    </div>
</form>

<?php if ($theme['title'] !== ''): ?>
<div class="theme-box">
    <strong><?= e($theme['title']) ?></strong>
    <span class="muted small"><?= e($theme['question']) ?></span>
    <?php if ($rules !== ''): ?>
        <details class="mt">
            <summary class="small" style="cursor:pointer;font-weight:700">Regras de convivência</summary>
            <p class="small muted" style="margin-top:8px"><?= text_html($rules) ?></p>
        </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php foreach ($pinned as $p): partial('post', ['post' => $p, 'user' => $user, 'back' => current_path()]); endforeach; ?>
<?php foreach ($posts as $p): partial('post', ['post' => $p, 'user' => $user, 'back' => current_path()]); endforeach; ?>

<?php if (!$posts && !$pinned): ?>
    <div class="empty"><strong>Ainda não há publicações</strong>Seja o primeiro a postar na comunidade.</div>
<?php endif; ?>

<?php if ($page > 1 || $hasMore): ?>
    <div class="pager">
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/feed?p=' . ($page - 1))) ?>">Mais recentes</a><?php else: ?><span></span><?php endif; ?>
        <?php if ($hasMore): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/feed?p=' . ($page + 1))) ?>">Mais antigas</a><?php endif; ?>
    </div>
<?php endif; ?>
