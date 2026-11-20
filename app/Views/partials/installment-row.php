<?php
/**
 * Uma linha da lista do Financeiro (painel admin): paciente, contrato (plano, total e forma),
 * a parcela ("Parcela 3/12 · R$ 300,00"), a situação e as ações.
 * Recebe: $r (parcela da lista, já com st/auto/wa), $back (caminho interno para voltar)
 */
$P = \App\Services\Payments::class;
$n = (int) $r['total_n'];
$contract = [];
if (!empty($r['plan_name'])) {
    $contract[] = e($r['plan_name']);
}
$contract[] = e(money((float) $r['payment_total'], 2, $r['currency']));
$contract[] = e($P::methodText($r['method'], $n)) . (!empty($r['card_brand']) ? ' · ' . e($P::brandLabel($r['card_brand'])) : '');
?>
<div class="fin-row<?= !empty($r['late']) ? ' is-late' : '' ?>">
    <div class="fin-who">
        <a class="fin-name" href="<?= e(url('/admin/pacientes/' . (int) $r['user_id'])) ?>"><?= e($r['patient_name']) ?></a>
        <span class="small muted"><?= implode(' · ', $contract) ?></span>
    </div>
    <div class="fin-part">
        <strong class="num"><?= $n > 1 ? 'Parcela ' . (int) $r['number'] . '/' . $n : 'Pagamento à vista' ?> · <?= e(money((float) $r['amount'], 2, $r['currency'])) ?></strong>
        <?php if (!empty($r['fee']) && $r['fee'] > 0): ?><span class="small muted">líquido <?= e(money((float) $r['net'])) ?> (taxa <?= e(rtrim(rtrim(number_format((float) $r['fee_pct'], 2, ',', ''), '0'), ',')) ?>%)</span><?php endif; ?>
        <span><span class="tag<?= $r['st']['tag'] !== '' ? ' ' . e($r['st']['tag']) : '' ?>"><?= e($r['st']['text']) ?></span><?php if ($r['status'] === 'received' && $r['currency'] !== 'BRL'): ?> <span class="small muted"><?= $r['received_brl'] !== null ? 'caiu ' . e(money((float) $r['received_brl'])) : 'falta informar o R$' ?></span><?php endif; ?></span>
    </div>
    <div class="fin-actions"><?php partial('installment-actions', ['i' => $r, 'back' => $back]); ?></div>
</div>
