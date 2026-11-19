<div class="page-head"><h2>Auditoria</h2></div>
<p class="muted small">Registro das ações feitas no painel (últimas 150). Serve para conferir quem mudou o quê.</p>
<?php if (!$rows): ?><div class="empty"><strong>Nada registrado ainda</strong></div><?php else: ?>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Quando</th><th>Quem</th><th>Ação</th><th>Detalhe</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="small"><?= e(fmt_datetime($r['created_at'])) ?></td>
            <td><?= e($r['admin_name'] ?? '-') ?></td>
            <td><span class="tag"><?= e($r['action']) ?></span></td>
            <td class="small muted"><?= e(trim(($r['target_type'] ? $r['target_type'] . ' #' . $r['target_id'] : '') . ' ' . ($r['detail'] ?? ''))) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
