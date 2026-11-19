<!doctype html>
<html lang="pt-BR">
<head>
    <?php require ROOT . '/app/Views/partials/head.php'; ?>
</head>
<body class="guest">
<main class="guest-wrap">
    <a class="guest-logo" href="<?= e(url('/')) ?>">
        <img src="<?= e(asset('img/logo-horizontal.svg')) ?>" alt="<?= e(app_name()) ?>" width="240" height="72">
    </a>
    <?php partial('flash'); ?>
    <?= $content ?>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
