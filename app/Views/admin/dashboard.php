<div class="page-head">
    <h2>Painel</h2>
    <a class="btn btn-sm" href="<?= e(url('/admin/pacientes/novo')) ?>"><?= icon('plus', 18) ?> Novo paciente</a>
</div>

<div class="kpis">
    <a class="kpi" href="<?= e(url('/admin/pacientes')) ?>"><span class="big num"><?= (int) $stats['patients'] ?></span><span class="lbl">pacientes ativos</span></a>
    <div class="kpi"><span class="big num"><?= (int) $stats['active7'] ?></span><span class="lbl">abriram o app em 7 dias</span></div>
    <div class="kpi"><span class="big num"><?= (int) $stats['checkins'] ?></span><span class="lbl">check-ins hoje</span></div>
    <a class="kpi" href="<?= e(url('/admin/pacientes?f=yellow')) ?>"><span class="big num"><?= (int) $stats['expiring'] ?></span><span class="lbl">vencem em até <?= (int) $yellow ?> dias</span></a>
    <a class="kpi<?= $stats['expired'] > 0 ? ' alert' : '' ?>" href="<?= e(url('/admin/pacientes?f=red')) ?>"><span class="big num"><?= (int) $stats['expired'] ?></span><span class="lbl">vencidos</span></a>
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
        <thead><tr><th>Paciente</th><th>Renovação</th><th>Visto por último</th><th>Último check-in</th><th class="r">Ação</th></tr></thead>
        <tbody>
        <?php foreach ($risk as $r): ?>
            <tr>
                <td><a class="who" href="<?= e(url('/admin/pacientes/' . $r['id'])) ?>"><?= avatar($r, 34) ?><?= e($r['name']) ?></a></td>
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
