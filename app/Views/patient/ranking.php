<?php $shown = array_values(array_filter($list, fn($r) => $r['pts'] > 0)); $meInList = false; ?>
<div class="month-nav">
    <a href="<?= e(url('/ranking?m=' . $prev)) ?>" aria-label="Mês anterior"><?= icon('left', 20) ?></a>
    <strong><?= e($monthLabel) ?></strong>
    <?php if ($next): ?><a href="<?= e(url('/ranking?m=' . $next)) ?>" aria-label="Próximo mês"><?= icon('right', 20) ?></a><?php else: ?><span class="month-spacer"></span><?php endif; ?>
</div>
<?php if ($isCurrent): ?>
    <p class="hint" style="padding:0 16px 14px;margin:0">O ranking zera em <?= (int) $resetDays ?> <?= $resetDays === 1 ? 'dia' : 'dias' ?>. Seus pontos da loja não zeram.</p>
<?php endif; ?>

<div class="list">
<?php foreach ($shown as $r):
    $isMe = $me && (int) $r['id'] === (int) $me['id'];
    $meInList = $meInList || $isMe; ?>
    <a class="rank-row<?= $r['pos'] <= 3 ? ' top' : '' ?><?= $isMe ? ' me' : '' ?>" href="<?= e(url($isMe ? '/perfil' : '/u/' . $r['id'])) ?>">
        <span class="rank-pos num"><?= (int) $r['pos'] ?></span>
        <?= avatar($r, 40) ?>
        <span class="rank-name"><?= e($r['name']) ?><?= $isMe ? ' (você)' : '' ?></span>
        <span class="rank-pts num"><?= pts($r['pts']) ?></span>
    </a>
<?php endforeach; ?>
</div>

<?php if (!$shown): ?>
    <div class="empty"><strong>Ninguém pontuou neste mês ainda</strong>Faça o check-in de hoje e abra o ranking.</div>
<?php endif; ?>

<?php if ($isCurrent && $me && !$meInList): ?>
    <p class="hint" style="padding:14px 16px 0;margin:0">Sua posição</p>
    <?php if ($me['pts'] > 0): ?>
        <div class="list"><a class="rank-row me" href="<?= e(url('/perfil')) ?>">
            <span class="rank-pos num"><?= (int) $me['pos'] ?></span><?= avatar($me, 40) ?>
            <span class="rank-name"><?= e($me['name']) ?> (você)</span><span class="rank-pts num"><?= pts($me['pts']) ?></span>
        </a></div>
    <?php else: ?>
        <p class="muted" style="padding:6px 16px 20px">Você ainda não pontuou neste mês. Um check-in já coloca você no ranking.</p>
    <?php endif; ?>
<?php endif; ?>
