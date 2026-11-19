<div class="page-head"><h2>Moderação</h2></div>

<form class="panel" method="post" action="<?= e(url('/admin/mural')) ?>" enctype="multipart/form-data" style="max-width:720px">
    <?= csrf_field() ?>
    <h3>Mural do Gabriel (aviso fixado no topo do feed)</h3>
    <div class="field"><label class="sr-only" for="body">Aviso</label>
        <textarea class="input" id="body" name="body" maxlength="1000" placeholder="Escreva o aviso, o desafio do mês ou um recado para a comunidade." required><?= e(old('body')) ?></textarea></div>
    <div class="field"><label for="image">Imagem (opcional)</label><input class="input" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"></div>
    <button class="btn btn-sm" type="submit">Publicar e fixar</button>
</form>

<h3 class="sub">Denúncias abertas (<?= count($reports) ?>)</h3>
<?php if (!$reports): ?>
    <div class="empty"><strong>Nenhuma denúncia pendente</strong>Tudo tranquilo na comunidade.</div>
<?php endif; ?>
<?php foreach ($reports as $r): $t = $r['target']; ?>
    <div class="report">
        <div class="row-between wrap">
            <span><strong><?= $r['target_type'] === 'post' ? 'Publicação' : 'Comentário' ?></strong> de
                <?php if ($t): ?><a href="<?= e(url('/u/' . $t['uid'])) ?>"><?= e($t['name']) ?></a><?php else: ?>autor removido<?php endif; ?></span>
            <span class="small muted">Denunciado por <?= e($r['reporter_name']) ?> - <?= e(time_ago($r['created_at'])) ?></span>
        </div>
        <p class="small" style="margin:8px 0 0"><span class="tag warn"><?= e($r['reason']) ?></span></p>
        <?php if ($t): ?>
            <blockquote><?= e($t['body']) ?></blockquote>
            <?php if (!empty($t['image'])): ?><img class="thumb" src="<?= e(url('/media/img/' . $t['image'])) ?>" alt="Imagem da publicação denunciada"><?php endif; ?>
            <?php if ($t['status'] === 'removed'): ?><p class="small muted">Este conteúdo já foi removido.</p><?php endif; ?>
        <?php endif; ?>
        <form class="form-actions" method="post" action="<?= e(url('/admin/denuncias/' . $r['id'] . '/resolver')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-danger btn-ghost" type="submit" name="action" value="remove">Remover conteúdo</button>
            <button class="btn btn-sm btn-ghost" type="submit" name="action" value="keep">Manter e encerrar</button>
            <?php if ($t && $r['target_type'] === 'post'): ?><a class="small" href="<?= e(url('/post/' . $t['id'])) ?>">Ver no feed</a>
            <?php elseif ($t): ?><a class="small" href="<?= e(url('/post/' . $t['post_id'] . '#c' . $t['id'])) ?>">Ver no feed</a><?php endif; ?>
        </form>
    </div>
<?php endforeach; ?>

<h3 class="sub">Publicações recentes</h3>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Autor</th><th>Publicação</th><th>Quando</th><th class="r">Ações</th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
        <tr>
            <td><strong><?= e($p['name']) ?></strong><?= $p['role'] === 'admin' ? ' <span class="tag">Equipe</span>' : '' ?></td>
            <td style="max-width:420px"><?= $p['is_pinned'] ? '<span class="tag ok">fixado</span> ' : '' ?><a href="<?= e(url('/post/' . $p['id'])) ?>"><?= e(mb_strlen($p['body']) > 100 ? mb_substr($p['body'], 0, 100) . '...' : $p['body']) ?></a></td>
            <td class="small muted"><?= e(time_ago($p['created_at'])) ?></td>
            <td class="r" style="white-space:nowrap">
                <form class="inline-form" method="post" action="<?= e(url('/admin/posts/' . $p['id'] . '/fixar')) ?>"><?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= $p['is_pinned'] ? 'Desafixar' : 'Fixar' ?></button></form>
                <form class="inline-form" method="post" action="<?= e(url('/admin/posts/' . $p['id'] . '/remover')) ?>" data-confirm="Remover esta publicação? Os pontos dela também são retirados."><?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm btn-danger" type="submit">Remover</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
