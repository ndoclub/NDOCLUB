<?php $inApp = $user !== null; ?>
<div class="<?= $inApp ? 'page ' : '' ?>legal">
    <h1 class="guest-title">Termos e privacidade</h1>
    <p class="muted small">Versão <?= e($version) ?></p>

    <h2>Quem cuida dos seus dados</h2>
    <p>O <?= e(app_name()) ?> é a comunidade ligada ao seu acompanhamento nutricional com o nutricionista Gabriel, responsável pelos dados tratados neste aplicativo.</p>

    <h2>Quais dados usamos</h2>
    <ul>
        <li>Cadastro: nome, e-mail, telefone e data de renovação do acompanhamento.</li>
        <li>Perfil: foto, bio e objetivo.</li>
        <li>Uso do app: check-ins, treinos feitos, pontos, publicações, curtidas e comentários.</li>
        <li>Dados de saúde que você decidir registrar: peso e medida de cintura. São dados sensíveis.</li>
    </ul>

    <h2>Para que usamos</h2>
    <ul>
        <li>Dar acesso ao app, ao plano alimentar e aos treinos.</li>
        <li>Calcular pontos, ranking, nível e sequência de dias.</li>
        <li>Avisar sobre a renovação do acompanhamento.</li>
        <li>Moderar a comunidade e manter a segurança do app.</li>
    </ul>

    <h2>O que os outros membros veem</h2>
    <p>Seu nome, foto, bio, objetivo, nível, sequência, posição no ranking e as suas publicações. Peso, medidas, dieta, e-mail, telefone e data de renovação nunca aparecem para outros membros.</p>

    <h2>Dados de saúde</h2>
    <p>Tratamos peso e medidas com base no seu consentimento, apenas para acompanhar a sua evolução. Você pode retirar o consentimento quando quiser, pedindo a exclusão dos seus dados.</p>

    <h2>Compartilhamento</h2>
    <p>Não vendemos seus dados. O WhatsApp só é aberto quando você toca no botão para falar com o Gabriel.</p>

    <h2>Seus direitos</h2>
    <p>Pela Lei Geral de Proteção de Dados (LGPD) você pode pedir confirmação de que tratamos seus dados, acesso, correção, portabilidade e exclusão. Fale com o Gabriel e o pedido será atendido.</p>

    <h2>Segurança</h2>
    <p>O acesso é protegido por senha. Fotos e arquivos só abrem para quem está logado. Dados de saúde não são guardados no cache do aparelho.</p>

    <h2>Regras de convivência</h2>
    <p><?= text_html($rules) ?></p>

    <?php if (!$inApp): ?>
        <p class="mt-lg"><a class="btn btn-ghost btn-block" href="<?= e(url('/login')) ?>">Voltar</a></p>
    <?php endif; ?>
</div>
