<div class="page-head"><h2>Novo paciente</h2></div>
<form class="panel" method="post" action="<?= e(url('/admin/pacientes')) ?>" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field"><label for="name">Nome completo</label><input class="input" id="name" name="name" maxlength="120" value="<?= e(old('name')) ?>" required></div>
        <div class="field"><label for="email">E-mail (é o login)</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required></div>
        <div class="field"><label for="phone">WhatsApp</label><input class="input" id="phone" name="phone" inputmode="tel" value="<?= e(old('phone')) ?>" placeholder="(11) 99999-8888"></div>
        <div class="field"><label for="goal">Objetivo</label><input class="input" id="goal" name="goal" maxlength="120" value="<?= e(old('goal')) ?>" placeholder="Ex.: Emagrecimento"></div>
        <div class="field"><label for="plan_id">Plano</label>
            <select id="plan_id" name="plan_id" data-plan-select data-date-target="#renewal_date" data-price-target="#plan_price">
                <option value="">Sem plano definido</option>
                <?php foreach ($plans as $pl): ?>
                    <option value="<?= (int) $pl['id'] ?>" data-days="<?= (int) $pl['duration_days'] ?>" data-price="<?= $pl['price'] !== null ? e(number_format((float) $pl['price'], 2, ',', '')) : '' ?>"<?= (string) old('plan_id') === (string) $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?> · <?= (int) $pl['duration_days'] ?> dias</option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Ao escolher, a data de vencimento é preenchida sozinha (você pode mudar).</p></div>
        <div class="field"><label for="plan_price">Valor combinado (R$)</label><input class="input" id="plan_price" name="plan_price" inputmode="decimal" value="<?= e(old('plan_price')) ?>" placeholder="Igual ao do plano">
            <p class="hint">Só preencha se este paciente tem um valor diferente. Só o admin vê.</p></div>
        <div class="field"><label for="renewal_date">Vence em</label><input class="input" type="date" id="renewal_date" name="renewal_date" value="<?= e(old('renewal_date', $defaultRenewal)) ?>"></div>
        <div class="field"><label for="password">Senha provisória</label><input class="input" id="password" name="password" value="<?= e($password) ?>" minlength="8" required>
            <p class="hint">O paciente troca no primeiro acesso.</p></div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Criar paciente</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/pacientes')) ?>">Cancelar</a>
    </div>
</form>
