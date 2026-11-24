<?php
use App\Services\Leads;

$cur = $f + ['pp' => $perPage];
$defaults = ['ord' => 'new', 'pp' => 25];
$link = function (array $over = []) use ($cur, $defaults) {
    $m = array_filter(array_merge($cur, $over), fn($v, $k) => $v !== '' && $v !== 0 && $v !== null && !(isset($defaults[$k]) && $defaults[$k] === $v), ARRAY_FILTER_USE_BOTH);
    return url('/admin/crm') . ($m ? '?' . http_build_query($m) : '');
};
$hasFilters = $f['q'] !== '' || $f['temp'] !== '' || $f['source'] !== '' || $f['stage'] !== '' || $f['sport'] !== '' || $f['plan'] !== '' || $f['resp'] !== '' || $f['task'] !== '' || $f['p'] !== '' || $f['f'] !== '';
$moreOn = ($f['resp'] !== '' || $f['task'] !== '' || $f['sport'] !== '' || $f['plan'] !== '' || $f['p'] !== '' || $f['ord'] !== 'new');
$won = $f['f'] === 'won';
// valores dos indicadores (sem casas decimais quando o valor é redondo)
$mny = fn(float $v, string $cur = 'BRL') => money($v, fmod($v, 1.0) == 0.0 ? 0 : 2, $cur);
$contracted = [];
foreach ($ind['contracted'] as $cur => $v) { $contracted[] = $mny((float) $v, $cur); }
$rate = $ind['rate'] === null ? '-' : rtrim(rtrim(number_format((float) $ind['rate'], 1, ',', ''), '0'), ',') . '%';
$activeResp = array_values(array_filter($respChoices, fn($x) => $x['active']));
$from = $total > 0 ? $offset + 1 : 0;
$to = min($total, $offset + $perPage);
$pageLink = fn(int $n) => $link(['pg' => $n]);
$nums = array_values(array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], fn($n) => $n >= 1 && $n <= $pages)));
sort($nums);
$quick = [
    '' => ['Em aberto', $counts['open']], 'today' => ['Retorno hoje', $counts['today']], 'late' => ['Retorno atrasado', $counts['late']],
    'sched' => ['Retorno agendado', $counts['sched']], 'none' => ['Sem retorno programado', $counts['none']], 'noresp' => ['Sem responsável', $counts['noresp']],
    'hot' => ['Quentes', $counts['hot']], 'dnc' => ['Não contatar', $counts['dnc']], 'won' => ['Convertidos', $counts['won']],
];
?>
<div class="page-head ppage-head">
    <div>
        <h2>CRM</h2>
        <p class="muted small" style="margin:4px 0 0">Quem ainda não é paciente: de onde veio, em que etapa está e quando falar de novo.</p>
    </div>
    <a class="btn btn-sm" href="<?= e(url('/admin/crm/novo')) ?>"><?= icon('plus', 18) ?> Novo lead</a>
</div>
<?php partial('crm-tabs', ['active' => 'leads']); ?>

<div class="pcards lcards" role="list" aria-label="Indicadores">
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm')) ?>">
        <span class="pic"><?= icon('users', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['open'] ?></span>
        <span class="plbl">Leads em aberto</span>
    </a>
    <div class="pcard" role="listitem">
        <span class="pic"><?= icon('calendar', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['new_month'] ?></span>
        <span class="plbl">Novos no mês</span>
    </div>
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm?f=hot')) ?>">
        <span class="pic"><?= icon('star', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['hot'] ?></span>
        <span class="plbl">Leads quentes</span>
    </a>
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm?f=late')) ?>">
        <span class="pic"><?= icon('clock', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['late'] ?></span>
        <span class="plbl">Retornos atrasados</span>
        <span class="trend flat"><?= (int) $counts['today'] > 0 ? (int) $counts['today'] . ' para hoje' : 'nenhum para hoje' ?></span>
    </a>
</div>
<div class="pcards lcards lcards-biz" role="list" aria-label="Resultados comerciais do mês">
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm?f=won')) ?>">
        <span class="pic"><?= icon('trophy', 22) ?></span>
        <span class="pbig num"><?= (int) $ind['converted'] ?></span>
        <span class="plbl">Leads convertidos no mês</span>
        <span class="trend flat"><?= (int) $counts['won'] ?> no total</span>
    </a>
    <div class="pcard" role="listitem" title="Dos leads que entraram neste mês, quantos já viraram paciente">
        <span class="pic"><?= icon('chart', 22) ?></span>
        <span class="pbig num"><?= e($rate) ?></span>
        <span class="plbl">Taxa de conversão</span>
        <span class="trend flat"><?= $ind['entered'] > 0 ? (int) $ind['cohort_won'] . ' de ' . (int) $ind['entered'] . ($ind['entered'] === 1 ? ' lead do mês' : ' leads do mês') : 'ninguém entrou neste mês' ?></span>
    </div>
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm?f=pot')) ?>" title="Soma do valor do plano de interesse dos leads em proposta e negociação (etapas marcadas em Configurar)">
        <span class="pic"><?= icon('clock', 22) ?></span>
        <span class="pbig num"><?= e($mny($ind['potential'])) ?></span>
        <span class="plbl">Receita potencial em negociação</span>
        <span class="trend flat"><?= (int) $ind['potential_n'] ?> <?= $ind['potential_n'] === 1 ? 'lead' : 'leads' ?> nas etapas finais<?= $ind['unpriced'] > 0 ? ' · ' . (int) $ind['unpriced'] . ' sem plano de interesse (fora da soma)' : '' ?></span>
    </a>
    <div class="pcard" role="listitem" title="Valor combinado nas conversões do mês. É o valor vendido, não o que já foi recebido: o recebimento fica no Financeiro.">
        <span class="pic"><?= icon('cash', 22) ?></span>
        <span class="pbig num"><?= $contracted ? e(implode(' · ', $contracted)) : e($mny(0.0)) ?></span>
        <span class="plbl">Receita efetivamente contratada</span>
        <span class="trend flat"><?= $ind['contracts'] > 0 ? (int) $ind['contracts'] . ($ind['contracts'] === 1 ? ' contrato no mês' : ' contratos no mês') : 'nenhuma conversão neste mês' ?></span>
    </div>
</div>
<?php if ($counts['noresp'] > 0 && !$won): ?>
<div class="panel lead-alert" role="status">
    <span class="lead-alert-t"><strong><?= (int) $counts['noresp'] ?></strong> <?= $counts['noresp'] === 1 ? 'lead sem responsável' : 'leads sem responsável' ?>: ninguém é dono do retorno.</span>
    <?php if ($f['f'] !== 'noresp'): ?><a class="btn btn-ghost btn-sm" href="<?= e($link(['f' => 'noresp', 'pg' => ''])) ?>">Ver e atribuir</a><?php endif; ?>
</div>
<?php endif; ?>

<form class="pfilters" method="get" action="<?= e(url('/admin/crm')) ?>">
    <input type="hidden" name="f" value="<?= e($f['f']) ?>">
    <input type="hidden" name="pp" value="<?= (int) $perPage ?>">
    <div class="pf-row">
        <div class="pf-search">
            <span class="pf-ico"><?= icon('search', 18) ?></span>
            <label class="sr-only" for="q">Buscar</label>
            <input class="input" type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Nome, WhatsApp, e-mail ou Instagram">
        </div>
        <label class="sr-only" for="temp">Temperatura</label>
        <select id="temp" name="temp" data-autosubmit>
            <option value="">Temperatura</option>
            <?php foreach (Leads::TEMPS as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['temp'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <label class="sr-only" for="source">Origem</label>
        <select id="source" name="source" data-autosubmit>
            <option value="">Origem</option>
            <?php foreach ($sources as $s): ?><option value="<?= (int) $s['id'] ?>"<?= $f['source'] === (string) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?><?= $s['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
        </select>
        <label class="sr-only" for="stage">Etapa</label>
        <select id="stage" name="stage" data-autosubmit>
            <option value="">Etapa</option>
            <?php foreach ($stages as $st): if ($st['kind'] !== 'open') { continue; } ?><option value="<?= (int) $st['id'] ?>"<?= $f['stage'] === (string) $st['id'] ? ' selected' : '' ?>><?= e($st['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm pf-go" type="submit" aria-label="Buscar" title="Buscar"><?= icon('search', 18) ?><span class="pf-go-t">Buscar</span></button>
    </div>
    <details class="pf-more"<?= $moreOn ? ' open' : '' ?>>
        <summary><?= icon('filter', 16) ?> Mais filtros<?= $moreOn ? ' <span class="pf-dot" title="Há filtros ligados aqui dentro"></span>' : '' ?></summary>
        <div class="pf-more-grid lf-more">
            <div>
                <label for="resp">Responsável</label>
                <select id="resp" name="resp" data-autosubmit>
                    <option value="">Todos</option>
                    <option value="none"<?= $f['resp'] === 'none' ? ' selected' : '' ?>>Sem responsável</option>
                    <?php foreach ($responsibles as $rp): ?><option value="<?= (int) $rp['id'] ?>"<?= $f['resp'] === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="task">Retorno de</label>
                <select id="task" name="task" data-autosubmit>
                    <option value="">Todos</option>
                    <?php foreach ($responsibles as $rp): ?><option value="<?= (int) $rp['id'] ?>"<?= $f['task'] === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="sport">Modalidade</label>
                <select id="sport" name="sport" data-autosubmit>
                    <option value="">Todas</option>
                    <?php foreach ($sports as $sp): ?><option value="<?= (int) $sp['id'] ?>"<?= $f['sport'] === (string) $sp['id'] ? ' selected' : '' ?>><?= e($sp['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="plan">Plano de interesse</label>
                <select id="plan" name="plan" data-autosubmit>
                    <option value="">Todos</option>
                    <?php foreach ($plans as $pl): ?><option value="<?= (int) $pl['id'] ?>"<?= $f['plan'] === (string) $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="p">Data de entrada</label>
                <select id="p" name="p" data-autosubmit>
                    <?php foreach (Leads::periods() as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['p'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="ord">Ordenar por</label>
                <select id="ord" name="ord" data-autosubmit>
                    <?php foreach (Leads::sorts() as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['ord'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </details>
</form>

<div class="chips quick" role="group" aria-label="Atalhos">
    <?php foreach ($quick as $k => [$label, $n]): ?>
        <a class="chip" href="<?= e($link(['f' => $k, 'pg' => ''])) ?>"<?= $f['f'] === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?> <span class="chip-n">(<?= (int) $n ?>)</span></a>
    <?php endforeach; ?>
    <?php if ($hasFilters): ?><a class="chip chip-clear" href="<?= e(url('/admin/crm' . ($f['ord'] !== 'new' ? '?ord=' . urlencode($f['ord']) : ''))) ?>">Limpar filtros</a><?php endif; ?>
</div>

<?php if (!$list): ?>
    <div class="empty">
        <?php if ($won && !$f['q'] && !$f['temp'] && !$f['source'] && !$f['stage'] && !$f['sport'] && !$f['plan'] && !$f['resp'] && !$f['task'] && !$f['p']): ?><strong>Nenhum lead convertido ainda</strong>Quando um lead virar paciente (botão "Converter em paciente", na ficha do lead), ele aparece aqui.
        <?php elseif ($hasFilters): ?><strong>Nenhum lead encontrado</strong>Ajuste a busca ou os filtros.
        <?php else: ?><strong>Nenhum lead ainda</strong>Cadastre o primeiro com o botão "Novo lead". Dá para registrar em 10 segundos: nome, WhatsApp e origem.<?php endif; ?>
    </div>
<?php else: ?>
<div class="ptable ltable">
    <?php if (!$won): ?>
    <form class="bulk" id="bulk" method="post" action="<?= e(url('/admin/crm/responsavel')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e(current_path()) ?>">
        <label class="bulk-all"><input type="checkbox" data-check-all aria-label="Marcar todos os leads desta página"> <span>Selecionar todos</span></label>
        <span class="small"><strong data-check-count>0</strong> marcados</span>
        <span class="bulk-sp"></span>
        <label class="sr-only" for="bulk_resp">Responsável</label>
        <select id="bulk_resp" name="responsible_id">
            <option value="">Ações em lote: atribuir a…</option>
            <?php foreach ($activeResp as $rc): ?><option value="<?= (int) $rc['id'] ?>"><?= e($rc['name']) ?></option><?php endforeach; ?>
            <option value="0">Sem responsável (remover)</option>
        </select>
        <button class="btn btn-ghost btn-sm" type="submit">Aplicar</button>
        <?php if (!$activeResp): ?><a class="small" href="<?= e(url('/admin/responsaveis/novo')) ?>">Cadastre o Gabriel e o Comercial em Responsáveis primeiro</a><?php endif; ?>
    </form>
    <?php endif; ?>
    <div class="tbl-wrap">
        <table class="tbl tbl-patients tbl-leads">
            <colgroup><?php if (!$won): ?><col class="k-chk"><?php endif; ?><col class="l-who"><col class="l-temp"><col class="l-src"><col class="l-int"><col class="l-stg"><col class="l-rsp"><col class="l-lst"><col class="l-nxt"><col class="l-act"></colgroup>
            <thead><tr>
                <?php if (!$won): ?><th class="chk"><span class="sr-only">Marcar</span></th><?php endif; ?>
                <th>Lead</th><th title="Temperatura">Temp.</th><th>Origem</th><th>Interesse</th><th>Etapa</th><th>Resp.</th><th>Último contato</th><th><?= $won ? 'Conversão' : 'Próximo retorno' ?></th><th class="r">Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list as $r): $id = (int) $r['id']; $fu = Leads::followUp($r); $wa = Leads::waUrl($r);
                $contact = $r['phone'] ?: ($r['email'] ?: ($r['instagram'] ? '@' . $r['instagram'] : '')); ?>
                <tr>
                    <?php if (!$won): ?><td class="chk"><input type="checkbox" form="bulk" name="ids[]" value="<?= $id ?>" data-check-item aria-label="Marcar <?= e($r['name']) ?>"></td><?php endif; ?>
                    <td class="c-who">
                        <a class="who" href="<?= e(url('/admin/crm/' . $id)) ?>"><span class="ini" aria-hidden="true"><?= e(initials($r['name'])) ?></span>
                            <span><span class="who-name"><?= e($r['name']) ?></span><br><span class="small muted who-mail" title="<?= e($contact) ?>"><?= e($contact) ?></span></span></a>
                    </td>
                    <td data-label="Temperatura"><span class="temp temp-<?= e($r['temperature']) ?>"><?= e(Leads::TEMPS[$r['temperature']] ?? $r['temperature']) ?></span></td>
                    <td data-label="Origem"><?= $r['source_name'] ? e($r['source_name']) : '<span class="muted">-</span>' ?></td>
                    <?php $int = array_filter([$r['sport_name'], $r['plan_name']]); ?>
                    <td data-label="Interesse"<?= $int ? '' : ' class="c-empty"' ?>><?= $int ? e(implode(' · ', $int)) : '<span class="muted">-</span>' ?></td>
                    <td data-label="Etapa"><span class="plan-pill stage-pill"><?= e($r['stage_name']) ?></span></td>
                    <td data-label="Responsável"<?= $r['responsible_name'] ? '' : ' class="c-empty"' ?>><?php if ($r['responsible_name']): ?><span class="resp" title="<?= e($r['responsible_name']) ?>"><span class="ini ini-sm" aria-hidden="true"><?= e(initials($r['responsible_name'])) ?></span><span class="resp-name"><?= e($r['responsible_name']) ?></span></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                    <td data-label="Último contato"><?php if ($r['last_contact_at']): ?><?= e(fmt_date($r['last_contact_at'])) ?><br><span class="small muted"><?= e(Leads::agoText($r['last_contact_at'])) ?></span><?php else: ?><span class="muted">Nunca</span><?php endif; ?></td>
                    <?php if ($won): ?>
                    <td data-label="Conversão"><span class="st st-ok">Virou paciente</span><br><span class="small muted"><?= e(fmt_date($r['converted_at'])) ?></span></td>
                    <?php else: ?>
                    <td data-label="Próximo retorno" class="c-fu">
                        <?php if ($fu['state'] === 'dnc'): ?><span class="st st-off">Não contatar</span>
                        <?php else: ?><span class="fu fu-<?= e($fu['state']) ?>"><?= e($fu['label']) ?></span><?php endif; ?>
                        <?php if ($fu['state'] === 'late' || $fu['state'] === 'today' || $fu['state'] === 'scheduled'): ?>
                            <br><span class="small nx nx-<?= e($fu['cls']) ?>" title="<?= e(fmt_date($r['next_on'])) ?>"><?= e($fu['text']) ?></span><span class="small muted"> · <?= e(substr(fmt_date($r['next_on']), 0, 5)) ?></span>
                            <?php $acao = $r['next_type'] && isset(Leads::ACTIONS[$r['next_type']]) ? Leads::ACTIONS[$r['next_type']] : ''; $tarefa = $r['next_responsible_name'] ? first_name($r['next_responsible_name']) : '';
                            if ($acao !== '' || $tarefa !== ''): ?><br><span class="small muted"><?= e(implode(' · ', array_filter([$acao, $tarefa !== '' ? 'tarefa de ' . $tarefa : '']))) ?></span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="r c-act">
                        <div class="row-actions">
                            <a class="ra" href="<?= e(url('/admin/crm/' . $id)) ?>" title="Abrir o lead" aria-label="Abrir <?= e($r['name']) ?>"><?= icon('eye', 18) ?></a>
                            <?php if ($won && $r['patient_id']): ?><a class="ra" href="<?= e(url(\App\Controllers\Admin\PatientController::ficha((int) $r['patient_id']))) ?>" title="Abrir a ficha do paciente" aria-label="Ficha do paciente <?= e($r['name']) ?>"><?= icon('user', 18) ?></a><?php endif; ?>
                            <?php if (!$won): ?><a class="ra" href="<?= e(url('/admin/crm/' . $id . '/editar')) ?>" title="Editar os dados" aria-label="Editar <?= e($r['name']) ?>"><?= icon('pencil', 18) ?></a><?php endif; ?>
                            <?php if ($wa && !$won): ?><a class="ra" href="<?= e($wa) ?>" target="_blank" rel="noopener" title="Abrir o WhatsApp com a mensagem pronta" aria-label="WhatsApp de <?= e($r['name']) ?>"><?= icon('chat', 18) ?></a><?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php partial('crm-pager', ['total' => $total, 'from' => $from, 'to' => $to, 'page' => $page, 'pages' => $pages, 'nums' => $nums, 'perPage' => $perPage, 'pageLink' => $pageLink, 'cur' => $cur, 'defaults' => $defaults, 'action' => url('/admin/crm'), 'one' => 'lead', 'many' => 'leads']); ?>
</div>
<?php endif; ?>
