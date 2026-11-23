<?php
use App\Services\Leads;

$id = (int) $lead['id'];
$dnc = (int) $lead['do_not_contact'] === 1;
$ok = (int) $lead['contact_ok'] === 1;
$open = $lead['status'] === 'open';
$kinds = [
    'created' => ['plus', 'Lead cadastrado'], 'contact' => ['chat', 'Contato'], 'stage' => ['flag', 'Mudou de etapa'],
    'temperature' => ['star', 'Temperatura'], 'data' => ['pencil', 'Dados atualizados'], 'consent' => ['check', 'Autorização'], 'dnc' => ['x', 'Preferência de contato'],
];
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

<div class="lead-grid">
    <div class="lead-main">
        <section class="panel" id="contato">
            <h3>Registrar contato</h3>
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
                    <div class="field"><label for="c_stage">Etapa depois deste contato</label>
                        <select id="c_stage" name="stage_id">
                            <?php $sSel = old('stage_id', (string) $lead['stage_id']); foreach ($stages as $st): ?><option value="<?= (int) $st['id'] ?>"<?= $sSel === (string) $st['id'] ? ' selected' : '' ?>><?= e($st['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="field full"><label for="summary">Resumo do contato</label>
                        <textarea class="input" id="summary" name="summary" maxlength="600" style="min-height:80px" placeholder="O que foi conversado ou combinado?" required><?= e(old('summary')) ?></textarea></div>
                    <div class="field full"><label for="c_notes">Observações (opcional)</label><input class="input" id="c_notes" name="notes" maxlength="600" value="<?= e(old('notes')) ?>"></div>
                </div>
                <?php if ($dnc): ?>
                    <p class="hint">Lead marcado como "Não contatar": não dá para agendar novo retorno.</p>
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
                <div class="form-actions"><button class="btn" type="submit">Salvar contato</button></div>
            </form>
        </section>

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
                        <?php if ($e['kind'] === 'stage' || $e['kind'] === 'temperature'): ?>
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
            <p class="lead-next-lbl">Próximo retorno</p>
            <?php if ($dnc): ?><p><span class="st st-off">Não contatar</span></p>
            <?php elseif ($lead['next_on']): ?>
                <p class="lead-next"><span class="nx nx-<?= e($next['cls']) ?>"><?= e($next['text']) ?></span></p>
                <p class="small muted" style="margin-top:-4px"><?= e(fmt_date($lead['next_on'])) ?><?= $lead['next_type'] && isset(Leads::ACTIONS[$lead['next_type']]) ? ' · ' . e(Leads::ACTIONS[$lead['next_type']]) : '' ?><?= $lead['next_responsible_name'] ? ' · ' . e($lead['next_responsible_name']) : '' ?></p>
            <?php else: ?><p class="muted">Sem retorno agendado. Registre um contato e marque o próximo retorno.</p><?php endif; ?>
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
