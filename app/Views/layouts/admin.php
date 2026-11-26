<?php
$path = strtok(current_path(), '?');
$is = fn(string $prefix) => $path === $prefix || str_starts_with($path, $prefix . '/');
$finBadge = \App\Services\Payments::attentionStats()['count'];
$crmBadge = (int) \App\Services\Leads::attention()['due']; // retornos de leads atrasados ou para hoje (09.4)
$jrBadge = (int) \App\Services\Journey::attention()['due']; // tarefas da Jornada atrasadas ou para hoje (Atualização 11)
$appBadge = (int) \App\Services\AppModule::pending()['total']; // resgates pendentes + denúncias abertas (09.5)
// Menu em grupos: o que a equipe usa todo dia, os cadastros, o que é do app do paciente e o sistema.
// 09.5: o grupo "App do paciente" é uma barra com seta (recolhida); abre sozinha quando a página é do app.
$navGroups = [
    'Dia a dia' => [
        ['/admin',             'Painel',         'grid',     $path === '/admin'],
        ['/admin/pacientes',   'Pacientes',      'users',    $is('/admin/pacientes') || $is('/admin/importar')],
        ['/admin/jornada',     'Jornada',        'flag',     $is('/admin/jornada')],
        [\App\Services\Leads::homeUrl(), 'CRM',        'filter',   $is('/admin/crm')],
        ['/admin/aniversarios', 'Aniversários',  'gift',     $is('/admin/aniversarios')],
        ['/admin/financeiro',  'Financeiro',     'cash',     $is('/admin/financeiro')],
    ],
    'Cadastros' => [
        ['/admin/planos',      'Planos',         'card',     $is('/admin/planos')],
        ['/admin/responsaveis', 'Responsáveis',  'users',    $is('/admin/responsaveis')],
        ['/admin/modalidades', 'Modalidades',    'tag',      $is('/admin/modalidades')],
    ],
    'App do paciente' => [
        ['/admin/app',         'Visão geral',    'grid',     $path === '/admin/app'],
        ['/admin/treinos',     'Treinos',        'dumbbell', $is('/admin/treinos')],
        ['/admin/ranking',     'Ranking',        'trophy',   $is('/admin/ranking')],
        ['/admin/loja',        'Loja',           'bag',      $is('/admin/loja')],
        ['/admin/resgates',    'Resgates',       'check',    $is('/admin/resgates')],
        ['/admin/pontos',      'Regras de pontos', 'star',   $is('/admin/pontos')],
        ['/admin/moderacao',   'Moderação',      'shield',   $is('/admin/moderacao')],
        ['/admin/app/ajustes', 'Ajustes do app', 'gear',     $is('/admin/app/ajustes')],
        ['/feed',              'Ver o feed',     'chat',     $is('/feed') || $is('/post')],
    ],
    'Sistema' => [
        ['/admin/config',      'Configurações',  'gear',     $is('/admin/config')],
        ['/admin/auditoria',   'Auditoria',      'file',     $is('/admin/auditoria')],
    ],
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
    <a class="brand" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('img/logo-nd-vinho.svg')) ?>" alt="" width="30" height="29"></a>
    <h1><?= e($title) ?></h1>
</header>
<label for="nav-toggle" class="nav-scrim" aria-hidden="true"></label>
<aside class="admin-nav" aria-label="Menu do painel">
    <a class="admin-logo" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('img/logo-circular-claro.png')) ?>" alt="<?= e(app_name()) ?>" width="150" height="156"></a>
    <nav>
        <?php $gi = 0; foreach ($navGroups as $gLabel => $gItems): $gi++; ?>
        <?php if ($gLabel === 'App do paciente'): $appOpen = false; foreach ($gItems as $gi2) { if ($gi2[3]) { $appOpen = true; } } ?>
        <details class="nav-sec nav-fold"<?= $appOpen ? ' open' : '' ?>>
            <summary><?= icon('trophy', 20) ?><span><?= e($gLabel) ?></span><?php if ($appBadge > 0): ?><b class="nav-badge" title="Resgates e denúncias esperando por você"><?= $appBadge ?></b><?php endif; ?><i class="nav-caret" aria-hidden="true"></i></summary>
            <?php foreach ($gItems as [$href, $label, $ico, $active]): ?>
                <a class="nav-sub" href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><span><?= e($label) ?></span><?php if ($appBadge > 0 && ($href === '/admin/resgates' || $href === '/admin/moderacao')): $nb = $href === '/admin/resgates' ? \App\Services\AppModule::pending()['redemptions'] : \App\Services\AppModule::pending()['reports']; if ($nb > 0): ?><b class="nav-badge"><?= (int) $nb ?></b><?php endif; endif; ?></a>
            <?php endforeach; ?>
        </details>
        <?php continue; endif; ?>
        <div class="nav-sec" role="group" aria-labelledby="nav-g<?= $gi ?>">
            <p class="nav-label" id="nav-g<?= $gi ?>"><?= e($gLabel) ?></p>
            <?php foreach ($gItems as [$href, $label, $ico, $active]): ?>
                <a href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= icon($ico, 20) ?><span><?= e($label) ?></span><?php if ($href === '/admin/financeiro' && $finBadge > 0): ?><b class="nav-badge" title="Pix para cobrar ou conferir"><?= (int) $finBadge ?></b><?php endif; ?><?php if ($href === '/admin/jornada' && $jrBadge > 0): ?><b class="nav-badge" title="Tarefas da Jornada atrasadas ou para hoje"><?= $jrBadge ?></b><?php endif; ?><?php if ($label === 'CRM' && $crmBadge > 0): ?><b class="nav-badge" title="Retornos de leads atrasados ou para hoje"><?= $crmBadge ?></b><?php endif; ?></a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>
    <p class="admin-motto">Constância vence a perfeição porque todo dia conta.</p>
    <div class="admin-user">
        <span><?= e($user['name'] ?? '') ?></span>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="link-btn" type="submit">Sair</button></form>
    </div>
</aside>
<main class="admin-main<?= !empty($wide) ? ' admin-main-wide' : '' ?>" id="main">
    <div class="admin-bar">
        <p class="motto">Constância vence a perfeição<br>porque todo dia conta.</p>
        <details class="user-menu">
            <summary><span class="ini"><?= e(initials((string) ($user['name'] ?? '?'))) ?></span><span class="user-name"><?= e(first_name((string) ($user['name'] ?? ''))) ?></span><span class="caret" aria-hidden="true">▾</span></summary>
            <div class="menu-pop">
                <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout', 18) ?> Sair</button></form>
            </div>
        </details>
    </div>
    <?php partial('flash'); ?>
    <?= $content ?>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
