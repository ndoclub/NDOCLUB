<?php
/** Abas do CRM. $active: 'leads' ou 'config'. As abas Funil, Follow-ups e Visão geral entram nas próximas etapas. */
$active = $active ?? 'leads';
?>
<nav class="tabs" aria-label="CRM">
    <a href="<?= e(url('/admin/crm')) ?>"<?= $active === 'leads' ? ' aria-current="page"' : '' ?>>Leads</a>
    <a href="<?= e(url('/admin/crm/configurar')) ?>"<?= $active === 'config' ? ' aria-current="page"' : '' ?>>Configurar</a>
</nav>
