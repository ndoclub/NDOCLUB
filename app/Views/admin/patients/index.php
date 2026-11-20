<?php
$filters = ['all' => 'Todos', 'green' => 'Em dia', 'yellow' => 'Vencem logo', 'red' => 'Críticos', 'away' => 'Sumidos', 'noapp' => 'Sem acesso ao app', 'inactive' => 'Desativados', 'athletes' => 'Atletas', 'compete' => 'Competem', 'nocoach' => 'Sem assessoria'];
// Nos filtros de atletas (ou ao escolher uma modalidade) a lista ganha as colunas Prova e Assessoria
$athleteCols = in_array($filter, ['athletes', 'compete', 'nocoach'], true) || $sportId > 0;

// Endereço da lista mantendo todos os filtros e a ordenação (só troca o que vier em $over)
$cur = ['f' => $filter, 'q' => $q, 's' => $sportId, 'plan' => $planFilter, 'st' => $stFilter, 'v' => $dueFilter, 'r' => $respFilter, 'c' => $consFilter, 'ord' => $ord];
$defaults = ['f' => 'all', 'ord' => 'name_asc'];
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
?>
<div class="page-head">
    <h2>Pacientes</h2>
    <div class="row wrap" style="gap:8px">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/importar')) ?>">Importar planilha</a>
        <a class="btn btn-sm" href="<?= e(url('/admin/pacientes/novo')) ?>"><?= icon('plus', 18) ?> Novo paciente</a>
    </div>
</div>

<form class="search" method="get" action="<?= e(url('/admin/pacientes')) ?>">
    <input type="hidden" name="f" value="<?= e($filter) ?>">
    <label class="sr-only" for="plan">Plano</label>
    <select id="plan" name="plan" data-autosubmit>
        <option value="">Todos os planos</option>
        <?php foreach ($planOptions as $po): ?>
            <option value="<?= (int) $po['id'] ?>"<?= (int) $planFilter === (int) $po['id'] ? ' selected' : '' ?>><?= e($po['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="s">Modalidade</label>
    <select id="s" name="s" data-autosubmit>
        <option value="">Todas as modalidades</option>
        <?php foreach ($sports as $sp): ?>
            <option value="<?= (int) $sp['id'] ?>"<?= (int) $sportId === (int) $sp['id'] ? ' selected' : '' ?>><?= e($sp['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="q">Buscar</label>
    <input class="input" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Buscar por nome ou e-mail">
    <button class="btn btn-ghost" type="submit">Buscar</button>

    <div class="filters-row">
        <label class="sr-only" for="st">Status</label>
        <select id="st" name="st" data-autosubmit>
            <option value="">Status (todos)</option>
            <option value="active"<?= $stFilter === 'active' ? ' selected' : '' ?>>Ativos (plano em dia)</option>
            <option value="expired"<?= $stFilter === 'expired' ? ' selected' : '' ?>>Inativos (plano vencido)</option>
            <option value="disabled"<?= $stFilter === 'disabled' ? ' selected' : '' ?>>Desativados (conta bloqueada)</option>
            <option value="nodate"<?= $stFilter === 'nodate' ? ' selected' : '' ?>>Sem data de vencimento</option>
        </select>
        <label class="sr-only" for="v">Vencimento</label>
        <select id="v" name="v" data-autosubmit>
            <option value="">Vencimento (todos)</option>
            <?php foreach ($dueBuckets as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $dueFilter === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="r">Responsável</label>
        <select id="r" name="r" data-autosubmit>
            <option value="">Responsável (todos)</option>
            <option value="none"<?= $respFilter === 'none' ? ' selected' : '' ?>>Sem responsável</option>
            <?php foreach ($responsibles as $rp): ?>
                <option value="<?= (int) $rp['id'] ?>"<?= $respFilter === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="c">Consultas</label>
        <select id="c" name="c" data-autosubmit>
            <option value="">Consultas (todas)</option>
            <option value="left"<?= $consFilter === 'left' ? ' selected' : '' ?>>Ainda têm consultas a fazer</option>
            <option value="full"<?= $consFilter === 'full' ? ' selected' : '' ?>>Cota de consultas completa</option>
            <option value="zero"<?= $consFilter === 'zero' ? ' selected' : '' ?>>Nenhuma consulta feita</option>
            <option value="noquota"<?= $consFilter === 'noquota' ? ' selected' : '' ?>>Plano sem cota definida</option>
        </select>
        <label class="sr-only" for="ord">Ordenar por</label>
        <select id="ord" name="ord" data-autosubmit>
            <?php foreach ($sorts as $k => [$label, $ascTxt, $descTxt]): ?>
                <option value="<?= e($k . '_asc') ?>"<?= $ord === $k . '_asc' ? ' selected' : '' ?>><?= e($label) ?>: <?= e($ascTxt) ?></option>
                <option value="<?= e($k . '_desc') ?>"<?= $ord === $k . '_desc' ? ' selected' : '' ?>><?= e($label) ?>: <?= e($descTxt) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>
<div class="chips">
    <?php foreach ($filters as $k => $label): ?>
        <a class="chip" href="<?= e($link(['f' => $k])) ?>"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <?php if ($hasFilters): ?><a class="chip chip-clear" href="<?= e(url('/admin/pacientes' . ($ord !== 'name_asc' ? '?ord=' . urlencode($ord) : ''))) ?>">Limpar filtros</a><?php endif; ?>
</div>

<?php if (!$list): ?>
    <div class="empty"><strong>Nenhum paciente encontrado</strong>Ajuste a busca ou os filtros.</div>
<?php else: ?>
<form class="bulk" id="bulk" method="post" action="<?= e(url('/admin/pacientes/responsavel')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= e(current_path()) ?>">
    <span class="small"><strong data-check-count>0</strong> marcados</span>
    <label class="sr-only" for="bulk_resp">Responsável</label>
    <select id="bulk_resp" name="responsible_id">
        <option value="">Atribuir os marcados a…</option>
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
            <th class="chk"><input type="checkbox" data-check-all aria-label="Marcar todos os pacientes da lista"></th>
            <?= $th('name', 'Paciente') ?>
            <?php if ($athleteCols): ?><th>Prova</th><th>Assessoria</th><?php endif; ?>
            <?= $th('status', 'Status') ?>
            <?= $th('plan', 'Plano') ?>
            <?= $th('renewal', 'Vencimento') ?>
            <?= $th('consults', 'Consultas') ?>
            <?= $th('resp', 'Responsável') ?>
        </tr></thead>
        <tbody>
        <?php foreach ($list as $r): ?>
            <tr>
                <td class="chk"><input type="checkbox" form="bulk" name="ids[]" value="<?= (int) $r['id'] ?>" data-check-item aria-label="Marcar <?= e($r['name']) ?>"></td>
                <td>
                    <a class="who" href="<?= e(url('/admin/pacientes/' . $r['id'])) ?>"><?= avatar($r, 34) ?>
                        <span><?= e($r['name']) ?><br><span class="small muted" style="font-weight:400"><?= e($r['email']) ?></span>
                            <?php if (!empty($sportNames[(int) $r['id']])): ?><span class="sports"><?php foreach ($sportNames[(int) $r['id']] as $sn): ?><span class="sport"><?= e($sn) ?></span><?php endforeach; ?></span><?php endif; ?></span></a>
                </td>
                <?php if ($athleteCols): $isAth = (int) ($r['is_athlete'] ?? 0) === 1; $dd = ($isAth && $r['competes'] && $r['event_date']) ? days_until($r['event_date']) : null; ?>
                <td>
                    <?php if (!$isAth || !$r['competes']): ?><span class="muted"><?= $isAth ? 'Não compete' : '-' ?></span>
                    <?php elseif ($r['event_name'] || $r['event_date']): ?>
                        <strong><?= e($r['event_name'] ?: 'Prova') ?></strong>
                        <?php if ($r['event_date']): ?><br><span class="small muted"><?= e(fmt_date($r['event_date'])) ?></span>
                            <span class="tag<?= ($dd !== null && $dd >= 0 && $dd <= 14) ? ' warn' : '' ?>"><?= $dd < 0 ? 'já passou' : ($dd === 0 ? 'hoje' : ($dd === 1 ? 'amanhã' : 'em ' . (int) $dd . ' dias')) ?></span><?php endif; ?>
                    <?php else: ?><span class="muted">Compete, sem prova cadastrada</span><?php endif; ?>
                </td>
                <td><?php if (!$isAth): ?><span class="muted">-</span><?php elseif ($r['has_coach']): ?><?= e($r['coach_name'] ?: 'Sim') ?><?php else: ?><span class="muted">Sem assessoria</span><?php endif; ?></td>
                <?php endif; ?>
                <td><span class="tag<?= $r['plan_status']['tag'] ? ' ' . e($r['plan_status']['tag']) : '' ?>"><?= e($r['plan_status']['label']) ?></span><?= $r['away'] && $r['status'] === 'active' ? '<br><span class="tag warn" title="Não abre o app há 7 dias ou mais">sumido</span>' : '' ?><?= $r['noapp'] ? '<br><span class="tag" title="Veio de importação. Gere a senha provisória na ficha para liberar o acesso.">sem acesso ao app</span>' : '' ?></td>
                <td><?php if ($r['plan_name']): ?><?= e($r['plan_name']) ?><br><span class="small muted"><?= $r['plan_value'] !== null ? e(money((float) $r['plan_value'], floor((float) $r['plan_value']) == (float) $r['plan_value'] ? 0 : 2, (string) $r['plan_currency'])) : 'sem valor' ?></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                <td class="num">
                    <?php if ($r['renewal_date']): ?><strong><?= e(fmt_date($r['renewal_date'])) ?></strong><br><?php endif; ?>
                    <?php if ($r['status'] === 'inactive'): ?><?= $r['renewal_date'] ? '' : '<span class="muted">-</span>' ?>
                    <?php else: partial('seal', ['renew' => $r['renew']]); endif; ?>
                </td>
                <td class="num">
                    <?php $c = $r['cons']; ?>
                    <?php if (!$r['plan_name']): ?><span class="muted">-</span>
                    <?php elseif ($c['quota'] === null): ?><strong><?= (int) $c['done'] ?></strong><br><span class="small muted">sem cota no plano</span>
                    <?php else: ?><strong><?= (int) $c['done'] ?></strong> de <?= (int) $c['quota'] ?><br>
                        <?php if ($c['full']): ?><span class="tag ok">completa</span><?php else: ?><span class="small muted">restam <?= (int) $c['left'] ?></span><?php endif; ?>
                    <?php endif; ?>
                </td>
                <td><?php if ($r['responsible_name']): ?><?= e($r['responsible_name']) ?><?= $r['responsible_active'] ? '' : '<br><span class="small muted">(desativada)</span>' ?><?php else: ?><span class="muted">-</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="muted small mt"><?= count($list) ?> <?= count($list) === 1 ? 'paciente' : 'pacientes' ?></p>
<?php endif; ?>
