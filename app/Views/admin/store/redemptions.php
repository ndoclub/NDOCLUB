<?php
$filters = ['open' => 'Em andamento', 'delivered' => 'Entregues', 'rejected' => 'Recusados', 'all' => 'Todos'];
$status = ['pending' => ['Pendente', 'warn'], 'approved' => ['Aprovado', 'ok'], 'delivered' => ['Entregue', 'ok'], 'rejected' => ['Recusado', 'bad']];
?>
<div class="page-head"><h2>Resgates</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/loja')) ?>">Ver loja</a></div>
<div class="chips">
    <?php foreach ($filters as $k => $label): ?><a class="chip" href="<?= e(url('/admin/resgates?s=' . $k)) ?>"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</div>
<?php if (!$rows): ?>
    <div class="empty"><strong>Nenhum resgate aqui</strong>Quando um paciente trocar pontos por um brinde, o pedido aparece nesta lista.</div>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl" style="min-width:720px">
    <thead><tr><th>Pedido</th><th>Paciente</th><th>Status</th><th>Ação</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): [$txt, $cls] = $status[$r['status']] ?? [$r['status'], '']; ?>
        <tr>
            <td><strong><?= e($r['item_name']) ?></strong><br><span class="small muted"><?= pts($r['cost_points']) ?> pts - <?= e(fmt_datetime($r['created_at'])) ?></span></td>
            <td><?= e($r['user_name']) ?>
                <?php $wa = wa_phone_url($r['phone'], 'Oi, ' . first_name($r['user_name']) . '! Sobre o seu resgate no No Days Off Club: ' . $r['item_name']); if ($wa): ?>
                    <br><a class="small" href="<?= e($wa) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a><?php endif; ?></td>
            <td><span class="tag <?= e($cls) ?>"><?= e($txt) ?></span><?= $r['admin_note'] ? '<br><span class="small muted">' . e($r['admin_note']) . '</span>' : '' ?></td>
            <td>
                <?php if (in_array($r['status'], ['pending', 'approved'], true)): ?>
                    <form class="row wrap" method="post" action="<?= e(url('/admin/resgates/' . $r['id'] . '/status')) ?>">
                        <?= csrf_field() ?>
                        <input class="input" type="text" name="note" maxlength="200" placeholder="Recado (opcional)" aria-label="Recado para o paciente" style="max-width:200px">
                        <?php if ($r['status'] === 'pending'): ?>
                            <button class="btn btn-sm" type="submit" name="status" value="approved">Aprovar</button>
                        <?php else: ?>
                            <button class="btn btn-sm" type="submit" name="status" value="delivered">Marcar entregue</button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-ghost btn-danger" type="submit" name="status" value="rejected" data-confirm-click="Recusar e devolver os pontos?">Recusar</button>
                    </form>
                <?php else: ?><span class="muted small"><?= e(fmt_date($r['updated_at'])) ?></span><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
