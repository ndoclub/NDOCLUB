<section class="page">
    <form method="post" action="<?= e(url('/perfil/editar')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="row mb">
            <?= avatar($p, 72) ?>
            <div class="field" style="margin:0;flex:1">
                <label for="avatar">Foto de perfil</label>
                <input class="input" type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
            </div>
        </div>
        <div class="field">
            <label for="name">Nome</label>
            <input class="input" type="text" id="name" name="name" maxlength="120" value="<?= e(old('name', $p['name'])) ?>" required>
        </div>
        <div class="field">
            <label for="goal">Seu objetivo</label>
            <input class="input" type="text" id="goal" name="goal" maxlength="120" value="<?= e(old('goal', $p['goal'] ?? '')) ?>" placeholder="Ex.: Ganhar massa magra">
        </div>
        <div class="field">
            <label for="bio">Bio</label>
            <textarea class="input" id="bio" name="bio" maxlength="280" style="min-height:90px"><?= e(old('bio', $p['bio'] ?? '')) ?></textarea>
            <p class="hint">Aparece para os outros membros. Até 280 caracteres.</p>
        </div>
        <button class="btn btn-block" type="submit">Salvar perfil</button>
    </form>

    <?php if (!empty($p['avatar'])): ?>
        <form method="post" action="<?= e(url('/perfil/excluir-foto')) ?>" class="mt" data-confirm="Remover a foto de perfil?">
            <?= csrf_field() ?><button class="link-btn danger" type="submit">Remover foto atual</button>
        </form>
    <?php endif; ?>
</section>

<section class="section">
    <h2 class="section-title">Trocar senha</h2>
    <form method="post" action="<?= e(url('/perfil/senha')) ?>">
        <?= csrf_field() ?>
        <div class="field"><label for="cp">Senha atual</label><input class="input" type="password" id="cp" name="current_password" autocomplete="current-password" required></div>
        <div class="field"><label for="np">Nova senha</label><input class="input" type="password" id="np" name="new_password" autocomplete="new-password" minlength="8" required></div>
        <div class="field"><label for="pc">Repita a nova senha</label><input class="input" type="password" id="pc" name="password_confirm" autocomplete="new-password" minlength="8" required></div>
        <button class="btn btn-ghost btn-block" type="submit">Trocar senha</button>
    </form>
</section>

<section class="section">
    <h2 class="section-title">Seus dados</h2>
    <p class="muted small">Para ver, corrigir ou excluir os seus dados, fale com o Gabriel. Leia também os <a href="<?= e(url('/termos')) ?>">termos e a política de privacidade</a>.</p>
</section>
