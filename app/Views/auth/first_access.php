<h1 class="guest-title">Primeiro acesso</h1>
<p class="lead">
    <?php if ($needPassword && $needConsent): ?>Escolha a sua senha e leia o termo de consentimento para começar.
    <?php elseif ($needPassword): ?>Escolha a sua senha pessoal para continuar.
    <?php else: ?>O termo de consentimento foi atualizado. Leia e aceite para continuar.<?php endif; ?>
</p>

<form method="post" action="<?= e(url('/primeiro-acesso')) ?>" class="mt">
    <?= csrf_field() ?>
    <?php if ($needPassword): ?>
        <div class="field">
            <label for="password">Nova senha</label>
            <input class="input" type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>
            <p class="hint">Mínimo de 8 caracteres.</p>
        </div>
        <div class="field">
            <label for="password_confirm">Repita a nova senha</label>
            <input class="input" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="8" required>
        </div>
    <?php endif; ?>

    <?php if ($needConsent): ?>
        <div class="panel legal">
            <p><strong>Resumo do termo (versão <?= e($version) ?>)</strong></p>
            <ul>
                <li>Usamos seus dados de cadastro, de uso do app e os dados de saúde que você registrar (como peso e cintura) para acompanhar o seu progresso.</li>
                <li>Peso, medidas, dieta e data de renovação são privados: só você e a equipe do Gabriel veem.</li>
                <li>Seu nome, foto, objetivo, nível e publicações aparecem para os outros membros.</li>
                <li>Você pode pedir acesso, correção ou exclusão dos seus dados a qualquer momento.</li>
            </ul>
            <p><a href="<?= e(url('/termos')) ?>" target="_blank" rel="noopener">Ler o termo completo</a></p>
        </div>
        <div class="field">
            <label class="checkbox">
                <input type="checkbox" name="accept" value="1" required>
                <span>Li e aceito o termo de consentimento e a política de privacidade.</span>
            </label>
        </div>
    <?php endif; ?>

    <button class="btn btn-block" type="submit">Continuar</button>
</form>

<form method="post" action="<?= e(url('/logout')) ?>" class="center mt">
    <?= csrf_field() ?>
    <button class="link-btn" type="submit">Sair</button>
</form>
