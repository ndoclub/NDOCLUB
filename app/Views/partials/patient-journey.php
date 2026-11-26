<?php
/**
 * Aba "Jornada" da ficha do paciente (Atualização 11, parte 1: Renovação e Saída).
 * Recebe: $p (paciente), $j (Journey::forPatient), $id, $planStatus, $renew, $planRow
 * Só o admin vê. Nada daqui vai para o app do paciente.
 */
use App\Controllers\Admin\PatientController;
use App\Services\Journey;

$cfg = $j['cfg'];
$cyc = $j['cyc'];
$R = !empty($p['renewal_date']) ? substr((string) $p['renewal_date'], 0, 10) : null;
$hasPlan = !empty($p['plan_id']);
$active = $p['status'] === 'active';
$exited = $p['status'] === 'inactive' && !empty($p['exit_reason']);
$back = PatientController::ficha($id, 'jornada');
$kindLabel = ['tarefa' => 'Tarefa', 'resposta' => 'Resposta', 'consulta' => 'Consulta', 'saida' => 'Saída', 'volta' => 'Volta', 'renovou' => 'Renovação', 'nota' => 'Observação'];
$answer = $cyc['answer'] ?? null;
?>
<p class="muted small" style="margin:0 0 12px">Renovação e saída deste paciente: as tarefas nascem sozinhas a partir do plano e do vencimento. O sistema só lembra e registra; as mensagens você manda pelo WhatsApp. Só o admin vê esta aba.</p>

<div class="cols">
<div>
    <div class="panel">
        <h3>Plano e prazos</h3>
        <?php if (!$hasPlan || !$R): ?>
            <p class="muted small">Escolha o plano e a data de vencimento na aba Resumo para a Jornada gerar as tarefas deste paciente.</p>
        <?php else: ?>
            <dl class="jr-dl">
                <div><dt>Plano</dt><dd><a href="<?= e(url('/admin/planos/' . (int) $p['plan_id'])) ?>"><?= e($planRow['name'] ?? '') ?></a> · <?= e($j['typeLabel']) ?></dd></div>
                <div><dt>Vence em</dt><dd><?= e(fmt_date($R)) ?> <span class="small muted">(<?= e($renew['label']) ?>)</span></dd></div>
                <?php if ($j['cota'] !== null): ?><div><dt>Consultas</dt><dd><?= (int) $j['heldCount'] ?> de <?= (int) $j['cota'] ?> feitas<?= $j['withConsults'] && $j['interval'] ? ' · uma a cada ~' . (int) $j['interval'] . ' dias' : '' ?></dd></div><?php endif; ?>
                <div><dt>Tarefas deste plano</dt><dd><?= $j['total'] > 0 ? (int) $j['done'] . ' de ' . (int) $j['total'] . ' concluídas' : 'nenhuma ainda' ?></dd></div>
            </dl>
            <?php if (in_array($j['type'], ['premium', 'smart'], true) && $j['cota'] === null): ?>
                <p class="hint">O plano ainda não tem as consultas incluídas, então a Jornada usa só o contato de renovação <?= (int) $cfg['d_contato'] ?> dias antes do fim. <a href="<?= e(url('/admin/planos/' . (int) $p['plan_id'])) ?>">Defina em Planos</a> para ter o dossiê e a última consulta.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($j['withConsults'] && $R): ?>
    <div class="panel" id="ultima-consulta">
        <h3>Última consulta do plano</h3>
        <p style="margin:0 0 10px"><strong class="num"><?= e(fmt_date($j['last']['date'])) ?></strong>
            <span class="tag"><?= ['marcada' => 'marcada', 'feita' => 'já feita', 'estimada' => 'estimada'][$j['last']['source']] ?? '' ?></span></p>
        <form class="jr-inline" method="post" action="<?= e(url('/admin/pacientes/' . $id . '/jornada/ultima-consulta')) ?>">
            <?= csrf_field() ?>
            <div class="field"><label for="lc">Marcar o dia da última consulta</label><input class="input" type="date" id="lc" name="last_consult_on" value="<?= e((string) ($cyc['last_consult_on'] ?? '')) ?>"></div>
            <button class="btn btn-sm" type="submit">Salvar</button>
        </form>
        <p class="hint">Quem agenda é a Isabela. Em branco, o sistema estima: começo do plano + (consultas − 1) × intervalo. O dossiê é pedido <?= (int) $cfg['d_dossie'] ?> dias antes desse dia, e a resposta de renovação é registrada nele.</p>
    </div>
    <?php endif; ?>

    <div class="panel" id="resposta">
        <h3>Resposta de renovação</h3>
        <?php if ($answer): ?>
            <p style="margin:0 0 10px"><span class="tag <?= $answer === 'nao_renova' ? 'bad' : ($answer === 'renova' ? 'ok' : 'warn') ?>"><?= e(Journey::ANSWERS[$answer]) ?></span>
                <?php if ($answer === 'data_combinada' && !empty($cyc['agreed_on'])): ?> <span class="small muted">contato em <?= e(fmt_date((string) $cyc['agreed_on'])) ?></span><?php endif; ?>
                <?php if ($answer === 'vai_pensar' && !empty($cyc['next_consult_on'])): ?> <span class="small muted">consulta seguinte em <?= e(fmt_date((string) $cyc['next_consult_on'])) ?></span><?php endif; ?>
                <?php if (!empty($cyc['answer_on'])): ?> <span class="small muted">· registrada em <?= e(fmt_date((string) $cyc['answer_on'])) ?></span><?php endif; ?></p>
        <?php else: ?>
            <p class="muted small" style="margin:0 0 10px">Ainda sem resposta neste plano.</p>
        <?php endif; ?>
        <?php if ($active && $hasPlan && $R): ?>
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/jornada/resposta')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field full"><label for="ans">O que o paciente respondeu</label>
                    <select id="ans" name="answer" required>
                        <option value="">Escolha…</option>
                        <?php foreach (Journey::ANSWERS as $k => $lab): ?><option value="<?= e($k) ?>"<?= $answer === $k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="field"><label for="agreed">Data combinada</label><input class="input" type="date" id="agreed" name="agreed_on" min="<?= e(today()) ?>" value="<?= e((string) ($cyc['agreed_on'] ?? '')) ?>">
                    <p class="hint">Só para "Data combinada" (ex.: o cartão vira dia 10). A Isabela é lembrada nesse dia.</p></div>
                <div class="field"><label for="nextc">Consulta seguinte</label><input class="input" type="date" id="nextc" name="next_consult_on" min="<?= e(today()) ?>" value="<?= e((string) ($cyc['next_consult_on'] ?? '')) ?>">
                    <p class="hint">Só para "Vai pensar". A Isabela é lembrada <?= (int) $cfg['d_contato'] ?> dias antes dela.</p></div>
            </div>
            <div class="form-actions"><button class="btn btn-sm" type="submit">Registrar resposta</button>
                <a class="btn btn-ghost btn-sm" href="<?= e(url(PatientController::ficha($id, 'plano', 'renovar'))) ?>">Ir para Renovar</a></div>
        </form>
        <?php endif; ?>

        <?php if ($j['reajuste']): $rj = $j['reajuste']; ?>
        <h3 class="sub" style="margin:18px 0 8px">Valor na renovação</h3>
        <dl class="jr-dl">
            <div><dt>Valor atual</dt><dd class="num"><?= e(money($rj['now'])) ?></dd></div>
            <div><dt>Valor da tabela</dt><dd class="num"><?= e(money($rj['table'])) ?></dd></div>
            <div><dt>Diferença</dt><dd class="num"><?= $rj['diff'] > 0 ? '<span class="tag warn">' . e(money($rj['diff'])) . ' abaixo da tabela</span>' : ($rj['diff'] < 0 ? e(money(abs($rj['diff']))) . ' acima da tabela' : 'Na tabela') ?></dd></div>
        </dl>
        <?php if ($rj['diff'] > 0): ?><p class="hint">Quem paga abaixo da tabela sobe de <?= (int) $cfg['raise_min'] ?>% a <?= (int) $cfg['raise_max'] ?>% na renovação (referência do Gabriel): de <?= e(money($rj['min'])) ?> a <?= e(money($rj['max'])) ?>. Quem decide o valor é você, no campo "Valor combinado" do Resumo ou no Renovar.</p><?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div>
    <div class="panel" id="tarefas">
        <h3>Tarefas deste plano</h3>
        <?php if (!$j['tasks']): ?>
            <p class="muted small">Ainda não há tarefas. Elas nascem perto do prazo (até <?= (int) \App\Services\Journey::HORIZON_DAYS ?> dias antes) ou quando você registra uma resposta.</p>
        <?php else: ?>
            <div class="jr-list">
                <?php foreach ($j['tasks'] as $t): $t['patient'] = $p['name']; partial('journey-task-row', ['t' => $t, 'back' => $back, 'showPatient' => false]); endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel" id="saida">
        <h3>Saída</h3>
        <?php if ($exited): ?>
            <p style="margin:0 0 8px"><span class="tag">Inativo</span> <span class="small muted">desde <?= e(fmt_date((string) $p['exited_on'])) ?></span></p>
            <p class="small" style="margin:0 0 6px">Motivo: <strong><?= e(Journey::EXIT_REASONS[$p['exit_reason']] ?? $p['exit_reason']) ?></strong><?= !empty($p['exit_note']) ? ' · ' . e($p['exit_note']) : '' ?></p>
            <?php if (!empty($p['recontact_on'])): ?><p class="small" style="margin:0 0 6px">Recontato marcado para <strong><?= e(fmt_date((string) $p['recontact_on'])) ?></strong>.</p><?php endif; ?>
            <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/jornada/reativar')) ?>" data-confirm="Voltar este paciente para os ativos?">
                <?= csrf_field() ?>
                <div class="form-actions" style="margin-top:8px"><button class="btn btn-sm" type="submit">Voltar para os ativos</button></div>
            </form>
            <p class="hint">Quem volta faz a entrada de novo (grupo novo e Modo Babá). Se ele renovar pelo botão Renovar, volta sozinho para os ativos.</p>
        <?php else: ?>
            <?php if ($R && $R < today() && $active): ?><p class="small" style="margin:0 0 10px"><span class="tag bad">Vencido</span> O plano venceu e ninguém decidiu ainda: renove ou tire da carteira abaixo. Ele não vira inativo sozinho.</p><?php endif; ?>
            <?php if ($p['status'] === 'inactive'): ?><p class="small muted" style="margin:0 0 10px">Conta desativada, sem motivo de saída registrado.</p><?php endif; ?>
            <details class="jr-exit">
                <summary class="btn btn-ghost btn-danger btn-sm">Tirar da carteira (inativar)</summary>
                <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/jornada/inativar')) ?>" data-confirm="Inativar este paciente? Ele perde o acesso ao app e vai para a base de reativação.">
                    <?= csrf_field() ?>
                    <div class="form-grid" style="margin-top:12px">
                        <div class="field"><label for="reason">Motivo da saída</label>
                            <select id="reason" name="reason" required>
                                <option value="">Escolha…</option>
                                <?php foreach (Journey::EXIT_REASONS as $k => $lab): ?><option value="<?= e($k) ?>"><?= e($lab) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="field"><label for="xnote">Observação (opcional)</label><input class="input" id="xnote" name="note" maxlength="255" placeholder="Ex.: cancelou antes do fim"></div>
                    </div>
                    <div class="form-actions"><button class="btn btn-ghost btn-danger btn-sm" type="submit">Inativar com este motivo</button></div>
                </form>
            </details>
            <p class="hint">Use quando o time decidiu que o paciente saiu (venceu sem renovar, cancelou ou pediu pausa). A conta fica sem acesso ao app e ele vai para a base de reativação. <strong>Pausa</strong> marca o recontato para daqui a <?= (int) $cfg['d_pausa'] ?> dias.</p>
        <?php endif; ?>
    </div>

    <div class="panel" id="linha-do-tempo">
        <h3>Linha do tempo</h3>
        <form class="jr-inline" method="post" action="<?= e(url('/admin/pacientes/' . $id . '/jornada/nota')) ?>">
            <?= csrf_field() ?>
            <div class="field"><label for="jnote">Anotar uma observação</label><input class="input" id="jnote" name="text" maxlength="240" placeholder="Ex.: pediu para falar só depois do dia 10"></div>
            <button class="btn btn-ghost btn-sm" type="submit">Anotar</button>
        </form>
        <p class="hint" style="margin-top:4px">A linha do tempo nunca é editada nem apagada: se errar, anote a correção. Pode ter dado de saúde; só o admin vê.</p>
        <?php if (!$j['events']): ?>
            <p class="muted small">Nada registrado ainda.</p>
        <?php else: ?>
            <ul class="jr-tl">
                <?php foreach ($j['events'] as $ev): ?>
                    <li><span class="small muted"><?= e(fmt_datetime((string) $ev['happened_at'])) ?></span> <span class="tag"><?= e($kindLabel[$ev['kind']] ?? $ev['kind']) ?></span> <?= e($ev['summary']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
</div>
