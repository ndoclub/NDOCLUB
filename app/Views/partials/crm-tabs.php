<?php
/**
 * Abas do CRM: Quadro | Lista | Reativação | Configurar.
 * $active: 'board', 'leads' (Lista), 'recovery' (Reativação) ou 'config'. Quadro e Lista mostram os mesmos leads em aberto, de dois jeitos.
 */
$active = $active ?? 'leads';
?>
<nav class="tabs scroll" aria-label="CRM">
    <a href="<?= e(url('/admin/crm/quadro')) ?>"<?= $active === 'board' ? ' aria-current="page"' : '' ?>>Quadro</a>
    <a href="<?= e(url('/admin/crm')) ?>"<?= $active === 'leads' ? ' aria-current="page"' : '' ?>>Lista</a>
    <a href="<?= e(url('/admin/crm/reativacao')) ?>"<?= $active === 'recovery' ? ' aria-current="page"' : '' ?>>Reativação</a>
    <a href="<?= e(url('/admin/crm/configurar')) ?>"<?= $active === 'config' ? ' aria-current="page"' : '' ?>>Configurar</a>
</nav>
