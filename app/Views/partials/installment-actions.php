<?php
/**
 * Botões de uma parcela (painel admin).
 * - Cartão em R$: cai sozinho, então não há botão de "Recebi".
 * - Pix e demais formas: "Cobrar" (WhatsApp, quando vence hoje ou já venceu), clipe do comprovante e "Recebi";
 *   depois de recebida: "Comprovante" (ou anexar) e "Desfazer".
 * - Em USD/EUR, ao marcar como recebida informa-se quanto caiu em reais.
 * - Parcela cancelada: "Reativar".
 * Recebe: $i (parcela já com auto, wa, proof_file...), $back (caminho interno para voltar), $showCancel (ficha do paciente)
 */
$showCancel = !empty($showCancel);
$id = (int) $i['id'];
$foreign = $i['currency'] !== 'BRL';
$status = $i['status'];
$received = $status === 'received';
$auto = !empty($i['auto']);
$needsBrl = $received && $foreign && $i['received_brl'] === null;
$hasProof = !empty($i['proof_file']);
?>
<?php if ($status === 'canceled'): ?>
    <form class="inline-form" method="post" action="<?= e(url('/admin/parcelas/' . $id . '/desfazer')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <button class="btn btn-ghost btn-sm" type="submit">Reativar</button>
    </form>
<?php else: ?>
    <?php if (!empty($i['wa'])): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e($i['wa']) ?>" target="_blank" rel="noopener" title="Abre o WhatsApp com a mensagem de cobrança pronta"><?= icon('chat', 16) ?> Cobrar</a>
    <?php endif; ?>

    <?php if (!$received && !$auto): ?>
        <form class="inline-form" method="post" action="<?= e(url('/admin/parcelas/' . $id . '/receber')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <?php if ($foreign): ?>
                <input class="input" name="received_brl" inputmode="decimal" aria-label="Quanto caiu em reais (R$)" placeholder="R$ que caiu" required>
            <?php endif; ?>
            <label class="btn btn-ghost btn-sm file-btn" title="Anexar comprovante (foto ou PDF)"><?= icon('clip', 16) ?><span class="sr-only">Anexar comprovante</span><input type="file" name="proof" accept="image/*,application/pdf" data-file-btn></label>
            <button class="btn btn-sm" type="submit">Recebi</button>
        </form>
    <?php elseif ($needsBrl): ?>
        <form class="inline-form" method="post" action="<?= e(url('/admin/parcelas/' . $id . '/receber')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <input class="input" name="received_brl" inputmode="decimal" aria-label="Quanto caiu em reais (R$)" placeholder="R$ que caiu" required>
            <button class="btn btn-ghost btn-sm" type="submit">Salvar R$</button>
        </form>
    <?php endif; ?>

    <?php if ($received && !$auto): ?>
        <?php if ($hasProof): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/parcelas/' . $id . '/comprovante')) ?>" target="_blank" rel="noopener"><?= icon('clip', 16) ?> Comprovante</a>
        <?php else: ?>
            <form class="inline-form" method="post" action="<?= e(url('/admin/parcelas/' . $id . '/comprovante')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <label class="btn btn-ghost btn-sm file-btn" title="Anexar comprovante (foto ou PDF)"><?= icon('clip', 16) ?><span class="sr-only">Anexar comprovante</span><input type="file" name="proof" accept="image/*,application/pdf" required data-file-btn></label>
                <button class="btn btn-ghost btn-sm" type="submit">Anexar</button>
            </form>
        <?php endif; ?>
        <form class="inline-form" method="post" action="<?= e(url('/admin/parcelas/' . $id . '/desfazer')) ?>" data-confirm="Voltar esta parcela para pendente?">
            <?= csrf_field() ?>
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <button class="btn btn-ghost btn-sm" type="submit">Desfazer</button>
        </form>
    <?php endif; ?>

    <?php if ($showCancel): ?>
        <form class="inline-form" method="post" action="<?= e(url('/admin/parcelas/' . $id . '/cancelar')) ?>" data-confirm="Cancelar esta parcela? Ela sai das somas (use para estorno ou paciente que parou de pagar).">
            <?= csrf_field() ?>
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <button class="btn btn-ghost btn-sm" type="submit">Cancelar</button>
        </form>
    <?php endif; ?>
<?php endif; ?>
