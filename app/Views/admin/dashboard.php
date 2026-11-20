<?php
/** Painel: cabeçalho + abas. O conteúdo de cada aba fica em dashboard_executiva.php e dashboard_operacao.php. */
$firstName = first_name((string) ($user['name'] ?? 'Admin'));
?>
<div class="page-head">
    <div class="greet">
        <h2>Olá, <?= e($firstName) ?>!</h2>
        <p class="muted small">Aqui está o resumo da operação de hoje.</p>
    </div>
    <a class="btn btn-sm" href="<?= e(url('/admin/pacientes/novo')) ?>"><?= icon('plus', 18) ?> Novo paciente</a>
</div>

<nav class="tabs" aria-label="Abas do painel">
    <a href="<?= e(url('/admin')) ?>"<?= $aba === 'executiva' ? ' aria-current="page"' : '' ?>>Visão executiva</a>
    <a href="<?= e(url('/admin?aba=operacao')) ?>"<?= $aba === 'operacao' ? ' aria-current="page"' : '' ?>>Operação</a>
</nav>

<?php require ROOT . '/app/Views/admin/dashboard_' . ($aba === 'operacao' ? 'operacao' : 'executiva') . '.php'; ?>
