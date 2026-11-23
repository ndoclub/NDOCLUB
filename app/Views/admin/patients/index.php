<?php
// Nos filtros de atletas (ou ao escolher uma modalidade) a lista ganha as colunas Prova e Assessoria
$athleteCols = in_array($filter, ['athletes', 'compete', 'nocoach'], true) || $sportId > 0;

// Endereço da lista mantendo todos os filtros e a ordenação (só troca o que vier em $over)
$cur = ['f' => $filter, 'q' => $q, 's' => $sportId, 'plan' => $planFilter, 'st' => $stFilter, 'v' => $dueFilter, 'r' => $respFilter, 'c' => $consFilter, 'ord' => $ord, 'pp' => $perPage];
$defaults = ['f' => 'all', 'ord' => 'name_asc', 'pp' => 25];
$link = function (array $over = []) use ($cur, $defaults) {
    $m = array_filter(array_merge($cur, $over), fn($v, $k) => $v !== '' && $v !== 0 && $v !== null && !(isset($defaults[$k]) && $defaults[$k] === $v), ARRAY_FILTER_USE_BOTH);
    return url('/admin/pacientes') . ($m ? '?' . http_build_query($m) : '');
};
$hasFilters = ($filter !== 'all' || $q !== '' || $sportId || $planFilter || $stFilter !== '' || $dueFilter !== '' || $respFilter !== '' || $consFilter !== '');
// Título de coluna que ordena ao clicar (clicar de novo inverte)
$th = function (string $key, string $label, string $class = '') use ($sorts, $sortKey, $sortDir, $link) {
    $on = $sortKey === $key;
    $dir = $on ? ($sortDir === 'asc' ? 'desc' : 'asc') : $sorts[$key][3];
    $arrow = $on ? ($sortDir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<th' . ($class ? ' class="' . $class . '"' : '') . ($on ? ' aria-sort="' . ($sortDir === 'asc' ? 'ascending' : 'descending') . '"' : '') . '>'
        . '<a class="th-sort' . ($on ? ' on' : '') . '" href="' . e($link(['ord' => $key . '_' . $dir])) . '" title="Ordenar por ' . e(mb_strtolower($label)) . '">' . e($label) . $arrow . '</a></th>';
};

$c = $cards['ins']['cards'];
$trend = function (?array $t, string $unit = '%') {
    if ($t === null) {
        return '<span class="trend flat">sem base para comparar</span>';
    }
    $arrow = ['up' => '↑', 'down' => '↓', 'flat' => '→'][$t['dir']];
    $sign = ['up' => '+', 'down' => '−', 'flat' => ''][$t['dir']];
    $word = ['up' => 'a mais', 'down' => 'a menos', 'flat' => 'igual'][$t['dir']];
    return '<span class="trend ' . e($t['dir']) . '"><span aria-hidden="true">' . $arrow . '</span> ' . $sign . (int) $t['pct'] . $unit . '<span class="sr-only"> ' . $word . '</span> <span class="muted">vs. mês passado</span></span>';
};
$foreign = array_diff_key(array_filter($c['sold']['by'], fn($v) => $v > 0), ['BRL' => 1]);
$rate = $c['renewal']['rate'];
// Filtros que ficam dentro de "Mais filtros": se algum estiver ligado, o quadro já abre
$moreOn = ($respFilter !== '' || $consFilter !== '' || $ord !== 'name_asc' || isset($more[$filter]));
$from = $total > 0 ? $offset + 1 : 0;
$to = min($total, $offset + $perPage);
$pageLink = fn(int $n) => $link(['pg' => $n]);
// Janela de páginas: 1 … atual-1, atual, atual+1 … última
$nums = array_values(array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], fn($n) => $n >= 1 && $n <= $pages)));
sort($nums);
$statusTag = function (array $r) use ($cards) {
    $k = $r['plan_status']['key'];
    if ($k === 'active') {
        $soon = $r['renew']['days'] !== null && $r['renew']['days'] <= $cards['yellow'];
        return $soon ? ['warn', 'Renovação próxima'] : ['ok', 'Ativo'];
    }
    return [$k === 'expired' ? 'off' : 'off', $r['plan_status']['label']];
};
?>
<div class="page-head ppage-head">
    <div>
        <h2>Pacientes</h2>
        <p class="muted small" style="margin:4px 0 0">Gerencie sua carteira de pacientes de forma simples e eficiente.</p>
    </div>
    <div class="row wrap" style="gap:8px">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/importar')) ?>">Importar planilha</a>
        <a class="btn btn-sm" href="<?= e(url('/admin/pacientes/novo')) ?>"><?= icon('plus', 18) ?> Novo paciente</a>
    </div>
</div>

<div class="pcards" role="list" aria-label="Indicadores do mês">
    <a class="pcard" role="listitem" href="<?= e(url('/admin/pacientes?st=active')) ?>">
        <span class="pic"><?= icon('users', 22) ?></span>
        <span class="pbig num"><?= (int) $c['active']['value'] ?></span>
        <span class="plbl">Pacientes em carteira</span>
        <?= $trend($c['active']['trend']) ?>
    </a>
    <div class="pcard" role="listitem">
        <span class="pic"><?= icon('calendar', 22) ?></span>
        <span class="pbig num"><?= (int) $c['new']['value'] ?></span>
        <span class="plbl">Novos no mês</span>
        <?= $trend($c['new']['trend']) ?>
    </div>
    <a class="pcard" role="listitem" href="<?= e(url('/admin/financeiro')) ?>">
        <span class="pic"><?= icon('cash', 22) ?></span>
        <span class="pbig num"><?= $c['sold']['brl'] > 0 ? e(money($c['sold']['brl'], 0)) : '—' ?></span>
        <span class="plbl">Faturamento contratado</span>
        <?= $trend($c['sold']['trend']) ?>
        <?php if ($foreign): ?><span class="trend flat">+ <?= e(money_multi($foreign)) ?> (exterior)</span><?php endif; ?>
    </a>
    <a class="pcard" role="listitem" href="<?= e($link(['f' => 'soon'])) ?>">
        <span class="pic"><?= icon('refresh', 22) ?></span>
        <span class="pbig num"><?= (int) $cards['soon'] ?></span>
        <span class="plbl">Renovações próximas</span>
        <span class="trend flat"><?= (int) $cards['soon7'] > 0 ? (int) $cards['soon7'] . ' ' . ($cards['soon7'] === 1 ? 'vence' : 'vencem') . ' em até 7 dias' : 'nenhuma em até 7 dias' ?></span>
    </a>
    <div class="pcard" role="listitem">
        <span class="pic"><?= icon('star', 22) ?></span>
        <span class="pbig num"><?= $rate['pct'] === null ? '—' : (int) $rate['pct'] . '%' ?></span>
        <span class="plbl">Taxa de renovação</span>
        <?php if ($rate['due'] > 0 && $c['renewal']['trend'] !== null): ?><?= $trend($c['renewal']['trend'], ' pp') ?>
        <?php else: ?><span class="trend flat"><?= $rate['due'] > 0 ? (int) $rate['renewed'] . ' de ' . (int) $rate['due'] . ' planos' : 'nenhum plano venceu' ?></span><?php endif; ?>
    </div>
</div>

<form class="pfilters" method="get" action="<?= e(url('/admin/pacientes')) ?>">
    <input type="hidden" name="f" value="<?= e($filter) ?>">
    <input type="hidden" name="pp" value="<?= (int) $perPage ?>">
    <div class="pf-row">
        <div class="pf-search">
            <span class="pf-ico"><?= icon('search', 18) ?></span>
            <label class="sr-only" for="q">Buscar</label>
            <input class="input" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nome, e-mail, CPF ou WhatsApp">
        </div>
        <label class="sr-only" for="plan">Plano</label>
        <select id="plan" name="plan" data-autosubmit>
            <option value="">Plano</option>
            <?php foreach ($planOptions as $po): ?>
                <option value="<?= (int) $po['id'] ?>"<?= (int) $planFilter === (int) $po['id'] ? ' selected' : '' ?>><?= e($po['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="s">Modalidade</label>
        <select id="s" name="s" data-autosubmit>
            <option value="">Modalidade</option>
            <?php foreach ($sports as $sp): ?>
                <option value="<?= (int) $sp['id'] ?>"<?= (int) $sportId === (int) $sp['id'] ? ' selected' : '' ?>><?= e($sp['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="st">Status</label>
        <select id="st" name="st" data-autosubmit>
            <option value="">Status</option>
            <option value="active"<?= $stFilter === 'active' ? ' selected' : '' ?>>Ativos (plano em dia)</option>
            <option value="expired"<?= $stFilter === 'expired' ? ' selected' : '' ?>>Inativos (plano vencido)</option>
            <option value="disabled"<?= $stFilter === 'disabled' ? ' selected' : '' ?>>Desativados (conta bloqueada)</option>
            <option value="nodate"<?= $stFilter === 'nodate' ? ' selected' : '' ?>>Sem data de vencimento</option>
        </select>
        <label class="sr-only" for="v">Vencimento</label>
        <select id="v" name="v" data-autosubmit>
            <option value="">Vencimento</option>
            <?php foreach ($dueBuckets as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $dueFilter === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm pf-go" type="submit" aria-label="Buscar" title="Buscar"><?= icon('search', 18) ?><span class="pf-go-t">Buscar</span></button>
    </div>

    <details class="pf-more"<?= $moreOn ? ' open' : '' ?>>
        <summary><?= icon('filter', 16) ?> Mais filtros<?= $moreOn ? ' <span class="pf-dot" title="Há filtros ligados aqui dentro"></span>' : '' ?></summary>
        <div class="pf-more-grid">
            <div>
                <label for="r">Responsável</label>
                <select id="r" name="r" data-autosubmit>
                    <option value="">Todos</option>
                    <option value="none"<?= $respFilter === 'none' ? ' selected' : '' ?>>Sem responsável</option>
                    <?php foreach ($responsibles as $rp): ?>
                        <option value="<?= (int) $rp['id'] ?>"<?= $respFilter === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="c">Consultas</label>
                <select id="c" name="c" data-autosubmit>
                    <option value="">Todas</option>
                    <option value="left"<?= $consFilter === 'left' ? ' selected' : '' ?>>Ainda têm consultas a fazer</option>
                    <option value="full"<?= $consFilter === 'full' ? ' selected' : '' ?>>Cota de consultas completa</option>
                    <option value="zero"<?= $consFilter === 'zero' ? ' selected' : '' ?>>Nenhuma consulta feita</option>
                    <option value="noquota"<?= $consFilter === 'noquota' ? ' selected' : '' ?>>Plano sem cota definida</option>
                </select>
            </div>
            <div>
                <label for="ord">Ordenar por</label>
                <select id="ord" name="ord" data-autosubmit>
                    <?php foreach ($sorts as $k => [$label, $ascTxt, $descTxt]): ?>
                        <option value="<?= e($k . '_asc') ?>"<?= $ord === $k . '_asc' ? ' selected' : '' ?>><?= e($label) ?>: <?= e($ascTxt) ?></option>
                        <option value="<?= e($k . '_desc') ?>"<?= $ord === $k . '_desc' ? ' selected' : '' ?>><?= e($label) ?>: <?= e($descTxt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="pf-more-h">Outros atalhos</p>
        <div class="chips pf-chips">
            <?php foreach ($more as $k => $label): ?>
                <a class="chip" href="<?= e($link(['f' => $k])) ?>"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </details>
</form>

<div class="chips quick" role="group" aria-label="Atalhos">
    <?php foreach ($quick as $k => $label): ?>
        <a class="chip" href="<?= e($link(['f' => $k])) ?>"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?> <span class="chip-n">(<?= (int) $counts[$k] ?>)</span></a>
    <?php endforeach; ?>
    <?php if ($hasFilters): ?><a class="chip chip-clear" href="<?= e(url('/admin/pacientes' . ($ord !== 'name_asc' ? '?ord=' . urlencode($ord) : ''))) ?>">Limpar filtros</a><?php endif; ?>
</div>

<?php if (!$list): ?>
    <div class="empty"><strong>Nenhum paciente encontrado</strong>Ajuste a busca ou os filtros.</div>
<?php else: ?>
<div class="ptable">
    <form class="bulk" id="bulk" method="post" action="<?= e(url('/admin/pacientes/responsavel')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e(current_path()) ?>">
        <label class="bulk-all"><input type="checkbox" data-check-all aria-label="Marcar todos os pacientes desta página"> <span>Selecionar todos</span></label>
        <span class="small"><strong data-check-count>0</strong> marcados</span>
        <span class="bulk-sp"></span>
        <label class="sr-only" for="bulk_resp">Responsável</label>
        <select id="bulk_resp" name="responsible_id">
            <option value="">Ações em lote: atribuir a…</option>
            <?php foreach ($respChoices as $rc): if (!$rc['active']) { continue; } ?>
                <option value="<?= (int) $rc['id'] ?>"><?= e($rc['name']) ?></option>
            <?php endforeach; ?>
            <option value="0">Sem responsável (remover)</option>
        </select>
        <button class="btn btn-ghost btn-sm" type="submit">Aplicar</button>
        <?php if (!array_filter($respChoices, fn($x) => $x['active'])): ?><a class="small" href="<?= e(url('/admin/responsaveis/novo')) ?>">Cadastre os responsáveis primeiro</a><?php endif; ?>
    </form>
    <div class="tbl-wrap">
        <table class="tbl tbl-patients">
            <thead><tr>
                <th class="chk"><span class="sr-only">Marcar</span></th>
                <?= $th('name', 'Paciente') ?>
                <?= $th('plan', 'Plano') ?>
                <th>Modalidade</th>
                <?= $th('renewal', 'Vencimento') ?>
                <?= $th('status', 'Status') ?>
                <?= $th('resp', 'Responsável') ?>
                <th class="r">Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list as $r): $id = (int) $r['id']; [$stCls, $stTxt] = $statusTag($r); $sn = $sportNames[$id] ?? []; ?>
                <tr>
                    <td class="chk"><input type="checkbox" form="bulk" name="ids[]" value="<?= $id ?>" data-check-item aria-label="Marcar <?= e($r['name']) ?>"></td>
                    <td class="c-who">
                        <a class="who" href="<?= e(url('/admin/pacientes/' . $id)) ?>"><?= avatar($r, 40) ?>
                            <span><span class="who-name"><?= e($r['name']) ?></span><br><span class="small muted who-mail" title="<?= e($r['email']) ?>"><?= e($r['email']) ?></span></span></a>
                    </td>
                    <td data-label="Plano"><?php if ($r['plan_name']): ?><span class="plan-pill"><?= e($r['plan_name']) ?></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                    <td data-label="Modalidade"><?php if ($sn): ?><span class="mod"><?= e($sn[0]) ?></span><?= count($sn) > 1 ? ' <span class="tag" title="' . e(implode(', ', array_slice($sn, 1))) . '">+' . (count($sn) - 1) . '</span>' : '' ?><?php else: ?><span class="muted">-</span><?php endif; ?>
                        <?php if ($athleteCols && (int) ($r['is_athlete'] ?? 0) === 1): $dd = ($r['competes'] && $r['event_date']) ? days_until($r['event_date']) : null; ?>
                            <br><span class="small muted ath-line"><?php
                                if (!$r['competes']) { echo 'Não compete'; }
                                elseif ($r['event_name'] || $r['event_date']) {
                                    echo 'Prova: ' . e($r['event_name'] ?: 'sem nome') . ($r['event_date'] ? ' · ' . e(fmt_date($r['event_date'])) . ($dd !== null && $dd >= 0 ? ' (em ' . (int) $dd . ' d)' : ' (já passou)') : '');
                                } else { echo 'Compete, sem prova cadastrada'; }
                                echo ' · ' . ($r['has_coach'] ? 'Assessoria: ' . e($r['coach_name'] ?: 'sim') : 'Sem assessoria');
                            ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num" data-label="Vencimento">
                        <?php if ($r['renewal_date']): ?><span class="due"><?= e(fmt_date($r['renewal_date'])) ?></span>
                            <?php if ($r['status'] !== 'inactive' && $r['renew']['days'] !== null): ?><br><span class="small muted"><?= e($r['renew']['label']) ?></span><?php endif; ?>
                        <?php else: ?><span class="muted">-</span><?php endif; ?>
                    </td>
                    <td data-label="Status">
                        <span class="st st-<?= e($stCls) ?>"><?= e($stTxt) ?></span>
                        <?php if (!empty($r['late'])): ?><span class="st st-bad" title="Tem parcela vencida e ainda não recebida">Parcela atrasada</span><?php endif; ?>
                    </td>
                    <td data-label="Responsável"><?php if ($r['responsible_name']): ?><span class="resp"><span class="ini ini-sm" aria-hidden="true"><?= e(initials($r['responsible_name'])) ?></span><?= e($r['responsible_name']) ?></span><?= $r['responsible_active'] ? '' : ' <span class="small muted">(desativada)</span>' ?><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                    <td class="r c-act">
                        <div class="row-actions">
                            <a class="ra" href="<?= e(url(\App\Controllers\Admin\PatientController::ficha($id))) ?>" title="Ver a ficha" aria-label="Ver a ficha de <?= e($r['name']) ?>"><?= icon('eye', 18) ?></a>
                            <a class="ra" href="<?= e(url(\App\Controllers\Admin\PatientController::ficha($id, 'resumo', 'dados'))) ?>" title="Editar os dados" aria-label="Editar os dados de <?= e($r['name']) ?>"><?= icon('pencil', 18) ?></a>
                            <details class="ra-menu">
                                <summary class="ra" title="Mais opções" aria-label="Mais opções de <?= e($r['name']) ?>"><?= icon('dots', 18) ?></summary>
                                <div class="menu-pop">
                                    <a href="<?= e(url(\App\Controllers\Admin\PatientController::ficha($id, 'plano'))) ?>"><?= icon('cash', 18) ?> Plano e financeiro</a>
                                    <a href="<?= e(url(\App\Controllers\Admin\PatientController::ficha($id, 'acompanhamento'))) ?>"><?= icon('list', 18) ?> Acompanhamento</a>
                                    <a href="<?= e(url(\App\Controllers\Admin\PatientController::ficha($id, 'app'))) ?>"><?= icon('trophy', 18) ?> App do paciente</a>
                                    <?php $wa = wa_phone_url($r['phone'] ?? '', ''); if ($wa): ?><hr><a href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('chat', 18) ?> Abrir WhatsApp</a><?php endif; ?>
                                </div>
                            </details>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="pager">
        <p class="small muted pager-info">Mostrando <strong><?= (int) $from ?>–<?= (int) $to ?></strong> de <strong><?= (int) $total ?></strong> <?= $total === 1 ? 'paciente' : 'pacientes' ?></p>
        <?php if ($pages > 1): ?>
        <nav class="pager-nav" aria-label="Páginas">
            <?php if ($page > 1): ?><a class="pg" href="<?= e($pageLink($page - 1)) ?>" aria-label="Página anterior"><?= icon('left', 16) ?></a><?php else: ?><span class="pg off" aria-hidden="true"><?= icon('left', 16) ?></span><?php endif; ?>
            <?php $prev = 0; foreach ($nums as $n): if ($n - $prev > 1): ?><span class="pg gap" aria-hidden="true">…</span><?php endif; ?>
                <a class="pg<?= $n === $page ? ' on' : '' ?>" href="<?= e($pageLink($n)) ?>"<?= $n === $page ? ' aria-current="page"' : '' ?> aria-label="Página <?= $n ?>"><?= $n ?></a>
            <?php $prev = $n; endforeach; ?>
            <?php if ($page < $pages): ?><a class="pg" href="<?= e($pageLink($page + 1)) ?>" aria-label="Próxima página"><?= icon('right', 16) ?></a><?php else: ?><span class="pg off" aria-hidden="true"><?= icon('right', 16) ?></span><?php endif; ?>
        </nav>
        <?php endif; ?>
        <form class="pager-pp" method="get" action="<?= e(url('/admin/pacientes')) ?>">
            <?php foreach (array_diff_key($cur, ['pp' => 1]) as $k => $v): if ($v === '' || $v === 0 || $v === null || (isset($defaults[$k]) && $defaults[$k] === $v)) { continue; } ?>
                <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
            <?php endforeach; ?>
            <label for="pp">Mostrar:</label>
            <select id="pp" name="pp" data-autosubmit>
                <?php foreach ($perOptions as $o): ?><option value="<?= (int) $o ?>"<?= $o === $perPage ? ' selected' : '' ?>><?= (int) $o ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>
