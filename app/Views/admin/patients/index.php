<?php
$filters = ['all' => 'Todos', 'green' => 'Em dia', 'yellow' => 'Vencem logo', 'red' => 'Críticos', 'away' => 'Sumidos', 'inactive' => 'Desativados', 'athletes' => 'Atletas', 'compete' => 'Competem', 'nocoach' => 'Sem assessoria'];
$sq = ($q !== '' ? '&q=' . urlencode($q) : '') . ($sportId ? '&s=' . (int) $sportId : '');
?>
<div class="page-head">
    <h2>Pacientes</h2>
    <a class="btn btn-sm" href="<?= e(url('/admin/pacientes/novo')) ?>"><?= icon('plus', 18) ?> Novo paciente</a>
</div>

<form class="search" method="get" action="<?= e(url('/admin/pacientes')) ?>">
    <input type="hidden" name="f" value="<?= e($filter) ?>">
    <label class="sr-only" for="s">Modalidade</label>
    <select id="s" name="s" data-autosubmit style="flex:0 0 auto;max-width:215px">
        <option value="">Todas as modalidades</option>
        <?php foreach ($sports as $sp): ?>
            <option value="<?= (int) $sp['id'] ?>"<?= (int) $sportId === (int) $sp['id'] ? ' selected' : '' ?>><?= e($sp['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="q">Buscar</label>
    <input class="input" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Buscar por nome ou e-mail">
    <button class="btn btn-ghost" type="submit">Buscar</button>
</form>
<div class="chips">
    <?php foreach ($filters as $k => $label): ?>
        <a class="chip" href="<?= e(url('/admin/pacientes?f=' . $k . $sq)) ?>"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$list): ?>
    <div class="empty"><strong>Nenhum paciente encontrado</strong>Ajuste a busca ou o filtro.</div>
<?php else: ?>
<div class="tbl-wrap">
    <table class="tbl">
        <thead><tr><th>Paciente</th><th>Plano</th><th>Renovação</th><th class="r">Pontos</th><th>Visto por último</th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): ?>
            <tr>
                <td>
                    <a class="who" href="<?= e(url('/admin/pacientes/' . $r['id'])) ?>"><?= avatar($r, 34) ?>
                        <span><?= e($r['name']) ?><br><span class="small muted" style="font-weight:400"><?= e($r['email']) ?></span>
                            <?php if (!empty($sportNames[(int) $r['id']])): ?><span class="sports"><?php foreach ($sportNames[(int) $r['id']] as $sn): ?><span class="sport"><?= e($sn) ?></span><?php endforeach; ?></span><?php endif; ?></span></a>
                </td>
                <td><?php if ($r['plan_name']): ?><?= e($r['plan_name']) ?><br><span class="small muted"><?= $r['plan_value'] !== null ? e(money((float) $r['plan_value'], floor((float) $r['plan_value']) == (float) $r['plan_value'] ? 0 : 2)) : 'sem valor' ?></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                <td>
                    <?php if ($r['status'] === 'inactive'): ?><span class="tag">Desativado</span>
                    <?php else: partial('seal', ['renew' => $r['renew']]); endif; ?>
                </td>
                <td class="r num"><?= pts($r['balance']) ?></td>
                <td><?= $r['last_seen_at'] ? e(time_ago($r['last_seen_at'])) : 'Nunca entrou' ?><?= $r['away'] && $r['status'] === 'active' ? ' <span class="tag warn">sumido</span>' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="muted small mt"><?= count($list) ?> <?= count($list) === 1 ? 'paciente' : 'pacientes' ?></p>
<?php endif; ?>
