<?php
/**
 * Aba Operação: "o que eu faço hoje?". Dois cartões de tarefa no alto (cada um leva para a lista; o que é do app do paciente fica no módulo App do paciente, desde a 09.5),
 * (se houver retornos de leads para fazer, uma faixa do Follow-up do CRM), uma linha de resumo, a previsão de renovação e as listas do dia (risco, aniversários, provas).
 * Os números são os mesmos de antes; só a apresentação mudou.
 */
$brlDue = money($due30['total'], floor($due30['total']) == $due30['total'] ? 0 : 2);
$foreignDue = array_diff_key($due30['by'], ['BRL' => 1]);
$plural = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);
$nPix = (int) $pix['count'];
$nLate = (int) $pix['late'];
$nExp = (int) $stats['expiring'];
$nOld = (int) $stats['expired'];
?>

<h3 class="osec">Para fazer hoje</h3>
<div class="otasks">
    <div class="otask<?= $nLate > 0 ? ' is-alert' : ($nPix === 0 ? ' is-zero' : '') ?>">
        <a class="otask-main" href="<?= e(url('/admin/financeiro')) ?>">
            <span class="otask-ico"><?= icon('cash', 20) ?></span>
            <span class="big num"><?= (int) $pix['count'] ?></span>
            <span class="lbl">parcelas para cobrar ou conferir</span>
        </a>
        <span class="otask-sub"><?php if ($nPix === 0): ?>Nada para cobrar agora.<?php else: ?><?= $nLate > 0 ? '<b class="bad">' . e($plural($nLate, 'atrasada', 'atrasadas')) . '</b> · ' : '' ?><span class="nowrap"><?= e(money_multi($pix['by'])) ?></span><?php endif; ?></span>
    </div>

    <div class="otask<?= $nExp === 0 && $nOld === 0 ? ' is-zero' : '' ?>">
        <a class="otask-main" href="<?= e(url('/admin/pacientes?f=yellow')) ?>">
            <span class="otask-ico"><?= icon('refresh', 20) ?></span>
            <span class="big num"><?= $nExp ?></span>
            <span class="lbl">vencem em até <?= (int) $yellow ?> dias</span>
        </a>
        <span class="otask-sub"><?php if ($nOld > 0): ?><a class="bad" href="<?= e(url('/admin/pacientes?f=red')) ?>"><?= $nOld ?> <?= $nOld === 1 ? 'vencido' : 'vencidos' ?></a> <span class="muted">sem renovar</span><?php else: ?>Nenhum plano vencido.<?php endif; ?></span>
    </div>
</div>

<?php if ($crm['late'] + $crm['today'] + $crm['none'] > 0): ?>
<div class="ocrm<?= $crm['late'] > 0 ? ' is-alert' : '' ?>" aria-label="Follow-up do CRM">
    <span class="ocrm-t"><?= icon('filter', 18) ?> Follow-up de leads</span>
    <?php if ($crm['late'] > 0): ?><a class="bad" href="<?= e(url('/admin/crm/follow-up')) ?>"><?= e($plural((int) $crm['late'], 'retorno atrasado', 'retornos atrasados')) ?></a><?php endif; ?>
    <?php if ($crm['today'] > 0): ?><a href="<?= e(url('/admin/crm/follow-up')) ?>"><?= e($plural((int) $crm['today'], 'retorno para hoje', 'retornos para hoje')) ?></a><?php endif; ?>
    <?php if ($crm['none'] > 0): ?><a href="<?= e(url('/admin/crm?f=none')) ?>"><?= e($plural((int) $crm['none'], 'lead sem retorno programado', 'leads sem retorno programado')) ?></a><?php endif; ?>
    <a class="ocrm-go" href="<?= e(url('/admin/crm/follow-up')) ?>">Abrir o Follow-up →</a>
</div>
<?php endif; ?>

<?php if (($journey['late'] ?? 0) + ($journey['today'] ?? 0) > 0): ?>
<div class="ocrm<?= $journey['late'] > 0 ? ' is-alert' : '' ?>" aria-label="Jornada do paciente">
    <span class="ocrm-t"><?= icon('flag', 18) ?> Jornada do paciente</span>
    <?php if ($journey['late'] > 0): ?><a class="bad" href="<?= e(url('/admin/jornada')) ?>"><?= e($plural((int) $journey['late'], 'tarefa atrasada', 'tarefas atrasadas')) ?></a><?php endif; ?>
    <?php if ($journey['today'] > 0): ?><a href="<?= e(url('/admin/jornada')) ?>"><?= e($plural((int) $journey['today'], 'tarefa para hoje', 'tarefas para hoje')) ?></a><?php endif; ?>
    <?php foreach (array_slice($journey['byWho'] ?? [], 0, 4, true) as $jw => $jn): ?><a class="bad" href="<?= e(url('/admin/jornada?r=' . rawurlencode((string) $jw))) ?>"><?= $jw === '__none' ? 'Sem responsável' : e($jw) ?>: <?= (int) $jn ?> <?= $jn === 1 ? 'atrasada' : 'atrasadas' ?></a><?php endforeach; ?>
    <a class="ocrm-go" href="<?= e(url('/admin/jornada')) ?>">Abrir a Jornada →</a>
</div>
<?php endif; ?>

<div class="ostats" aria-label="Resumo">
    <a href="<?= e(url('/admin/pacientes?st=active')) ?>"><b class="num"><?= (int) $stats['patients'] ?></b><span class="lbl">pacientes em carteira</span></a>
    <a href="<?= e(url('/admin/pacientes?f=athletes')) ?>"><b class="num"><?= (int) $stats['athletes'] ?></b><span class="lbl">atletas</span></a>
</div>

<div class="ocols">
    <div class="ocol-main">
        <section class="panel opanel" aria-labelledby="h-risk">
            <div class="xp-head">
                <h3 id="h-risk">Risco de renovação</h3>
                <a class="tiny muted-link" href="<?= e(url('/admin/pacientes?f=yellow')) ?>">Ver na lista de pacientes →</a>
            </div>
            <p class="muted small" style="margin:0 0 12px">Quem vence em até <?= (int) $yellow ?> dias (ou já venceu).</p>

            <a class="oforecast" href="<?= e(url('/admin/pacientes?f=yellow')) ?>">
                <span class="big num"><?= e($brlDue) ?></span>
                <span class="lbl">a renovar em 30 dias · <?= (int) $due30['count'] ?> <?= $due30['count'] === 1 ? 'paciente' : 'pacientes' ?><?= $due30['missing'] > 0 ? ' · ' . (int) $due30['missing'] . ' sem valor definido' : '' ?><?php if ($foreignDue): ?> · + <?= e(money_multi($foreignDue)) ?> (exterior)<?php endif; ?></span>
            </a>

            <?php if (!$risk): ?>
                <div class="empty"><strong>Ninguém perto de vencer</strong>Quando alguém entrar na janela de <?= (int) $yellow ?> dias, aparece aqui.</div>
            <?php else: ?>
            <div class="tbl-wrap"><table class="tbl rk-tbl">
                <thead><tr><th>Paciente</th><th>Renovação</th><th class="r">Ação</th></tr></thead>
                <tbody>
                <?php foreach ($risk as $r): ?>
                    <tr>
                        <td><a class="who" href="<?= e(url('/admin/pacientes/' . $r['id'])) ?>"><?= avatar($r, 34) ?><span><span class="rk-name"><?= e($r['name']) ?></span>
                            <span class="rk-plan small muted"><?php if ($r['plan_name']): ?><?= e($r['plan_name']) ?> · <?= $r['plan_value'] !== null ? e(money((float) $r['plan_value'], floor((float) $r['plan_value']) == (float) $r['plan_value'] ? 0 : 2, (string) $r['plan_currency'])) : 'sem valor' ?><?php else: ?>sem plano<?php endif; ?></span>
</span></a></td>
                        <td><?php partial('seal', ['renew' => $r['renew']]); ?></td>
                        <td class="r">
                            <div class="row-actions">
                                <?php if ($r['wa']): ?><a class="btn btn-ghost btn-sm" href="<?= e($r['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                                <a class="ra" href="<?= e(url('/admin/pacientes/' . $r['id'])) ?>" title="Abrir a ficha" aria-label="Abrir a ficha de <?= e($r['name']) ?>"><?= icon('eye', 18) ?></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </section>
    </div>

    <div class="ocol-side">
        <section class="panel opanel" aria-labelledby="h-bd">
            <div class="xp-head">
                <h3 id="h-bd">Aniversariantes</h3>
                <a class="tiny muted-link" href="<?= e(url('/admin/aniversarios')) ?>">Ver todos →</a>
            </div>
            <p class="muted small" style="margin:0 0 10px">Hoje e nos próximos 7 dias. O botão abre o WhatsApp com a mensagem pronta (você confere e envia).</p>
            <?php if (!$birthdays): ?>
                <div class="empty"><strong>Nenhum aniversário nos próximos 7 dias</strong>Preencha a data de nascimento na ficha dos pacientes para eles aparecerem aqui.</div>
            <?php else: partial('birthday-table', ['rows' => $birthdays]); endif; ?>
        </section>

        <?php if ($events): ?>
        <section class="panel opanel" aria-labelledby="h-ev">
            <div class="xp-head"><h3 id="h-ev">Próximas provas</h3></div>
            <p class="muted small" style="margin:0 0 10px">Atletas que competem, da prova mais perto para a mais longe. É a hora de ajustar a dieta e mandar uma mensagem.</p>
            <ul class="evlist">
                <?php foreach ($events as $ev): $dd = days_until($ev['event_date']); ?>
                    <li>
                        <a class="who" href="<?= e(url('/admin/pacientes/' . $ev['id'])) ?>"><?= avatar($ev, 34) ?>
                            <span><?= e($ev['name']) ?>
                                <span class="small muted"><?= $ev['event_name'] ? e($ev['event_name']) : 'Prova' ?> · <?= $ev['has_coach'] ? e($ev['coach_name'] ?: 'Com assessoria') : 'Sem assessoria' ?></span>
                                <?php if (!empty($eventSports[(int) $ev['id']])): ?><span class="sports"><?php foreach ($eventSports[(int) $ev['id']] as $sn): ?><span class="sport"><?= e($sn) ?></span><?php endforeach; ?></span><?php endif; ?>
                            </span></a>
                        <span class="ev-when"><strong><?= e(fmt_date($ev['event_date'])) ?></strong>
                            <span class="tag<?= $dd <= 14 ? ' warn' : '' ?>"><?= $dd === 0 ? 'hoje' : ($dd === 1 ? 'amanhã' : 'em ' . (int) $dd . ' dias') ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>
</div>
