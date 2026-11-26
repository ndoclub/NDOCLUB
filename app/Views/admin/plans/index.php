<div class="page-head">
    <h2>Planos</h2>
    <a class="btn btn-sm" href="<?= e(url('/admin/planos/novo')) ?>"><?= icon('plus', 18) ?> Novo plano</a>
</div>
<p class="muted small">Os valores em R$ aparecem <strong>só aqui no painel</strong>. O paciente vê apenas o nome do plano e a validade. Para um paciente com valor diferente (ou em dólar/euro), use o campo "Valor combinado" no cadastro dele. Se você mudar o valor de um plano, quem já está nele mantém o valor antigo até a próxima renovação.</p>
<?php $semValor = array_filter($plans, fn($p) => $p['active'] && $p['price'] === null); ?>
<?php if ($semValor): ?>
    <p class="flash flash-err" style="margin:0 0 16px">Defina o valor de <?= implode(', ', array_map(fn($p) => e($p['name']), $semValor)) ?> para o Painel calcular quanto está para renovar.</p>
<?php endif; ?>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Plano</th><th class="r">Duração</th><th class="r">Valor</th><th class="r">Consultas</th><th>Jornada</th><th class="r">Pacientes em carteira</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($plans as $p): ?>
        <tr<?= $p['active'] ? '' : ' class="tag-soft"' ?>>
            <td><strong><?= e($p['name']) ?></strong><?= $p['active'] ? '' : ' <span class="muted small">(desativado)</span>' ?></td>
            <td class="r num"><?= (int) $p['duration_days'] ?> dias</td>
            <td class="r num"><?= $p['price'] === null ? '<span class="muted">definir</span>' : e(money((float) $p['price'])) ?></td>
            <td class="r num"><?= $p['consultations_included'] === null ? '<span class="muted">definir</span>' : (int) $p['consultations_included'] ?></td>
            <td class="small"><?= e(\App\Services\Journey::TYPES[\App\Services\Journey::typeOf($p['journey_type'] ?? 'premium')]) ?></td>
            <td class="r num"><?php if ((int) $p['patients'] > 0): ?><a href="<?= e(url('/admin/pacientes?plan=' . (int) $p['id'])) ?>"><?= (int) $p['patients'] ?></a><?php else: ?>0<?php endif; ?></td>
            <td class="r"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/planos/' . $p['id'])) ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
