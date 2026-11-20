<?php
/*
 * Faixa de aviso quando a renovação está no vermelho (7 dias ou menos / vencida).
 * Aparece nas telas do paciente, menos no Início e no Perfil (que já têm o cartão de renovação).
 * Mostra só o NOME do plano. Nunca valores em R$.
 */
if (!$user || $user['role'] !== 'patient' || $user['status'] !== 'active') { return; }
$bPath = strtok(current_path(), '?');
if ($bPath === '/home' || $bPath === '/perfil' || str_starts_with($bPath, '/perfil/')) { return; }
$bRenew = \App\Services\Renewal::info($user['renewal_date']);
if ($bRenew['seal'] !== 'red') { return; }
$bPlan = !empty($user['plan_id']) ? \App\Services\Plans::name((int) $user['plan_id']) : null;
?>
<div class="renew-banner" role="status">
    <span><?= e(\App\Services\Renewal::headline($bRenew, $bPlan)) ?></span>
    <a class="btn btn-sm" href="<?= e(\App\Services\Renewal::whatsappUrl($user)) ?>" target="_blank" rel="noopener">Renovar</a>
</div>
