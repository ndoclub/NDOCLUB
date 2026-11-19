<?php
$filters = ['all' => 'Todos', 'green' => 'Em dia', 'yellow' => 'Vencem logo', 'red' => 'Críticos', 'away' => 'Sumidos', 'inactive' => 'Desativados'];
?>
<div class="page-head">
    <h2>Pacientes</h2>
    <a class="btn btn-sm" href="<?= e(url('/admin/pacientes/novo')) ?>"><?= icon('plus', 18) ?> Novo paciente</a>
</div>

<form class="search" method="get" action="<?= e(url('/admin/pacientes')) ?>">
    <input type="hidden" name="f" value="<?= e($filter) ?>">
    <label class="sr-only" for="q">Buscar</label>
    <input class="input" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Buscar por nome ou e-mail">
    <button class="btn btn-ghost" type="submit">Buscar</button>
</form>
<div class="chips">
    <?php foreach ($filters as $k => $label): ?>
        <a class="chip" href="<?= e(url('/admin/pacientes?f=' . $k . ($q !== '' ? '&q=' . urlencode($q) : ''))) ?>"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$list): ?>
    <div class="empty"><strong>Nenhum paciente encontrado</strong>Ajuste a busca ou o filtro.</div>
<?php else: ?>
<div class="tbl-wrap">
    <table class="tbl">
        <thead><tr><th>Paciente</th><th>Renovação</th><th class="r">Pontos</th><th>Visto por último</th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): ?>
            <tr>
                <td>
                    <a class="who" href="<?= e(url('/admin/pacientes/' . $r['id'])) ?>"><?= avatar($r, 34) ?>
                        <span><?= e($r['name']) ?><br><span class="small muted" style="font-weight:400"><?= e($r['email']) ?></span></span></a>
                </td>
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
