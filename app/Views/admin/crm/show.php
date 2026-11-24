<?php
use App\Services\Leads;

$id = (int) $lead['id'];
$dnc = (int) $lead['do_not_contact'] === 1;
$ok = (int) $lead['contact_ok'] === 1;
$open = $lead['status'] === 'open';
$kinds = [
    'created' => ['plus', 'Lead cadastrado'], 'contact' => ['chat', 'Contato'], 'stage' => ['flag', 'Mudou de etapa'],
    'temperature' => ['star', 'Temperatura'], 'data' => ['pencil', 'Dados atualizados'], 'consent' => ['check', 'Autorização'], 'dnc' => ['x', 'Preferência de contato'],
    'assign' => ['users', 'Responsável'], 'schedule' => ['calendar', 'Follow-up agendado'],
    'lost' => ['x', 'Marcado como perdido'], 'recovery' => ['refresh', 'Reativação'], 'reopen' => ['refresh', 'Lead reaberto'], 'converted' => ['trophy', 'Convertido em paciente'],
];
$lost = $lead['status'] === 'lost';
$won = $lead['status'] === 'won';
$elig = Leads::recoveryEligible($lead);
$canAssign = $open || $lost;
$canSchedule = $open || ($lost && $elig);
$curNext = $lead['next_on'] ?: '';
$nextAct = $lead['next_type'] && isset(Leads::ACTIONS[$lead['next_type']]) ? Leads::ACTIONS[$lead['next_type']] : '';
$suggest = Leads::SUGGEST_DAYS[$lead['temperature']] ?? 3;
$contact = array_filter([$lead['phone'], $lead['email'], $lead['instagram'] ? '@' . $lead['instagram'] : '']);
?>
<div class="page-head lead-head">
    <div class="lead-title">
        <a class="small back-link" href="<?= e(url('/admin/crm')) ?>">← Voltar para o CRM</a>
        <h2><?= e($lead['name']) ?></h2>
        <div class="lead-tags">
            <span class="temp temp-<?= e($lead['temperature']) ?>"><?= e(Leads::TEMPS[$lead['temperature']] ?? $lead['temperature']) ?></span>
            <span class="plan-pill"><?= e($lead['stage_name']) ?></span>
            <?php if ($dnc): ?><span class="st st-off">Não contatar</span><?php endif; ?>
            <?php if ($lead['source_name']): ?><span class="small muted">Origem: <?= e($lead['source_name']) ?></span><?php endif; ?>
            <?php if ($lead['responsible_name']): ?><span class="small muted">Responsável: <?= e($lead['responsible_name']) ?></span><?php elseif ($canAssign): ?><span class="fu fu-none">Sem responsável</span><?php endif; ?>
            <span class="small muted">Entrou em <?= e(fmt_date($lead['entered_on'])) ?></span>
        </div>
    </div>
    <div class="row wrap" style="gap:8px">
        <?php if ($wa): ?><a class="btn btn-sm" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('chat', 18) ?> WhatsApp com mensagem pronta</a><?php endif; ?>
        <?php if ($lead['instagram']): ?><a class="btn btn-ghost btn-sm" href="<?= e(\App\Services\PersonalData::instagramUrl($lead['instagram'])) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/crm/' . $id . '/editar')) ?>"><?= icon('pencil', 18) ?> Editar dados</a>
    </div>
</div>

<?php if ($dnc): ?>
<div class="panel dnc-banner" role="status"><strong>Não contatar.</strong> Este lead pediu para não ser procurado: o botão de WhatsApp e novos retornos ficam bloqueados. Se ele mesmo escrever, você pode registrar o contato na linha do tempo.</div>
<?php endif; ?>

<?php if ($lost): ?>
<div class="panel lost-banner" role="status">
    <p style="margin:0"><strong>Lead perdido</strong> em <?= e(fmt_date($lead['lost_at'])) ?> (<?= e(Leads::agoText($lead['lost_at'])) ?>)<?= $lead['lost_reason_name'] ? ' · motivo: <strong>' . e($lead['lost_reason_name']) . '</strong>' : '' ?><?= $lead['lost_note'] ? ' · ' . e($lead['lost_note']) : '' ?><?= $lead['lost_note'] && preg_match('/[.!?…]\s*$/u', (string) $lead['lost_note']) ? '' : '.' ?> Ele não aparece mais nas oportunidades abertas.</p>
    <p class="small" style="margin:6px 0 0"><?php
        $stt = $rec['key'];
        if ($stt === 'ready') { echo '<strong>Pronto para contato:</strong> tentativa ' . (int) $rec['step'] . ' de ' . (int) $rec['steps'] . ', prevista para ' . e(fmt_date($lead['next_on'])) . '.'; }
        elseif ($stt === 'waiting') { echo '<strong>Reativação programada:</strong> tentativa ' . (int) $rec['step'] . ' de ' . (int) $rec['steps'] . ' em ' . e(fmt_date($lead['next_on'])) . '.'; }
        elseif ($stt === 'unscheduled') { echo '<strong>Elegível, sem reativação programada.</strong> Use "Programar reativação".'; }
        elseif ($stt === 'done') { echo '<strong>Reativação concluída</strong> (cadência de ' . count($recDays) . ' tentativas). Se quiser tentar de novo, use "Programar nova reativação".'; }
        elseif ($stt === 'noauth') { echo '<strong>Sem autorização para novos contatos:</strong> a reativação não é programada. Marque a autorização no quadro "Contato e consentimento" só se ele aceitou.'; }
        else { echo '<strong>Não contatar:</strong> fora da reativação.'; }
    ?></p>
</div>
<?php elseif ($won): ?>
<div class="panel won-banner" role="status">
    <p style="margin:0"><strong>Virou paciente</strong> em <?= e(fmt_date($lead['converted_at'])) ?><?php if ($lead['patient_id']): ?> · <a href="<?= e(url(\App\Controllers\Admin\PatientController::ficha((int) $lead['patient_id']))) ?>">Abrir a ficha de <?= e((string) ($lead['patient_name'] ?? $lead['name'])) ?></a><?php endif; ?>.</p>
    <?php if ($conv): ?><p class="small" style="margin:6px 0 0">Plano <strong><?= e((string) $conv['plan_name']) ?></strong> · <?= e(money((float) $conv['contracted_value'], 2, (string) $conv['currency'])) ?><?= $conv['payment_conditions'] ? ' · ' . e($conv['payment_conditions']) : '' ?><?= $conv['mode'] === 'link' ? ' · vinculado a paciente que já existia' : '' ?>. A venda está em Financeiro.</p><?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$won): ?>
<div class="lead-actions" role="group" aria-label="Ações do lead">
    <a class="btn btn-sm" href="#contato"><?= icon('chat', 18) ?> <?= $lost ? 'Registrar tentativa' : 'Registrar interação' ?></a>
    <?php if ($canSchedule): ?><a class="btn btn-ghost btn-sm" href="#retorno"><?= icon('calendar', 18) ?> <?= $lost ? 'Agendar tentativa' : 'Agendar follow-up' ?></a><?php endif; ?>
    <?php if ($lost && $elig && in_array($rec['key'], ['unscheduled', 'done'], true)): ?>
        <form method="post" action="<?= e(url('/admin/crm/' . $id . '/reativar')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh', 18) ?> <?= $rec['key'] === 'done' ? 'Programar nova reativação' : 'Programar reativação' ?></button></form>
    <?php endif; ?>
    <?php if ($lost): ?><a class="btn btn-ghost btn-sm" href="#reabrir"><?= icon('refresh', 18) ?> Reabrir lead</a><?php endif; ?>
    <a class="btn btn-sm" href="<?= e(url('/admin/crm/' . $id . '/converter')) ?>"><?= icon('trophy', 18) ?> Converter em paciente</a>
</div>
<?php endif; ?>

<div class="lead-grid">
    <div class="lead-main">
        <?php if (!$won): ?>
        <section class="panel" id="contato">
            <h3><?= $lost ? 'Registrar tentativa de reativação' : 'Registrar contato' ?></h3>
            <?php if ($lost && !$elig): ?><p class="muted small" style="margin-top:-6px">Este lead não está elegível para reativação (<?= $dnc ? 'Não contatar' : 'sem autorização para novos contatos' ?>): se ele mesmo escreveu, o contato fica registrado, sem nova tentativa.</p><?php endif; ?>
            <form method="post" action="<?= e(url('/admin/crm/' . $id . '/contato')) ?>">
                <?= csrf_field() ?>
                <div class="form-grid lc-grid">
                    <div class="field"><label for="happened_date">Quando</label>
                        <div class="row" style="gap:8px"><input class="input" type="date" id="happened_date" name="happened_date" max="<?= e(today()) ?>" value="<?= e(old('happened_date', today())) ?>">
                            <input class="input" type="time" id="happened_time" name="happened_time" aria-label="Hora" value="<?= e(old('happened_time', date('H:i'))) ?>"></div></div>
                    <div class="field"><label for="c_channel">Canal</label>
                        <?php $chSel = old('channel', (string) $lead['channel']); ?>
                        <select id="c_channel" name="channel"><option value="">Não informado</option>
                            <?php foreach (Leads::CHANNELS as $k => $label): ?><option value="<?= e($k) ?>"<?= $chSel === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="c_result">Resultado</label>
                        <select id="c_result" name="result"><option value="">Não informado</option>
                            <?php foreach (Leads::RESULTS as $k => $label): ?><option value="<?= e($k) ?>"<?= old('result') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <?php if ($open): ?>
                    <div class="field"><label for="c_stage">Etapa depois deste contato</label>
                        <select id="c_stage" name="stage_id">
                            <?php $sSel = old('stage_id', (string) $lead['stage_id']); foreach ($stages as $st): ?><option value="<?= (int) $st['id'] ?>"<?= $sSel === (string) $st['id'] ? ' selected' : '' ?>><?= e($st['name']) ?></option><?php endforeach; ?></select></div>
                    <?php elseif ($lost && $elig): ?>
                    <div class="field"><label for="c_after">Depois desta tentativa</label>
                        <select id="c_after" name="after">
                            <option value="cadence">Seguir a cadência</option>
                            <option value="stop">Encerrar a reativação</option>
                        </select></div>
                    <?php endif; ?>
                    <div class="field full"><label for="summary">Resumo do contato</label>
                        <textarea class="input" id="summary" name="summary" maxlength="600" style="min-height:80px" placeholder="O que foi conversado ou combinado?" required><?= e(old('summary')) ?></textarea></div>
                    <div class="field full"><label for="c_notes">Observações (opcional)</label><input class="input" id="c_notes" name="notes" maxlength="600" value="<?= e(old('notes')) ?>"></div>
                </div>
                <?php if ($dnc): ?>
                    <p class="hint">Lead marcado como "Não contatar": não dá para agendar novo retorno.</p>
                <?php elseif ($lost): ?>
                    <p class="hint">A próxima tentativa segue a cadência da reativação (<?= e(implode(', ', $recDays)) ?> dias depois da perda).</p>
                <?php else: ?>
                <fieldset class="next-box">
                    <legend>Próximo retorno</legend>
                    <div class="form-grid lc-grid">
                        <div class="field"><label for="next_days">Em quantos dias?</label>
                            <input class="input" type="number" id="next_days" name="next_days" min="0" max="365" value="<?= e(old('next_days', (string) $suggest)) ?>" inputmode="numeric">
                            <p class="hint">Sugestão pela temperatura (<?= e(mb_strtolower(Leads::TEMPS[$lead['temperature']] ?? '')) ?>): <?= (int) $suggest ?> <?= $suggest === 1 ? 'dia' : 'dias' ?>. Apague o número para não agendar.</p></div>
                        <div class="field"><label for="next_date">…ou escolha a data</label><input class="input" type="date" id="next_date" name="next_date" min="<?= e(today()) ?>" value="<?= e(old('next_date')) ?>">
                            <p class="hint">Se preencher a data, ela vale no lugar dos dias.</p></div>
                        <div class="field"><label for="next_type">Tipo de ação</label>
                            <select id="next_type" name="next_type"><?php $ntSel = old('next_type', 'followup'); foreach (Leads::ACTIONS as $k => $label): ?><option value="<?= e($k) ?>"<?= $ntSel === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label for="next_responsible_id">Quem faz o retorno</label>
                            <?php $nrSel = old('next_responsible_id', (string) $lead['next_responsible_id'] ?: (string) $lead['responsible_id']); ?>
                            <select id="next_responsible_id" name="next_responsible_id"><option value="">Ninguém definido</option>
                                <?php foreach ($responsibles as $rp): ?><option value="<?= (int) $rp['id'] ?>"<?= $nrSel === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?></select></div>
                    </div>
                </fieldset>
                <?php endif; ?>
                <div class="form-actions"><button class="btn" type="submit"><?= $lost ? 'Salvar tentativa' : 'Salvar contato' ?></button></div>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($canSchedule): ?>
        <section class="panel" id="retorno">
            <h3><?= $lost ? 'Agendar tentativa de reativação' : 'Agendar follow-up' ?></h3>
            <?php if ($dnc): ?>
                <p class="hint">Lead marcado como "Não contatar": não dá para agendar retorno. Libere o contato no quadro "Contato e consentimento" para voltar a agendar.</p>
                <?php if ($curNext): ?><p class="small muted">Retorno atual: <?= e(fmt_date($curNext)) ?>.</p><?php endif; ?>
            <?php else: ?>
            <p class="muted small" style="margin-top:-6px">Marca o próximo retorno <strong>sem registrar um contato</strong>: escolha em quantos dias (ou a data) e quem faz a tarefa.
                <?php if ($curNext): ?>Retorno atual: <strong><?= e(fmt_date($curNext)) ?></strong><?= $nextAct !== '' ? ' · ' . e($nextAct) : '' ?><?= $lead['next_responsible_name'] ? ' · tarefa de ' . e($lead['next_responsible_name']) : '' ?>.<?php endif; ?></p>
            <form method="post" action="<?= e(url('/admin/crm/' . $id . '/retorno')) ?>">
                <?= csrf_field() ?>
                <div class="form-grid lc-grid">
                    <div class="field"><label for="f_days">Em quantos dias?</label>
                        <input class="input" type="number" id="f_days" name="next_days" min="0" max="365" inputmode="numeric" value="<?= e(old('next_days', $curNext ? '' : (string) $suggest)) ?>">
                        <p class="hint"><?= $curNext ? 'Deixe em branco (e sem data) para manter ' . e(fmt_date($curNext)) . ' e só trocar a ação ou quem faz.' : 'Sugestão pela temperatura: ' . (int) $suggest . ($suggest === 1 ? ' dia' : ' dias') . '.' ?></p></div>
                    <div class="field"><label for="f_date">…ou escolha a data</label><input class="input" type="date" id="f_date" name="next_date" min="<?= e(today()) ?>" value="<?= e(old('next_date')) ?>">
                        <p class="hint">Se preencher a data, ela vale no lugar dos dias.</p></div>
                    <div class="field"><label for="f_type">Tipo de ação</label>
                        <select id="f_type" name="next_type"><?php $ftSel = old('next_type', $lead['next_type'] ?: 'followup'); foreach (Leads::ACTIONS as $k => $label): ?><option value="<?= e($k) ?>"<?= $ftSel === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="f_resp">Quem faz a tarefa</label>
                        <?php $frSel = old('next_responsible_id', (string) ($lead['next_responsible_id'] ?: $lead['responsible_id'])); ?>
                        <select id="f_resp" name="next_responsible_id"><option value="">Ninguém definido</option>
                            <?php foreach ($respTask as $rp): if (!$rp['active'] && (string) $rp['id'] !== $frSel) { continue; } ?><option value="<?= (int) $rp['id'] ?>"<?= $frSel === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?></select></div>
                    <div class="field full"><label for="f_note">Observação (opcional)</label><input class="input" id="f_note" name="note" maxlength="600" value="<?= e(old('note')) ?>" placeholder="Ex.: pediu para falar depois do treino"></div>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit"><?= $curNext ? 'Salvar retorno' : 'Agendar retorno' ?></button>
                    <?php if ($curNext): ?><button class="btn btn-ghost" type="submit" name="clear" value="1" formnovalidate>Remover retorno</button><?php endif; ?>
                </div>
            </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="panel" id="linha-do-tempo">
            <h3>Linha do tempo</h3>
            <p class="muted small" style="margin-top:-6px">Tudo o que aconteceu com este lead. Os registros não podem ser editados nem apagados: se algo estiver errado, registre um novo contato corrigindo.</p>
            <ol class="tl">
            <?php foreach ($events as $e): [$ic, $title] = $kinds[$e['kind']] ?? ['list', 'Registro'];
                if ($e['kind'] === 'contact' && $e['channel'] && isset(Leads::CHANNELS[$e['channel']])) { $title = 'Contato · ' . Leads::CHANNELS[$e['channel']]; } ?>
                <li class="tl-item tl-<?= e($e['kind']) ?>">
                    <span class="tl-dot" aria-hidden="true"><?= icon($ic, 16) ?></span>
                    <div class="tl-body">
                        <div class="tl-head"><strong><?= e($title) ?></strong>
                            <span class="small muted"><?= e(fmt_datetime($e['happened_at'])) ?><?= $e['by_name'] ? ' · ' . e($e['by_name']) : '' ?><?= $e['source'] !== 'manual' ? ' · ' . e($e['source']) : '' ?></span></div>
                        <?php if ($e['kind'] === 'stage' || $e['kind'] === 'temperature' || $e['kind'] === 'assign'): ?>
                            <p><?= e($e['from_value'] ?? '') ?> <span aria-hidden="true">→</span><span class="sr-only">para</span> <strong><?= e($e['to_value'] ?? '') ?></strong></p>
                        <?php else: ?>
                            <?php if ($e['summary']): ?><p><?= nl2br(e($e['summary'])) ?></p><?php endif; ?>
                        <?php endif; ?>
                        <?php if ($e['kind'] === 'created' && $e['to_value']): ?><p class="small muted">Etapa inicial: <?= e($e['to_value']) ?></p><?php endif; ?>
                        <?php if ($e['result'] && isset(Leads::RESULTS[$e['result']])): ?><span class="tag"><?= e(Leads::RESULTS[$e['result']]) ?></span><?php endif; ?>
                        <?php if ($e['notes']): ?><p class="small muted"><?= nl2br(e($e['notes'])) ?></p><?php endif; ?>
                        <?php if ($e['next_action']): ?><p class="small tl-next">Próxima ação: <strong><?= e($e['next_action']) ?></strong></p><?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <aside class="lead-side">
        <section class="panel">
            <h3>Situação</h3>
            <?php if ($won): ?>
            <p class="lead-next-lbl">Situação</p>
            <p class="lead-fu"><span class="st st-ok">Virou paciente</span></p>
            <p class="small muted" style="margin:0">Em <?= e(fmt_datetime($lead['converted_at'])) ?>. O acompanhamento agora é feito na ficha do paciente.</p>
            <?php else: ?>
            <p class="lead-next-lbl"><?= $lost ? 'Reativação' : 'Follow-up' ?></p>
            <?php $recCls = ['ready' => 'fu-today', 'waiting' => 'fu-scheduled', 'unscheduled' => 'fu-none', 'done' => 'fu-dnc', 'noauth' => 'fu-none', 'dnc' => 'fu-dnc']; ?>
            <p class="lead-fu"><?php if ($fu['state'] === 'dnc'): ?><span class="st st-off">Não contatar</span><?php elseif ($lost): ?><span class="fu fu-big <?= e($recCls[$rec['key']] ?? 'fu-none') ?>"><?= e($rec['label']) ?></span><?php else: ?><span class="fu fu-big fu-<?= e($fu['state']) ?>"><?= e($fu['label']) ?></span><?php endif; ?></p>
            <?php if ($lost && in_array($rec['key'], ['ready', 'waiting'], true) && $lead['next_on']): ?>
                <p class="lead-fu-line"><strong class="nx nx-<?= e($rec['key'] === 'ready' ? 'today' : $fu['cls']) ?>"><?= e($rec['key'] === 'ready' && $fu['state'] !== 'today' ? 'pronto desde ' . substr(fmt_date($lead['next_on']), 0, 5) : $fu['text']) ?></strong> <span class="muted">· <?= e(fmt_date($lead['next_on'])) ?></span></p>
                <p class="small muted" style="margin:0">Tentativa <?= (int) $rec['step'] ?> de <?= (int) $rec['steps'] ?> · Tarefa de: <?= $lead['next_responsible_name'] ? e($lead['next_responsible_name']) : 'ninguém definido' ?></p>
            <?php elseif ($lost): ?>
                <p class="muted small" style="margin:0"><?= $rec['key'] === 'done' ? 'A cadência de reativação terminou. Use "Programar nova reativação" se quiser tentar de novo.' : ($rec['key'] === 'unscheduled' ? 'Ele autorizou novos contatos, mas ainda não há tentativa programada. Use "Programar reativação".' : ($rec['key'] === 'noauth' ? 'Sem autorização para novos contatos: não há reativação programada.' : 'Está como "Não contatar": fora da reativação.')) ?></p>
            <?php elseif (in_array($fu['state'], ['late', 'today', 'scheduled'], true)): ?>
                <p class="lead-fu-line"><strong class="nx nx-<?= e($fu['cls']) ?>"><?= e($fu['text']) ?></strong> <span class="muted">· <?= e(fmt_date($lead['next_on'])) ?></span></p>
                <p class="small muted" style="margin:0">Ação: <?= $nextAct !== '' ? e($nextAct) : 'retomar contato' ?> · Tarefa de: <?= $lead['next_responsible_name'] ? e($lead['next_responsible_name']) : 'ninguém definido' ?></p>
            <?php elseif ($fu['state'] === 'none'): ?>
                <p class="muted small" style="margin:0">Nenhum retorno marcado. Registre um contato ou use "Agendar follow-up" para definir o próximo.</p>
            <?php else: ?>
                <p class="muted small" style="margin:0">Novos retornos ficam bloqueados enquanto o lead estiver como "Não contatar".</p>
            <?php endif; ?>
            <?php endif; ?>
            <p class="small muted" style="margin:8px 0 0">Último contato: <?= $lead['last_contact_at'] ? e(fmt_datetime($lead['last_contact_at'])) . ' (' . e(Leads::agoText($lead['last_contact_at'])) . ')' : 'nenhum ainda' ?></p>

            <?php if ($open): ?>
            <form class="side-form" method="post" action="<?= e(url('/admin/crm/' . $id . '/etapa')) ?>">
                <?= csrf_field() ?>
                <label for="s_stage">Etapa do funil</label>
                <div class="row" style="gap:8px">
                    <select id="s_stage" name="stage_id"><?php foreach ($stages as $st): ?><option value="<?= (int) $st['id'] ?>"<?= (int) $st['id'] === (int) $lead['stage_id'] ? ' selected' : '' ?>><?= e($st['name']) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-ghost btn-sm" type="submit">Mover</button>
                </div>
            </form>
            <form class="side-form" method="post" action="<?= e(url('/admin/crm/' . $id . '/temperatura')) ?>">
                <?= csrf_field() ?>
                <label for="s_temp">Temperatura</label>
                <div class="row" style="gap:8px">
                    <select id="s_temp" name="temperature"><?php foreach (Leads::TEMPS as $k => $label): ?><option value="<?= e($k) ?>"<?= $lead['temperature'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-ghost btn-sm" type="submit">Atualizar</button>
                </div>
            </form>
            <?php endif; ?>
            <?php if ($canAssign): $activeResp = array_filter($responsibles, fn($x) => $x['active']); ?>
            <form class="side-form" method="post" action="<?= e(url('/admin/crm/' . $id . '/responsavel')) ?>">
                <?= csrf_field() ?>
                <label for="s_resp">Responsável pelo lead</label>
                <div class="row" style="gap:8px">
                    <select id="s_resp" name="responsible_id">
                        <option value="0"<?= $lead['responsible_id'] ? '' : ' selected' ?>>Sem responsável</option>
                        <?php foreach ($responsibles as $rp): if (!$rp['active'] && (int) $rp['id'] !== (int) $lead['responsible_id']) { continue; } ?><option value="<?= (int) $rp['id'] ?>"<?= (int) $rp['id'] === (int) $lead['responsible_id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-ghost btn-sm" type="submit">Atribuir</button>
                </div>
                <?php if (!$activeResp): ?><p class="hint" style="margin:6px 0 0">Nenhum responsável cadastrado. <a href="<?= e(url('/admin/responsaveis/novo')) ?>">Cadastre o Gabriel e o Comercial</a> para poder atribuir.</p><?php endif; ?>
            </form>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h3>Dados</h3>
            <dl class="dl">
                <dt>WhatsApp</dt><dd><?= $lead['phone'] ? e($lead['phone']) : '<span class="muted">-</span>' ?></dd>
                <dt>E-mail</dt><dd><?= $lead['email'] ? e($lead['email']) : '<span class="muted">-</span>' ?></dd>
                <dt>Instagram</dt><dd><?= $lead['instagram'] ? '<a href="' . e(\App\Services\PersonalData::instagramUrl($lead['instagram'])) . '" target="_blank" rel="noopener">@' . e($lead['instagram']) . '</a>' : '<span class="muted">-</span>' ?></dd>
                <dt>Modalidade</dt><dd><?= $lead['sport_name'] ? e($lead['sport_name']) : '<span class="muted">-</span>' ?></dd>
                <dt>Plano de interesse</dt><dd><?= $lead['plan_name'] ? e($lead['plan_name']) : '<span class="muted">-</span>' ?></dd>
                <dt>Objetivo</dt><dd><?= $lead['goal'] ? e($lead['goal']) : '<span class="muted">-</span>' ?></dd>
                <dt>Origem</dt><dd><?= $lead['source_name'] ? e($lead['source_name']) : '<span class="muted">-</span>' ?></dd>
                <dt>Canal</dt><dd><?= $lead['channel'] && isset(Leads::CHANNELS[$lead['channel']]) ? e(Leads::CHANNELS[$lead['channel']]) : '<span class="muted">-</span>' ?></dd>
                <?php if ($lead['referred_by']): ?><dt>Indicado por</dt><dd><a href="<?= e(url('/admin/pacientes/' . (int) $lead['referred_by'])) ?>"><?= e((string) $lead['referred_name']) ?></a></dd><?php endif; ?>
                <dt>Responsável</dt><dd><?= $lead['responsible_name'] ? e($lead['responsible_name']) : '<span class="muted">-</span>' ?></dd>
                <dt>Cadastrado por</dt><dd><?= $lead['created_name'] ? e($lead['created_name']) : '<span class="muted">-</span>' ?></dd>
            </dl>
            <?php if ($lead['notes']): ?><p class="small" style="margin:10px 0 0"><strong>Observações:</strong> <?= nl2br(e($lead['notes'])) ?></p><?php endif; ?>
        </section>

        <?php if (!$won): ?>
        <section class="panel">
            <h3>Contato e consentimento</h3>
            <?php if (!$dnc): ?>
            <form method="post" action="<?= e(url('/admin/crm/' . $id . '/autorizacao')) ?>" class="side-form first">
                <?= csrf_field() ?>
                <?php if ($ok): ?>
                    <p class="small" style="margin:0 0 8px"><span class="st st-ok">Autorizou novos contatos</span> <span class="muted">em <?= e(fmt_date($lead['contact_ok_at'])) ?></span></p>
                    <button class="btn btn-ghost btn-sm" type="submit">Remover autorização</button>
                <?php else: ?>
                    <input type="hidden" name="on" value="1">
                    <p class="small muted" style="margin:0 0 8px">Marque só se o lead disse que aceita receber novos contatos. Isso será usado na reativação.</p>
                    <button class="btn btn-ghost btn-sm" type="submit">Marcar: autorizou novos contatos</button>
                <?php endif; ?>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/admin/crm/' . $id . '/nao-contatar')) ?>" class="side-form"<?= $dnc ? '' : ' data-confirm="Marcar como Não contatar? O retorno agendado será cancelado e o botão de WhatsApp fica bloqueado."' ?>>
                <?= csrf_field() ?>
                <?php if ($dnc): ?><button class="btn btn-ghost btn-sm" type="submit">Liberar contato novamente</button>
                <?php else: ?><input type="hidden" name="on" value="1"><button class="btn btn-ghost btn-sm" type="submit">Marcar como "Não contatar"</button><?php endif; ?>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($open): ?>
        <section class="panel" id="perder">
            <h3>Marcar como perdido</h3>
            <details class="lead-more"<?= isset($_GET['perder']) ? ' open' : '' ?>>
                <summary>Registrar a perda deste lead</summary>
                <form method="post" action="<?= e(url('/admin/crm/' . $id . '/perder')) ?>" class="side-form first" style="padding-top:8px">
                    <?= csrf_field() ?>
                    <p class="muted small" style="margin:0 0 8px">O lead sai das oportunidades abertas e passa para a tela Reativação. Dá para reabrir depois.</p>
                    <label for="l_reason">Motivo da perda</label>
                    <select id="l_reason" name="reason_id" required style="width:100%;margin-bottom:8px">
                        <option value="">Escolha o motivo…</option>
                        <?php foreach ($reasons as $rs): ?><option value="<?= (int) $rs['id'] ?>"><?= e($rs['name']) ?></option><?php endforeach; ?>
                    </select>
                    <label for="l_note">Observação (opcional)</label>
                    <input class="input" id="l_note" name="note" maxlength="300" style="margin-bottom:8px">
                    <?php if (!$dnc): ?>
                    <label class="checkbox small mb"><input type="checkbox" name="contact_ok" value="1"<?= $ok ? ' checked' : '' ?>><span>O lead aceita receber novos contatos no futuro (reativação)</span></label>
                    <?php else: ?><p class="small muted">Está como "Não contatar": não entra na reativação.</p><?php endif; ?>
                    <button class="btn btn-ghost btn-sm" type="submit">Marcar como perdido</button>
                </form>
            </details>
        </section>
        <?php endif; ?>

        <?php if ($lost): ?>
        <section class="panel" id="reabrir">
            <h3>Reabrir lead</h3>
            <form method="post" action="<?= e(url('/admin/crm/' . $id . '/reabrir')) ?>" class="side-form first">
                <?= csrf_field() ?>
                <p class="muted small" style="margin:0 0 8px">Ele voltou a se interessar? O lead volta para as oportunidades abertas, na etapa escolhida, com retorno para hoje.</p>
                <label for="r_stage">Voltar na etapa</label>
                <select id="r_stage" name="stage_id" required style="width:100%;margin-bottom:8px">
                    <?php foreach ($openStages as $st): ?><option value="<?= (int) $st['id'] ?>"><?= e($st['name']) ?></option><?php endforeach; ?>
                </select>
                <label for="r_note">Observação (opcional)</label>
                <input class="input" id="r_note" name="note" maxlength="300" style="margin-bottom:8px" placeholder="Ex.: pediu proposta de novo">
                <button class="btn btn-ghost btn-sm" type="submit">Reabrir lead</button>
            </form>
        </section>
        <?php endif; ?>

        <section class="panel">
            <h3>Privacidade (LGPD)</h3>
            <form method="post" action="<?= e(url('/admin/crm/' . $id . '/excluir')) ?>" data-confirm="Excluir DEFINITIVAMENTE este lead e toda a linha do tempo? Não dá para desfazer.">
                <?= csrf_field() ?>
                <p class="muted small" style="margin-top:0">Pedido de exclusão feito pela própria pessoa: apaga o lead e toda a linha do tempo dele. Fica só o registro na Auditoria.</p>
                <label class="checkbox small mb"><input type="checkbox" name="confirm" value="1" required><span>Entendo que não dá para desfazer.</span></label>
                <button class="btn btn-ghost btn-danger btn-sm" type="submit">Excluir lead e histórico</button>
            </form>
        </section>
    </aside>
</div>
