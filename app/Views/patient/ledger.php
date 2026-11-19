<div class="strip" style="grid-template-columns:1fr 1fr">
    <div class="stat"><span class="big num"><?= pts($balance) ?></span><span class="lbl">saldo da loja</span></div>
    <div class="stat"><span class="big num"><?= pts($monthPts) ?></span><span class="lbl">ganhos no mês (ranking)</span></div>
</div>

<div class="list">
<?php foreach ($rows as $r): ?>
    <div class="li">
        <div class="li-main">
            <span class="li-title"><?= e($r['label']) ?></span>
            <span class="li-sub"><?= e(fmt_datetime($r['created_at'])) ?><?= ($r['note'] && mb_strtolower(trim($r['note'])) !== mb_strtolower(trim($r['label']))) ? ' · ' . e($r['note']) : '' ?></span>
        </div>
        <span class="li-side num <?= $r['points'] >= 0 ? 'pos' : 'neg' ?>"><?= $r['points'] >= 0 ? '+' : '−' ?><?= pts(abs($r['points'])) ?></span>
    </div>
<?php endforeach; ?>
</div>
<?php if (!$rows): ?>
    <div class="empty"><strong>Nenhum ponto ainda</strong>Faça seu check-in, treine ou publique no feed para começar.</div>
<?php endif; ?>

<?php if ($page > 1 || $hasMore): ?>
    <div class="pager">
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/extrato?p=' . ($page - 1))) ?>">Mais recentes</a><?php else: ?><span></span><?php endif; ?>
        <?php if ($hasMore): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/extrato?p=' . ($page + 1))) ?>">Mais antigas</a><?php endif; ?>
    </div>
<?php endif; ?>
