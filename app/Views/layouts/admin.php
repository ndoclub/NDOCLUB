<?php
$path = strtok(current_path(), '?');
$is = fn(string $prefix) => $path === $prefix || str_starts_with($path, $prefix . '/');
$finBadge = \App\Services\Payments::attentionStats()['count'];
$nav = [
    ['/admin',            'Painel',        'grid',     $path === '/admin'],
    ['/admin/pacientes',  'Pacientes',     'users',    $is('/admin/pacientes')],
    ['/admin/planos',     'Planos',        'card',     $is('/admin/planos')],
    ['/admin/financeiro', 'Financeiro',    'cash',     $is('/admin/financeiro')],
    ['/admin/modalidades','Modalidades',   'tag',      $is('/admin/modalidades')],
    ['/admin/treinos',    'Treinos',       'dumbbell', $is('/admin/treinos')],
    ['/admin/moderacao',  'Moderação',     'shield',   $is('/admin/moderacao')],
    ['/admin/loja',       'Loja',          'bag',      $is('/admin/loja')],
    ['/admin/resgates',   'Resgates',      'check',    $is('/admin/resgates')],
    ['/admin/pontos',     'Regras de pontos', 'star',  $is('/admin/pontos')],
    ['/admin/ranking',    'Ranking',       'trophy',   $is('/admin/ranking')],
    ['/admin/config',     'Configurações', 'gear',     $is('/admin/config')],
    ['/admin/auditoria',  'Auditoria',     'file',     $is('/admin/auditoria')],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <?php require ROOT . '/app/Views/partials/head.php'; ?>
</head>
<body class="admin">
<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true" tabindex="-1">
<header class="admin-top">
    <label for="nav-toggle" class="burger" aria-label="Abrir menu"><?= icon('menu') ?></label>
    <a class="brand" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('img/logo-nd.svg')) ?>" alt="" width="30" height="29"></a>
    <h1><?= e($title) ?></h1>
</header>
<label for="nav-toggle" class="nav-scrim" aria-hidden="true"></label>
<aside class="admin-nav" aria-label="Menu do painel">
    <a class="admin-logo" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('img/logo-horizontal.svg')) ?>" alt="<?= e(app_name()) ?>" width="170" height="51"></a>
    <nav>
        <?php foreach ($nav as [$href, $label, $ico, $active]): ?>
            <a href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= icon($ico, 20) ?><span><?= e($label) ?></span><?php if ($href === '/admin/financeiro' && $finBadge > 0): ?><b class="nav-badge" title="Pix para cobrar ou conferir"><?= (int) $finBadge ?></b><?php endif; ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('/feed')) ?>"<?= $is('/feed') || $is('/post') ? ' aria-current="page"' : '' ?>><?= icon('chat', 20) ?><span>Ver o feed</span></a>
    </nav>
    <div class="admin-user">
        <span><?= e($user['name'] ?? '') ?></span>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="link-btn" type="submit">Sair</button></form>
    </div>
</aside>
<main class="admin-main" id="main">
    <?php partial('flash'); ?>
    <?= $content ?>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
