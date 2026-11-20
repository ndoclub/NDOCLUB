<?php
/**
 * Campos de pagamento (só painel admin), dentro de um <form data-pay-form data-fees="...">:
 * valor + moeda, forma, parcelas, bandeira e taxa (cartão em R$), 1ª parcela, "já recebida", R$ que caiu.
 * Os campos vão direto na grade .form-grid do formulário que incluir este arquivo.
 *
 * Recebe:
 *   $idp          prefixo dos ids (ex.: 'c_')
 *   $amountName   name do valor ('plan_price', 'amount' ou 'total')       $amountId $amountLabel $amountValue $amountPlaceholder $amountHint $amountRequired
 *   $currencyName name da moeda ('plan_currency' ou 'currency')           $currency
 *   $method       forma escolhida ('' = ainda não escolheu)               $allowNone (true no cadastro: oferece "Só cadastrar, sem pagamento agora")
 *   $installments parcelas escolhidas                                     $due (AAAA-MM-DD)
 *   $paidMode     'first' (caixa "1ª parcela já recebida") ou 'count' (quantas já recebidas)   $paidChecked / $paidCount
 *   $brand, $fee  bandeira e taxa (%) já escolhidas, se houver
 */
$idp = $idp ?? '';
$amountId = $amountId ?? ($idp . 'amount');
$amountLabel = $amountLabel ?? 'Valor total vendido';
$amountValue = $amountValue ?? '';
$amountPlaceholder = $amountPlaceholder ?? 'Ex.: 1200,00';
$amountHint = $amountHint ?? '';
$amountRequired = !empty($amountRequired);
$currency = currency_or_brl($currency ?? 'BRL');
$method = (string) ($method ?? '');
$allowNone = !empty($allowNone);
$installments = (int) ($installments ?? 1);
$paidMode = $paidMode ?? 'first';
$paidChecked = !empty($paidChecked);
$paidCount = (int) ($paidCount ?? 0);
$brand = (string) ($brand ?? '');
$fee = (string) ($fee ?? '');
$noPay = $allowNone && ($method === '' || $method === 'none');
$auto = $method === 'cartao' && $currency === 'BRL';
$needBrl = $currency !== 'BRL' && !$auto && !$noPay && ($paidMode === 'first' ? $paidChecked : $paidCount > 0);
?>
<div class="field"><label for="<?= e($amountId) ?>"><?= e($amountLabel) ?></label>
    <div class="row" style="gap:8px">
        <select name="<?= e($currencyName) ?>" aria-label="Moeda" data-pay-currency style="flex:0 0 96px">
            <?php foreach (currencies() as $code => $c): ?>
                <option value="<?= e($code) ?>"<?= $currency === $code ? ' selected' : '' ?>><?= e($c[0]) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="input" id="<?= e($amountId) ?>" name="<?= e($amountName) ?>" inputmode="decimal" value="<?= e($amountValue) ?>" placeholder="<?= e($amountPlaceholder) ?>"<?= $amountRequired ? ' required' : '' ?> data-pay-amount>
    </div>
    <?php if ($amountHint !== ''): ?><p class="hint"><?= e($amountHint) ?></p><?php endif; ?></div>

<div class="field"><label for="<?= e($idp) ?>method">Forma de pagamento</label>
    <select id="<?= e($idp) ?>method" name="method" data-pay-method<?= $allowNone ? ' required' : '' ?>>
        <?php if ($allowNone): ?>
            <option value=""<?= $method === '' ? ' selected' : '' ?> disabled>Escolha a forma...</option>
        <?php endif; ?>
        <?php foreach (\App\Services\Payments::methods() as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $method === $k ? ' selected' : '' ?>><?= e($k === 'pix' ? 'Pix (à vista ou parcelado)' : $label) ?></option>
        <?php endforeach; ?>
        <?php if ($allowNone): ?>
            <option value="none"<?= $method === 'none' ? ' selected' : '' ?>>Só cadastrar, sem pagamento agora</option>
        <?php endif; ?>
    </select></div>

<div class="pay-details" data-pay-details<?= $noPay ? ' hidden' : '' ?>>
    <div class="field"><label for="<?= e($idp) ?>installments">Parcelas</label>
        <select id="<?= e($idp) ?>installments" name="installments" data-pay-n>
            <?php for ($n = 1; $n <= 12; $n++): ?>
                <option value="<?= $n ?>"<?= $installments === $n ? ' selected' : '' ?>><?= $n === 1 ? 'À vista (1x)' : $n . 'x' ?></option>
            <?php endfor; ?>
        </select>
        <p class="hint">Até 12x. No Pix parcelado, combine o dia de cada parcela com o paciente.</p></div>

    <div class="field" data-auto-only<?= $auto ? '' : ' hidden' ?>><label for="<?= e($idp) ?>brand">Bandeira do cartão</label>
        <select id="<?= e($idp) ?>brand" name="card_brand" data-pay-brand>
            <option value="">Não informar</option>
            <?php foreach (\App\Services\Payments::brands() as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $brand === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select></div>
    <div class="field" data-auto-only<?= $auto ? '' : ' hidden' ?>><label for="<?= e($idp) ?>fee">Taxa do cartão (%)</label>
        <input class="input" id="<?= e($idp) ?>fee" name="fee_pct" inputmode="decimal" value="<?= e($fee) ?>" placeholder="0" data-pay-fee>
        <p class="hint">Vem da tabela em Financeiro &gt; Taxas do cartão (você pode corrigir aqui). Cada parcela entra pelo valor líquido.</p></div>

    <div class="field"><label for="<?= e($idp) ?>first_due"><?= $paidMode === 'count' ? 'Vencimento da 1ª parcela' : '1ª parcela em' ?></label>
        <input class="input" type="date" id="<?= e($idp) ?>first_due" name="first_due" value="<?= e($due ?? today()) ?>" data-pay-due>
        <p class="hint">As próximas vencem todo mês, no mesmo dia. No cartão, a 1ª costuma cair 30 dias depois; se cair em fim de semana ou feriado, vai para o próximo dia útil.</p></div>

    <?php if ($paidMode === 'count'): ?>
        <div class="field" data-auto-hide<?= $auto ? ' hidden' : '' ?>><label for="<?= e($idp) ?>paid_count">Parcelas já recebidas</label>
            <select id="<?= e($idp) ?>paid_count" name="paid_count" data-pay-paidcount>
                <?php for ($n = 0; $n <= 12; $n++): ?><option value="<?= $n ?>"<?= $paidCount === $n ? ' selected' : '' ?>><?= $n === 0 ? 'Nenhuma' : $n ?></option><?php endfor; ?>
            </select>
            <p class="hint">As primeiras parcelas, na ordem. No cartão em R$ não precisa: as parcelas vencidas caem sozinhas.</p></div>
    <?php else: ?>
        <div class="field align-input" data-auto-hide<?= $auto ? ' hidden' : '' ?>><label class="checkbox"><input type="checkbox" name="first_paid" value="1" data-pay-paid<?= $paidChecked ? ' checked' : '' ?>><span>1ª parcela já recebida</span></label></div>
    <?php endif; ?>

    <div class="field" data-brl-field<?= $needBrl ? '' : ' hidden' ?>><label for="<?= e($idp) ?>received_brl">Quanto caiu em reais (R$)</label>
        <input class="input" id="<?= e($idp) ?>received_brl" name="received_brl" inputmode="decimal" placeholder="Ex.: 4950,00">
        <p class="hint">Em dólar/euro, o total em R$ usa o valor que creditou na conta (já descontadas as taxas).</p></div>
</div>
