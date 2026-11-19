<div class="page-head"><h2>Novo paciente</h2></div>
<form class="panel" method="post" action="<?= e(url('/admin/pacientes')) ?>" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field"><label for="name">Nome completo</label><input class="input" id="name" name="name" maxlength="120" value="<?= e(old('name')) ?>" required></div>
        <div class="field"><label for="email">E-mail (é o login)</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required></div>
        <div class="field"><label for="phone">WhatsApp</label><input class="input" id="phone" name="phone" inputmode="tel" value="<?= e(old('phone')) ?>" placeholder="(11) 99999-8888"></div>
        <div class="field"><label for="goal">Objetivo</label><input class="input" id="goal" name="goal" maxlength="120" value="<?= e(old('goal')) ?>" placeholder="Ex.: Emagrecimento"></div>
        <div class="field"><label for="renewal_date">Vence em</label><input class="input" type="date" id="renewal_date" name="renewal_date" value="<?= e(old('renewal_date', $defaultRenewal)) ?>"></div>
        <div class="field"><label for="password">Senha provisória</label><input class="input" id="password" name="password" value="<?= e($password) ?>" minlength="8" required>
            <p class="hint">O paciente troca no primeiro acesso.</p></div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Criar paciente</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/pacientes')) ?>">Cancelar</a>
    </div>
</form>
