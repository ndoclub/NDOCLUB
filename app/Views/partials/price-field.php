<?php
/**
 * Campo "Valor combinado" com a moeda (R$, US$, €). Só no painel admin.
 * Recebe: $value (texto), $currency (BRL/USD/EUR), $placeholder, $hint
 */
?>
<div class="field"><label for="plan_price">Valor combinado</label>
    <div class="row" style="gap:8px">
        <select name="plan_currency" aria-label="Moeda" style="flex:0 0 96px">
            <?php foreach (currencies() as $code => $c): ?>
                <option value="<?= e($code) ?>"<?= $currency === $code ? ' selected' : '' ?>><?= e($c[0]) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="input" id="plan_price" name="plan_price" inputmode="decimal" value="<?= e($value) ?>" placeholder="<?= e($placeholder) ?>">
    </div>
    <p class="hint"><?= e($hint) ?></p></div>
