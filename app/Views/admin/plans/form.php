<?php
$isEdit = (bool) $plan;
$action = $isEdit ? '/admin/planos/' . (int) $plan['id'] : '/admin/planos';
$val = fn($k, $d = '') => old($k, $plan[$k] ?? $d);
$price = old('price', ($plan['price'] ?? null) !== null ? number_format((float) $plan['price'], 2, ',', '') : '');
?>
<div class="page-head"><h2><?= $isEdit ? 'Editar plano' : 'Novo plano' ?></h2></div>
<form class="panel" method="post" action="<?= e(url($action)) ?>" style="max-width:640px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field full"><label for="name">Nome do plano</label><input class="input" id="name" name="name" maxlength="60" value="<?= e($val('name')) ?>" placeholder="Ex.: Trimestral" required></div>
        <div class="field"><label for="duration_days">Duração (dias)</label><input class="input" type="number" id="duration_days" name="duration_days" min="7" max="1100" value="<?= e($val('duration_days', '90')) ?>" required>
            <p class="hint">Trimestral 90, semestral 180, anual 365. É o que a renovação soma.</p></div>
        <div class="field"><label for="price">Valor (R$)</label><input class="input" id="price" name="price" inputmode="decimal" value="<?= e($price) ?>" placeholder="Ex.: 450,00">
            <p class="hint">Só o admin vê. Deixe em branco se ainda não definiu.</p></div>
        <div class="field full"><label class="checkbox"><input type="checkbox" name="active" value="1"<?= (!$isEdit || $plan['active']) ? ' checked' : '' ?>><span>Ativo (aparece ao escolher o plano de um paciente)</span></label>
            <p class="hint">Planos não são apagados: desative para esconder, e os pacientes que já estão nele continuam normais.</p></div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Salvar plano</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/planos')) ?>">Cancelar</a>
    </div>
</form>
