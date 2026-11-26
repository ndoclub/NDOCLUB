<?php
use App\Controllers\Admin\JourneyController;
use App\Controllers\Admin\PatientController;
use App\Services\Journey;

/**
 * Jornada do paciente (Atualização 11, parte 1: Renovação e Saída).
 * Três abas: Tarefas (o que fazer, por prazo), Renovações (quem está perto de vencer e em que ponto está) e
 * Vencidos (plano venceu e ninguém decidiu). O sistema só lembra e registra; nunca manda mensagem.
 */
$plural = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);
$nLate = count($groups['late']);
$nToday = count($groups['today']);
$tabN = ['tarefas' => $nLate + $nToday, 'renovacoes' => count($renewals), 'vencidos' => count($expired)];
$link = fn(array $q) => url('/admin/jornada') . ($q ? '?' . http_build_query($q) : '');
$sections = [
    'late' => ['Atrasadas', 'bad'],
    'today' => ['Para hoje', 'warn'],
    'week' => ['Próximos 7 dias', ''],
    'later' => ['Mais adiante', ''],
];
$typeShort = ['premium' => 'Premium', 'smart' => 'Smart', 'escalavel' => 'Escalável', 'mensal' => 'Mensal'];
$srcLabel = ['marcada' => 'marcada', 'feita' => 'já feita', 'estimada' => 'estimada'];
?>
<div class="page-head ppage-head">
    <div>
        <h2>Jornada do paciente</h2>
        <p class="muted small" style="margin:4px 0 0">Renovação e saída: o que cada pessoa da equipe precisa fazer, no prazo. O sistema só lembra; as mensagens você manda pelo WhatsApp.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/jornada/ajustes')) ?>"><?= icon('gear', 18) ?> Ajustes</a>
</div>

<nav class="tabs scroll" aria-label="Jornada do paciente">
    <?php foreach (JourneyController::ABAS as $k => $label): ?>
        <a href="<?= e($link($k === 'tarefas' ? [] : ['aba' => $k])) ?>"<?= $aba === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($tabN[$k] > 0): ?> <b class="<?= $k === 'tarefas' ? 'tab-n' : 'tab-n tab-n-soft' ?>" title="<?= (int) $tabN[$k] ?>"><?= (int) $tabN[$k] ?></b><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($aba === 'tarefas'): ?>
    <div class="chips quick" role="group" aria-label="Filtrar por responsável">
        <?php $all = array_sum($byWho); ?>
        <a class="chip" href="<?= e($link([])) ?>"<?= $who === '' ? ' aria-current="true"' : '' ?>>Todos <span class="chip-n">(<?= (int) $all ?>)</span></a>
        <?php foreach ($byWho as $name => $n): ?>
            <a class="chip" href="<?= e($link(['r' => $name])) ?>"<?= $who === $name ? ' aria-current="true"' : '' ?>><?= $name === '__none' ? 'Sem responsável' : e($name) ?> <span class="chip-n">(<?= (int) $n ?>)</span></a>
        <?php endforeach; ?>
    </div>
    <p class="kb-sum small muted" style="margin:0 0 14px">
        <strong><?= (int) $total ?></strong> <?= $total === 1 ? 'tarefa pendente' : 'tarefas pendentes' ?><?= $who !== '' ? ' (com o filtro)' : '' ?>
        <?php if ($nLate > 0): ?> · <strong class="jr-late"><?= e($plural($nLate, 'atrasada', 'atrasadas')) ?></strong><?php endif; ?>
    </p>
    <?php if ($total === 0): ?>
        <div class="panel"><p class="muted" style="margin:0">Nenhuma tarefa pendente agora. As tarefas aparecem sozinhas perto do prazo de cada paciente.</p></div>
    <?php endif; ?>
    <?php foreach ($sections as $key => [$label, $tag]): if (!$groups[$key]) { continue; } ?>
        <section class="panel jr-sec" aria-label="<?= e($label) ?>">
            <h3><?= e($label) ?> <span class="tag<?= $tag !== '' ? ' ' . e($tag) : '' ?>"><?= count($groups[$key]) ?></span></h3>
            <div class="jr-list">
                <?php foreach ($groups[$key] as $t): partial('journey-task-row', ['t' => $t, 'back' => $back, 'showPatient' => true]); endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <p class="hint">O que venceu antes de <strong><?= e(fmt_date($cfg['start'])) ?></strong> (início da Jornada) aparece na ficha como "anterior ao sistema" e não conta como atraso. Dá para mudar em Ajustes.</p>

<?php elseif ($aba === 'renovacoes'): ?>
    <p class="muted small" style="margin:0 0 12px">Quem vence nos próximos <?= (int) $cfg['window'] ?> dias (ou já tem tarefa de renovação aberta), do mais próximo ao mais distante.</p>
    <?php if (!$renewals): ?>
        <div class="panel"><p class="muted" style="margin:0">Ninguém perto de vencer agora.</p></div>
    <?php else: ?>
        <div class="tbl-wrap jr-tbl"><table class="tbl">
            <thead><tr><th>Paciente</th><th>Plano</th><th>Vence</th><th>Última consulta</th><th>Onde está</th><th>Próximo passo</th></tr></thead>
            <tbody>
            <?php foreach ($renewals as $r): $nx = $r['next']; ?>
                <tr>
                    <td data-label="Paciente"><a href="<?= e(url(PatientController::ficha((int) $r['id'], 'jornada'))) ?>"><strong><?= e($r['name']) ?></strong></a></td>
                    <td data-label="Plano"><?= e($r['plan']) ?> <span class="small muted">· <?= e($typeShort[$r['type']] ?? '') ?></span></td>
                    <td class="num" data-label="Vence"><?= e(fmt_date($r['renewal'])) ?><br><span class="small muted"><?= $r['days'] === 0 ? 'hoje' : 'em ' . (int) $r['days'] . ' ' . ($r['days'] === 1 ? 'dia' : 'dias') ?></span></td>
                    <td class="num" data-label="Última consulta"><?php if ($r['last']['date']): ?><?= e(fmt_date($r['last']['date'])) ?><br><span class="small muted"><?= e($srcLabel[$r['last']['source']] ?? '') ?></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                    <td data-label="Onde está"><span class="tag<?= $r['answer'] === 'nao_renova' ? ' bad' : ($r['answer'] === 'renova' ? ' ok' : '') ?>"><?= e($r['stage']) ?></span></td>
                    <td class="small" data-label="Próximo passo"><?php if ($nx): $meta = Journey::TASKS[$nx['code']] ?? [$nx['code']]; ?><?= e($meta[0]) ?><br><span class="muted"><?= e($nx['assignee'] ?: 'Sem responsável') ?> · <?= e(fmt_date(substr((string) $nx['due_on'], 0, 10))) ?></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>

<?php else: ?>
    <p class="muted small" style="margin:0 0 12px"><strong>Vencido</strong> = o plano venceu e ninguém decidiu ainda. Renove, ou tire da carteira com o motivo. Ele não vira inativo sozinho. Quem já saiu está na <a href="<?= e(url('/admin/pacientes?f=exited')) ?>">base de reativação</a>.</p>
    <?php if (!$expired): ?>
        <div class="panel"><p class="muted" style="margin:0">Nenhum plano vencido esperando decisão.</p></div>
    <?php else: ?>
        <div class="tbl-wrap jr-tbl"><table class="tbl">
            <thead><tr><th>Paciente</th><th>Plano</th><th>Venceu</th><th>Resposta</th><th>Grupo</th><th>O que fazer</th></tr></thead>
            <tbody>
            <?php foreach ($expired as $r): $id = (int) $r['id']; ?>
                <tr>
                    <td data-label="Paciente"><a href="<?= e(url(PatientController::ficha($id, 'jornada'))) ?>"><strong><?= e($r['name']) ?></strong></a></td>
                    <td data-label="Plano"><?= e($r['plan_name'] ?? '-') ?></td>
                    <td class="num" data-label="Venceu"><?= e(fmt_date(substr((string) $r['renewal_date'], 0, 10))) ?><br><span class="small muted">há <?= (int) abs($r['days']) ?> <?= abs($r['days']) === 1 ? 'dia' : 'dias' ?></span></td>
                    <td data-label="Resposta"><?= $r['answer'] ? '<span class="tag' . ($r['answer'] === 'nao_renova' ? ' bad' : '') . '">' . e(Journey::ANSWERS[$r['answer']]) . '</span>' : '<span class="muted">-</span>' ?></td>
                    <td class="small" data-label="Grupo"><?= $r['group'] === 'done' ? 'Já saiu do grupo' : ($r['group'] === 'pending' ? '<span class="tag warn">Falta sair do grupo</span>' : ($r['group'] === 'prior' ? '<span class="muted">Anterior ao sistema</span>' : '<span class="muted">-</span>')) ?></td>
                    <td data-label="O que fazer">
                        <div class="jr-act">
                            <a class="btn btn-sm" href="<?= e(url(PatientController::ficha($id, 'plano', 'renovar'))) ?>">Renovar</a>
                            <details class="jr-exit">
                                <summary class="btn btn-ghost btn-danger btn-sm">Inativar…</summary>
                                <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/jornada/inativar')) ?>" data-confirm="Inativar este paciente? Ele perde o acesso ao app e vai para a base de reativação.">
                                    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
                                    <label class="sr-only" for="rs<?= $id ?>">Motivo da saída</label>
                                    <select id="rs<?= $id ?>" name="reason" required>
                                        <option value="">Motivo…</option>
                                        <?php foreach ($exitReasons as $k => $lab): ?><option value="<?= e($k) ?>"><?= e($lab) ?></option><?php endforeach; ?>
                                    </select>
                                    <label class="sr-only" for="rn<?= $id ?>">Observação</label>
                                    <input class="input" id="rn<?= $id ?>" name="note" maxlength="255" placeholder="Observação (opcional)">
                                    <button class="btn btn-ghost btn-danger btn-sm" type="submit">Inativar</button>
                                </form>
                            </details>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
<?php endif; ?>
