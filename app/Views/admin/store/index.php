<div class="page-head">
    <h2>Loja de brindes</h2>
    <div class="row wrap">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/resgates')) ?>">Ver resgates</a>
        <a class="btn btn-sm" href="<?= e(url('/admin/loja/novo')) ?>"><?= icon('plus', 18) ?> Novo brinde</a>
    </div>
</div>
<?php if (!$items): ?>
    <div class="empty"><strong>Nenhum brinde cadastrado</strong>Cadastre camiseta, boné, garrafa... com custo em pontos e estoque.</div>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th></th><th>Brinde</th><th class="r">Custo</th><th class="r">Estoque</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
        <tr>
            <td style="width:70px"><?= $it['image'] ? '<img class="thumb" src="' . e(url('/media/img/' . $it['image'])) . '" alt="">' : '<div class="thumb"></div>' ?></td>
            <td><strong><?= e($it['name']) ?></strong><?= $it['description'] ? '<br><span class="small muted">' . e($it['description']) . '</span>' : '' ?></td>
            <td class="r num"><?= pts($it['cost_points']) ?> pts</td>
            <td class="r num"><?= (int) $it['stock'] === 0 ? '<span class="tag bad">0</span>' : (int) $it['stock'] ?></td>
            <td><?= $it['active'] ? '<span class="tag ok">visível</span>' : '<span class="tag">oculto</span>' ?></td>
            <td class="r"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/loja/' . $it['id'])) ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
