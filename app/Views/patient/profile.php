<?php
$bio = trim((string) ($p['bio'] ?? ''));
?>
<section class="profile-head">
    <?= avatar($p, 84) ?>
    <div>
        <h2 class="profile-name"><?= e($p['name']) ?></h2>
        <?php if (!empty($p['goal'])): ?><p class="profile-goal" style="margin:0 0 8px"><?= e($p['goal']) ?></p><?php endif; ?>
        <?php if ($isPatient && $level): partial('level', ['level' => $level]); else: ?><span class="tag">Equipe</span><?php endif; ?>
        <?php if (!empty($sportNames)): ?><div class="sports"><?php foreach ($sportNames as $sn): ?><span class="sport"><?= e($sn) ?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
</section>
<?php if ($bio !== ''): ?><p class="profile-bio"><?= text_html($bio) ?></p><?php endif; ?>

<?php if ($isPatient): ?>
<div class="strip">
    <div class="stat"><span class="big num"><?= (int) $streak ?></span><span class="lbl">dias seguidos</span></div>
    <div class="stat"><span class="big num"><?= $rank && $rank['pts'] > 0 ? '#' . (int) $rank['pos'] : '-' ?></span><span class="lbl">ranking do mês</span></div>
    <div class="stat"><span class="big num"><?= $isMe ? pts($balance) : e($since ? date('m/Y', strtotime($since)) : '-') ?></span><span class="lbl"><?= $isMe ? 'pontos' : 'membro desde' ?></span></div>
</div>
<?php endif; ?>

<?php if ($isMe && $isPatient): ?>
    <section class="section">
        <div class="row-between"><h2 class="section-title" style="margin:0"><?= $planName ? 'Plano ' . e($planName) : 'Renovação' ?></h2><?php partial('seal', ['renew' => $renew, 'word' => true]); ?></div>
        <?php if ($renew['days'] !== null): ?><p class="muted mt" style="margin-bottom:0">Válido até <?= e(fmt_date($p['renewal_date'])) ?></p><?php endif; ?>
        <?php if (in_array($renew['seal'], ['yellow', 'red'], true)): ?>
            <a class="btn btn-sm btn-block mt" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">Renovar pelo WhatsApp</a>
        <?php endif; ?>
        <?php if ($level && $level['next_name']): ?>
            <p class="small muted mt" style="margin-bottom:0">Faltam <?= pts($level['missing']) ?> pontos para o nível <?= e($level['next_name']) ?>. O nível nunca cai.</p>
            <div class="meter" aria-hidden="true"><i style="width:<?= (int) $level['progress'] ?>%"></i></div>
        <?php endif; ?>
    </section>

    <?php
    $isAthlete = $athlete && (int) $athlete['is_athlete'] === 1;
    $evDays = ($isAthlete && $athlete['competes'] && $athlete['event_date']) ? days_until($athlete['event_date']) : null;
    ?>
    <section class="section">
        <div class="row-between"><h2 class="section-title" style="margin:0">Perfil esportivo</h2>
            <a class="small" href="<?= e(url('/perfil/esporte')) ?>"><?= $isAthlete ? 'Editar' : 'Adicionar' ?></a></div>
        <?php if ($isAthlete): ?>
            <?php if ($sportNames): ?><div class="sports" style="margin-top:12px"><?php foreach ($sportNames as $sn): ?><span class="sport"><?= e($sn) ?></span><?php endforeach; ?></div><?php endif; ?>
            <?php if ($athlete['has_coach']): ?><p class="muted small mt" style="margin-bottom:0">Assessoria: <?= e($athlete['coach_name'] ?: 'sim') ?></p><?php endif; ?>
            <?php if ($evDays !== null && $evDays >= 0): ?>
                <p class="mt" style="margin-bottom:0"><strong><?= e($athlete['event_name'] ?: 'Próxima prova') ?></strong> · <?= e(fmt_date($athlete['event_date'])) ?>
                    <span class="tag"><?= $evDays === 0 ? 'hoje' : ($evDays === 1 ? 'amanhã' : 'em ' . (int) $evDays . ' dias') ?></span></p>
            <?php endif; ?>
            <p class="hint" style="margin-bottom:0"><?= $athlete['show_public'] ? 'As modalidades aparecem no seu perfil da comunidade.' : 'As modalidades não aparecem para os outros membros.' ?></p>
        <?php else: ?>
            <p class="muted mt" style="margin-bottom:0">Você pratica corrida, natação, Hyrox ou outro esporte? Adicione aqui para o Gabriel acompanhar melhor.</p>
        <?php endif; ?>
    </section>

    <nav class="links" aria-label="Minha conta">
        <a href="<?= e(url('/perfil/editar')) ?>"><?= icon('user') ?> Editar perfil</a>
        <a href="<?= e(url('/evolucao')) ?>"><?= icon('chart') ?> Evolução</a>
        <a href="<?= e(url('/extrato')) ?>"><?= icon('list') ?> Extrato</a>
        <a href="<?= e(url('/termos')) ?>"><?= icon('doc') ?> Termos</a>
    </nav>
    <form method="post" action="<?= e(url('/logout')) ?>" class="page center">
        <?= csrf_field() ?><button class="btn btn-ghost btn-block" type="submit"><?= icon('logout', 20) ?> Sair da conta</button>
    </form>
<?php endif; ?>

<h2 class="section-title section-head"><?= $isMe ? 'Minhas publicações' : 'Publicações' ?></h2>
<div class="list">
    <?php foreach ($posts as $post): ?>
        <a class="li" href="<?= e(url('/post/' . $post['id'])) ?>">
            <div class="li-main">
                <span class="li-title" style="font-weight:500"><?= e(mb_strlen($post['body']) > 110 ? mb_substr($post['body'], 0, 110) . '...' : $post['body']) ?></span>
                <span class="li-sub"><?= e(time_ago($post['created_at'])) ?> - <?= (int) $post['likes'] ?> curtidas - <?= (int) $post['comments'] ?> comentários</span>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php if (!$posts): ?><div class="empty"><strong>Nenhuma publicação ainda</strong></div><?php endif; ?>
