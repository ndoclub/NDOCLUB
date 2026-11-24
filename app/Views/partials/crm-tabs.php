<?php
/**
 * Abas do CRM: Funil | Follow-up | Lista | Reativação | Configurar.
 * $active: 'board' (Funil), 'follow' (Follow-up), 'leads' (Lista), 'recovery' (Reativação) ou 'config'.
 * Funil, Follow-up e Lista mostram os mesmos leads, de três jeitos: por etapa, por prazo do retorno e em tabela.
 */
$active = $active ?? 'leads';
$due = (int) (\App\Services\Leads::attention()['due'] ?? 0);
?>
<nav class="tabs scroll" aria-label="CRM">
    <a href="<?= e(url('/admin/crm/quadro')) ?>"<?= $active === 'board' ? ' aria-current="page"' : '' ?>>Funil</a>
    <a href="<?= e(url('/admin/crm/follow-up')) ?>"<?= $active === 'follow' ? ' aria-current="page"' : '' ?>>Follow-up<?php if ($due > 0): ?> <b class="tab-n" title="<?= $due ?> <?= $due === 1 ? 'retorno atrasado ou para hoje' : 'retornos atrasados ou para hoje' ?>"><?= $due ?></b><?php endif; ?></a>
    <a href="<?= e(url('/admin/crm')) ?>"<?= $active === 'leads' ? ' aria-current="page"' : '' ?>>Lista</a>
    <a href="<?= e(url('/admin/crm/reativacao')) ?>"<?= $active === 'recovery' ? ' aria-current="page"' : '' ?>>Reativação</a>
    <a href="<?= e(url('/admin/crm/configurar')) ?>"<?= $active === 'config' ? ' aria-current="page"' : '' ?>>Configurar</a>
</nav>
