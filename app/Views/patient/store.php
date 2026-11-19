<section class="balance">
    <strong class="num"><?= pts($balance) ?></strong>
    <span class="muted">pontos para trocar</span>
</section>

<div class="list">
<?php foreach ($items as $it):
    $stock = (int) $it['stock']; $cost = (int) $it['cost_points']; $missing = $cost - $balance; ?>
    <article class="item">
        <?php if ($it['image']): ?>
            <img class="item-img" src="<?= e(url('/media/img/' . $it['image'])) ?>" alt="" loading="lazy">
        <?php else: ?>
            <div class="item-img"><img src="<?= e(asset('img/logo-nd.svg')) ?>" alt=""></div>
        <?php endif; ?>
        <div class="item-info">
            <div class="item-name"><?= e($it['name']) ?></div>
            <?php if ($it['description']): ?><div class="small muted"><?= e($it['description']) ?></div><?php endif; ?>
            <div class="row-between" style="margin-top:8px">
                <span class="cost num"><?= pts($cost) ?> pts</span>
                <span class="small muted"><?= $stock > 0 ? ($stock <= 5 ? 'Restam ' . $stock : 'Em estoque') : 'Esgotado' ?></span>
            </div>
            <?php if ($stock < 1): ?>
                <p class="small muted" style="margin:10px 0 0">Esgotado por enquanto.</p>
            <?php elseif ($missing > 0): ?>
                <p class="small muted" style="margin:10px 0 0">Faltam <?= pts($missing) ?> pontos.</p>
            <?php else: ?>
                <form method="post" action="<?= e(url('/loja/' . $it['id'] . '/resgatar')) ?>" data-confirm="Resgatar <?= e($it['name']) ?> por <?= pts($cost) ?> pontos?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm" type="submit">Resgatar</button>
                </form>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php if (!$items): ?>
    <div class="empty"><strong>A loja ainda está vazia</strong>Em breve, brindes da comunidade por aqui.</div>
<?php endif; ?>

<?php if ($mine): ?>
<section class="section" style="padding-bottom:6px">
    <h2 class="section-title">Meus resgates</h2>
</section>
<div class="list">
    <?php
    $labels = [
        'pending'   => ['Aguardando aprovação', ''],
        'approved'  => ['Aprovado, combine a entrega', 'ok'],
        'delivered' => ['Entregue', 'ok'],
        'rejected'  => ['Recusado, pontos devolvidos', 'bad'],
    ];
    foreach ($mine as $r): [$txt, $cls] = $labels[$r['status']] ?? [$r['status'], '']; ?>
        <div class="li">
            <div class="li-main">
                <span class="li-title"><?= e($r['name']) ?></span>
                <span class="li-sub"><?= e(fmt_date($r['created_at'])) ?> - <?= pts($r['cost_points']) ?> pts</span>
                <?php if ($r['admin_note']): ?><span class="li-sub">Recado: <?= e($r['admin_note']) ?></span><?php endif; ?>
            </div>
            <span class="tag <?= e($cls) ?>"><?= e($txt) ?></span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
