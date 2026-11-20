<div class="page-head">
    <h2>Responsáveis</h2>
    <a class="btn btn-sm" href="<?= e(url('/admin/responsaveis/novo')) ?>"><?= icon('plus', 18) ?> Novo responsável</a>
</div>
<p class="muted small">É o "modo babá": cada estagiária fica responsável por alguns pacientes. Por enquanto elas <strong>não entram no sistema</strong>; é só uma etiqueta que o Gabriel ou a equipe define, para filtrar a lista de pacientes. Para atribuir, abra <strong>Pacientes</strong>, marque os pacientes e use "Atribuir". Ninguém é apagado: desative para esconder, e os pacientes continuam com a etiqueta. O paciente nunca vê isso.</p>
<?php if (!$list): ?>
    <div class="empty"><strong>Nenhum responsável ainda</strong>Cadastre as estagiárias com o botão "Novo responsável".</div>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl" style="min-width:0">
    <thead><tr><th>Responsável</th><th class="r">Pacientes ativos</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
        <tr>
            <td><strong><?= e($r['name']) ?></strong></td>
            <td class="r num"><?php if ((int) $r['patients'] > 0): ?><a href="<?= e(url('/admin/pacientes?r=' . (int) $r['id'])) ?>"><?= (int) $r['patients'] ?></a><?php else: ?>0<?php endif; ?></td>
            <td><?= $r['active'] ? '<span class="tag ok">ativa</span>' : '<span class="tag">desativada</span>' ?></td>
            <td class="r"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/responsaveis/' . $r['id'])) ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
