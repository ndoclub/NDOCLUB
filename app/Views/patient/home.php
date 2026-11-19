<?php
$first = first_name($user['name']);
$streakWord = $streak === 1 ? 'dia seguido' : 'dias seguidos';
?>
<section class="hero">
    <div class="hero-count">
        <span class="hero-num num"><?= (int) $streak ?></span>
        <div class="hero-cap">
            <strong><?= e($streakWord) ?></strong>
            <span>Oi, <?= e($first) ?></span>
        </div>
    </div>

    <div class="week" role="list" aria-label="Últimos 7 dias">
        <?php foreach ($week as $d): ?>
            <div class="day<?= $d['done'] ? ' done' : '' ?><?= $d['today'] ? ' today' : '' ?>" role="listitem"
                 aria-label="<?= e(weekday_name((int) date('N', strtotime($d['date']))) . ($d['done'] ? ': check-in feito' : ': sem check-in')) ?>">
                <span><?= e($d['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($doneToday): ?>
        <p class="done-tag center" style="justify-content:center;display:flex"><?= icon('check', 20) ?> Check-in de hoje feito</p>
    <?php else: ?>
        <a class="btn btn-block" href="<?= e(url('/checkin')) ?>">Fazer o check-in de hoje</a>
        <p class="hint center">Leva 10 segundos, vale pontos e mantém a sua sequência.</p>
    <?php endif; ?>
</section>

<div class="strip">
    <a class="stat" href="<?= e(url('/extrato')) ?>">
        <span class="big num"><?= pts($balance) ?></span><span class="lbl">pontos</span>
    </a>
    <a class="stat" href="<?= e(url('/ranking')) ?>">
        <span class="big num"><?= $rank && $monthPts > 0 ? '#' . (int) $rank['pos'] : '-' ?></span><span class="lbl">ranking do mês</span>
    </a>
    <a class="stat" href="<?= e(url('/perfil')) ?>">
        <span class="big"><?= e($level['name']) ?></span><span class="lbl">seu nível</span>
    </a>
</div>

<section class="section">
    <div class="row-between">
        <h2 class="section-title" style="margin:0">Renovação</h2>
        <?php partial('seal', ['renew' => $renew, 'word' => true]); ?>
    </div>
    <?php if ($renew['days'] === null): ?>
        <p class="muted mt">A data de renovação do seu acompanhamento ainda não foi definida.</p>
    <?php else: ?>
        <div class="renew-row mt">
            <?php if ($renew['days'] >= 0): ?>
                <span class="renew-days num"><?= (int) $renew['days'] ?></span>
                <span><?= $renew['days'] === 1 ? 'dia' : 'dias' ?> para renovar</span>
            <?php else: ?>
                <span class="renew-days num"><?= abs((int) $renew['days']) ?></span>
                <span><?= abs((int) $renew['days']) === 1 ? 'dia' : 'dias' ?> de atraso</span>
            <?php endif; ?>
        </div>
        <div class="meter" aria-hidden="true"><i style="width:<?= (int) max(4, min(100, round(max(0, $renew['days']) / 30 * 100))) ?>%"></i></div>
        <p class="hint">Vence em <?= e(fmt_date($user['renewal_date'])) ?></p>

        <?php if (in_array($renew['seal'], ['yellow', 'red'], true)): ?>
            <div class="renew-alert <?= e($renew['seal']) ?>">
                <p><?= $renew['seal'] === 'red'
                    ? 'Seu acompanhamento vence em poucos dias. Renove para não ficar sem plano e manter seus pontos rendendo.'
                    : 'Seu acompanhamento está chegando ao fim. Fale com o Gabriel para renovar.' ?></p>
                <a class="btn btn-sm btn-block" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">Renovar pelo WhatsApp</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($pinned): ?>
    <section class="notice">
        <span class="pin-tag">Mural do Gabriel</span>
        <p><?= text_html(mb_strlen($pinned['body']) > 220 ? mb_substr($pinned['body'], 0, 220) . '...' : $pinned['body']) ?></p>
        <a class="small" href="<?= e(url('/post/' . $pinned['id'])) ?>">Ler o aviso completo</a>
    </section>
<?php endif; ?>

<?php if ($todayWorkouts): ?>
    <section class="section">
        <h2 class="section-title">Treino de hoje</h2>
        <?php foreach ($todayWorkouts as $w): ?>
            <a class="row-between" href="<?= e(url('/treino')) ?>" style="text-decoration:none;padding:6px 0">
                <strong><?= e($w['title']) ?></strong>
                <?php if ((int) $w['done'] > 0): ?><span class="done-tag"><?= icon('check', 18) ?> Feito</span>
                <?php else: ?><span class="small muted">Abrir treino</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($theme['title'] !== ''): ?>
    <section class="section">
        <h2 class="section-title">Tema de hoje no feed</h2>
        <strong style="font-family:var(--display);font-weight:400;font-size:18px"><?= e($theme['title']) ?></strong>
        <p class="muted" style="margin:6px 0 14px"><?= e($theme['question']) ?></p>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/feed?tema=1#novo')) ?>">Postar no tema (+pontos)</a>
    </section>
<?php endif; ?>

<?php if ($pendingRedemptions > 0): ?>
    <section class="section">
        <a href="<?= e(url('/loja')) ?>">Você tem <?= (int) $pendingRedemptions ?> <?= $pendingRedemptions === 1 ? 'resgate em andamento' : 'resgates em andamento' ?></a>
    </section>
<?php endif; ?>

<nav class="links" aria-label="Atalhos">
    <a href="<?= e(url('/dieta')) ?>"><?= icon('apple') ?> Dieta</a>
    <a href="<?= e(url('/evolucao')) ?>"><?= icon('chart') ?> Evolução</a>
    <a href="<?= e(url('/extrato')) ?>"><?= icon('list') ?> Extrato</a>
    <a href="<?= e(url('/perfil')) ?>"><?= icon('user') ?> Meu perfil</a>
</nav>
