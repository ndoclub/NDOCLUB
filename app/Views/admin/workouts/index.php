<?php $wd = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo']; ?>
<div class="page-head">
    <h2>Treinos</h2>
    <a class="btn btn-sm" href="<?= e(url('/admin/treinos/novo')) ?>"><?= icon('plus', 18) ?> Novo treino</a>
</div>
<p class="muted small">Treino sem paciente é "geral": aparece para todos. Treino com paciente aparece só para ele.</p>
<?php if (!$list): ?>
    <div class="empty"><strong>Nenhum treino criado</strong>Crie a ficha A, B, C... e escolha os dias da semana.</div>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Para</th><th>Treino</th><th>Dia</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $w): ?>
        <tr>
            <td><?= $w['patient'] ? e($w['patient']) : '<span class="tag">Todos</span>' ?></td>
            <td><strong><?= e($w['title']) ?></strong></td>
            <td><?= $w['weekday'] ? e($wd[(int) $w['weekday']]) : 'Qualquer dia' ?></td>
            <td><?= $w['active'] ? '<span class="tag ok">ativo</span>' : '<span class="tag">inativo</span>' ?></td>
            <td class="r"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/treinos/' . $w['id'])) ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
