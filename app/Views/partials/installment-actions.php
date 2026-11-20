<?php
/**
 * Botões de uma parcela (painel admin): "Recebi" / "Desfazer".
 * Em USD/EUR, ao marcar como recebida informa-se quanto caiu em reais.
 * Recebe: $i (id, status, currency, received_brl), $back (caminho interno para voltar)
 */
$foreign = $i['currency'] !== 'BRL';
$received = $i['status'] === 'received';
$needsBrl = $received && $foreign && $i['received_brl'] === null;
?>
<?php if (!$received || $needsBrl): ?>
    <form class="inline-form" method="post" action="<?= e(url('/admin/parcelas/' . (int) $i['id'] . '/receber')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <?php if ($foreign): ?>
            <input class="input" name="received_brl" inputmode="decimal" aria-label="Quanto caiu em reais (R$)" placeholder="R$ que caiu" required>
        <?php endif; ?>
        <button class="btn btn-sm<?= $needsBrl ? ' btn-ghost' : '' ?>" type="submit"><?= $needsBrl ? 'Salvar R$' : 'Recebi' ?></button>
    </form>
<?php endif; ?>
<?php if ($received): ?>
    <form class="inline-form" method="post" action="<?= e(url('/admin/parcelas/' . (int) $i['id'] . '/desfazer')) ?>" data-confirm="Voltar esta parcela para pendente?">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <button class="btn btn-ghost btn-sm" type="submit">Desfazer</button>
    </form>
<?php endif; ?>
