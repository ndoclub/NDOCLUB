<div class="page-head">
    <h2>Painel</h2>
    <a class="btn btn-sm" href="<?= e(url('/admin/pacientes/novo')) ?>"><?= icon('plus', 18) ?> Novo paciente</a>
</div>

<div class="kpis">
    <a class="kpi wide" href="<?= e(url('/admin/pacientes?f=yellow')) ?>">
        <span class="big num"><?= e(money($due30['total'], floor($due30['total']) == $due30['total'] ? 0 : 2)) ?></span>
        <span class="lbl">a renovar em 30 dias · <?= (int) $due30['count'] ?> <?= $due30['count'] === 1 ? 'paciente' : 'pacientes' ?><?= $due30['missing'] > 0 ? ' · ' . (int) $due30['missing'] . ' sem valor definido' : '' ?></span>
    </a>
    <a class="kpi" href="<?= e(url('/admin/pacientes')) ?>"><span class="big num"><?= (int) $stats['patients'] ?></span><span class="lbl">pacientes ativos</span></a>
    <div class="kpi"><span class="big num"><?= (int) $stats['active7'] ?></span><span class="lbl">abriram o app em 7 dias</span></div>
    <div class="kpi"><span class="big num"><?= (int) $stats['checkins'] ?></span><span class="lbl">check-ins hoje</span></div>
    <a class="kpi" href="<?= e(url('/admin/pacientes?f=yellow')) ?>"><span class="big num"><?= (int) $stats['expiring'] ?></span><span class="lbl">vencem em até <?= (int) $yellow ?> dias</span></a>
    <a class="kpi<?= $stats['expired'] > 0 ? ' alert' : '' ?>" href="<?= e(url('/admin/pacientes?f=red')) ?>"><span class="big num"><?= (int) $stats['expired'] ?></span><span class="lbl">vencidos</span></a>
    <a class="kpi" href="<?= e(url('/admin/pacientes?f=athletes')) ?>"><span class="big num"><?= (int) $stats['athletes'] ?></span><span class="lbl">atletas ativos</span></a>
    <a class="kpi" href="<?= e(url('/admin/resgates')) ?>"><span class="big num"><?= (int) $stats['redemptions'] ?></span><span class="lbl">resgates pendentes</span></a>
    <a class="kpi<?= $stats['reports'] > 0 ? ' alert' : '' ?>" href="<?= e(url('/admin/moderacao')) ?>"><span class="big num"><?= (int) $stats['reports'] ?></span><span class="lbl">denúncias abertas</span></a>
</div>

<h3 class="sub" style="margin-top:0">Risco de renovação</h3>
<p class="muted small">Pacientes que vencem em até <?= (int) $yellow ?> dias (ou já venceram). Quem está sem abrir o app há 7 dias ou mais aparece primeiro: é a hora de uma mensagem.</p>

<?php if (!$risk): ?>
    <div class="empty"><strong>Ninguém perto de vencer</strong>Quando alguém entrar na janela de <?= (int) $yellow ?> dias, aparece aqui.</div>
<?php else: ?>
<div class="tbl-wrap">
    <table class="tbl">
        <thead><tr><th>Paciente</th><th>Plano</th><th>Renovação</th><th>Visto por último</th><th>Último check-in</th><th class="r">Ação</th></tr></thead>
        <tbody>
        <?php foreach ($risk as $r): ?>
            <tr>
                <td><a class="who" href="<?= e(url('/admin/pacientes/' . $r['id'])) ?>"><?= avatar($r, 34) ?><?= e($r['name']) ?></a></td>
                <td><?php if ($r['plan_name']): ?><?= e($r['plan_name']) ?><br><span class="small muted"><?= $r['plan_value'] !== null ? e(money((float) $r['plan_value'], floor((float) $r['plan_value']) == (float) $r['plan_value'] ? 0 : 2)) : 'sem valor' ?></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                <td><?php partial('seal', ['renew' => $r['renew']]); ?></td>
                <td>
                    <?= $r['last_seen_at'] ? e(time_ago($r['last_seen_at'])) : 'Nunca entrou' ?>
                    <?php if ($r['away']): ?> <span class="tag warn">sumido</span><?php endif; ?>
                </td>
                <td><?= $r['last_checkin'] ? e(fmt_date($r['last_checkin'])) : '-' ?></td>
                <td class="r">
                    <?php if ($r['wa']): ?><a class="btn btn-ghost btn-sm" href="<?= e($r['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/pacientes/' . $r['id'])) ?>">Abrir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($events): ?>
<h3 class="sub" style="margin-top:32px">Próximas provas</h3>
<p class="muted small">Atletas que competem, da prova mais perto para a mais longe. É a hora de ajustar a dieta e mandar uma mensagem.</p>
<div class="tbl-wrap">
    <table class="tbl">
        <thead><tr><th>Paciente</th><th>Prova</th><th>Data</th><th>Assessoria</th><th class="r">Ação</th></tr></thead>
        <tbody>
        <?php foreach ($events as $ev): $dd = days_until($ev['event_date']); ?>
            <tr>
                <td><a class="who" href="<?= e(url('/admin/pacientes/' . $ev['id'])) ?>"><?= avatar($ev, 34) ?>
                    <span><?= e($ev['name']) ?><?php if (!empty($eventSports[(int) $ev['id']])): ?><span class="sports"><?php foreach ($eventSports[(int) $ev['id']] as $sn): ?><span class="sport"><?= e($sn) ?></span><?php endforeach; ?></span><?php endif; ?></span></a></td>
                <td><?= $ev['event_name'] ? e($ev['event_name']) : '<span class="muted">-</span>' ?></td>
                <td><?= e(fmt_date($ev['event_date'])) ?> <span class="tag<?= $dd <= 14 ? ' warn' : '' ?>"><?= $dd === 0 ? 'hoje' : ($dd === 1 ? 'amanhã' : 'em ' . (int) $dd . ' dias') ?></span></td>
                <td><?= $ev['has_coach'] ? e($ev['coach_name'] ?: 'Sim') : '<span class="muted">Sem assessoria</span>' ?></td>
                <td class="r"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/pacientes/' . $ev['id'])) ?>">Abrir</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
