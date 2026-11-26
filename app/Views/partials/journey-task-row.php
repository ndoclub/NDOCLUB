<?php
/**
 * Uma tarefa da Jornada (painel admin). Recebe:
 *  $t = linha de journey_tasks (na página da Jornada vem com patient, phone e plan_name)
 *  $back = caminho interno para voltar depois da ação
 *  $showPatient = mostra o nome do paciente (página da Jornada) ou não (já está na ficha)
 */
use App\Services\Journey;

$meta = Journey::TASKS[$t['code']] ?? [$t['code'], '', 'contato'];
$due = $t['due_on'] ? substr((string) $t['due_on'], 0, 10) : null;
$dd = $due ? days_until($due) : null;
$st = (string) $t['status'];
$late = $st === 'pending' && $dd !== null && $dd < 0;
$isToday = $st === 'pending' && $dd === 0;
$pid = (int) $t['user_id'];
$showPatient = !empty($showPatient);
$wa = (!empty($t['phone']) && in_array($t['code'], ['contato_renovacao', 'mensal_msg', 'recontato_pausa'], true)) ? wa_phone_url((string) $t['phone']) : null;
?>
<div class="jr-row<?= $late ? ' is-late' : ($isToday ? ' is-today' : '') ?><?= in_array($st, ['skipped', 'prior'], true) ? ' is-off' : '' ?>">
    <div class="jr-main">
        <?php if ($showPatient): ?><a class="jr-patient" href="<?= e(url(\App\Controllers\Admin\PatientController::ficha($pid, 'jornada'))) ?>"><?= e($t['patient']) ?></a><?php if (!empty($t['plan_name'])): ?> <span class="small muted">· <?= e($t['plan_name']) ?></span><?php endif; ?><?php endif; ?>
        <strong class="jr-title"><?= e($meta[0]) ?></strong>
        <?php if ($st === 'pending'): ?><span class="small muted"><?= e($meta[1]) ?></span><?php endif; ?>
        <?php if (!empty($t['note']) && $st !== 'pending'): ?><span class="small muted"><?= e($t['note']) ?></span><?php endif; ?>
    </div>
    <div class="jr-due">
        <?php if ($st === 'done'): ?>
            <span class="tag ok">Concluída<?= $t['done_at'] ? ' em ' . e(fmt_date((string) $t['done_at'])) : '' ?></span>
        <?php elseif ($st === 'skipped'): ?>
            <span class="tag">Deixou de valer</span>
        <?php elseif ($st === 'prior'): ?>
            <span class="tag">Anterior ao sistema</span>
        <?php elseif ($late): ?>
            <span class="tag bad">Atrasada há <?= (int) abs($dd) ?> <?= abs($dd) === 1 ? 'dia' : 'dias' ?></span>
        <?php elseif ($isToday): ?>
            <span class="tag warn">Hoje</span>
        <?php elseif ($dd !== null): ?>
            <span class="tag">Em <?= (int) $dd ?> <?= $dd === 1 ? 'dia' : 'dias' ?></span>
        <?php endif; ?>
        <?php if ($due): ?><span class="small muted">Prazo <?= e(fmt_date($due)) ?></span><?php endif; ?>
    </div>
    <div class="jr-who"><?php if (!empty($t['assignee'])): ?><span class="tag"><?= e($t['assignee']) ?></span><?php else: ?><span class="small muted">Sem responsável</span><?php endif; ?></div>
    <div class="jr-act">
        <?php if ($st === 'pending'): ?>
            <?php if ($t['code'] === 'ultima_consulta'): ?>
                <a class="btn btn-sm" href="<?= e(url(\App\Controllers\Admin\PatientController::ficha($pid, 'jornada', 'resposta'))) ?>">Registrar resposta</a>
            <?php elseif ($t['code'] === 'atualizar_plano'): ?>
                <a class="btn btn-sm" href="<?= e(url(\App\Controllers\Admin\PatientController::ficha($pid, 'plano', 'renovar'))) ?>">Ir para Renovar</a>
            <?php else: ?>
                <form method="post" action="<?= e(url('/admin/jornada/tarefas/' . (int) $t['id'] . '/concluir')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
                    <button class="btn btn-sm" type="submit">Concluir</button>
                </form>
            <?php endif; ?>
            <?php if ($wa): ?><a class="btn btn-ghost btn-sm" href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        <?php elseif ($st === 'done' && !in_array($t['code'], Journey::AUTO_DONE, true)): ?>
            <form method="post" action="<?= e(url('/admin/jornada/tarefas/' . (int) $t['id'] . '/reabrir')) ?>">
                <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Reabrir</button>
            </form>
        <?php endif; ?>
    </div>
</div>
