<?php
$path = strtok(current_path(), '?');
$is = fn(string $prefix) => $path === $prefix || str_starts_with($path, $prefix . '/');
$tabs = [
    ['/home',    'Início',   'home',     $is('/home') || $is('/checkin') || $is('/dieta') || $is('/extrato') || $is('/evolucao')],
    ['/feed',    'Feed',     'chat',     $is('/feed') || $is('/post')],
    ['/treino',  'Treino',   'dumbbell', $is('/treino')],
    ['/ranking', 'Ranking',  'trophy',   $is('/ranking')],
    ['/loja',    'Loja',     'bag',      $is('/loja')],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <?php require ROOT . '/app/Views/partials/head.php'; ?>
</head>
<body class="app">
<div class="shell">
    <header class="topbar">
        <a class="brand" href="<?= e(url('/home')) ?>" aria-label="Início">
            <img src="<?= e(asset('img/logo-nd.svg')) ?>" alt="" width="34" height="33">
        </a>
        <h1><?= e($title) ?></h1>
        <?php if ($user): ?>
            <a class="topbar-me" href="<?= e(url($user['role'] === 'admin' ? '/admin' : '/perfil')) ?>" aria-label="Meu perfil">
                <?= avatar($user, 36) ?>
            </a>
        <?php endif; ?>
    </header>

    <?php partial('renew-banner', ['user' => $user]); ?>
    <?php partial('flash'); ?>
    <main id="main">
        <?= $content ?>
    </main>

    <?php if ($user && $user['role'] === 'patient'): ?>
    <nav class="tabbar" aria-label="Navegação principal">
        <?php foreach ($tabs as [$href, $label, $ico, $active]): ?>
            <a class="tab" href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                <?= icon($ico) ?><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
