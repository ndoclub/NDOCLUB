<h1 class="guest-title">Entrar</h1>
<p class="lead">Use o e-mail e a senha que o Gabriel enviou para você.</p>

<form method="post" action="<?= e(url('/login')) ?>" class="mt">
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">E-mail</label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" inputmode="email" autocapitalize="none" required>
    </div>
    <div class="field">
        <label for="password">Senha</label>
        <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-block" type="submit">Entrar</button>
</form>

<p class="small muted mt-lg">Esqueceu a senha? Peça uma nova para o Gabriel.<br>
    <a href="<?= e(url('/termos')) ?>">Termos e privacidade</a></p>
