<?php
use App\Services\LeadConversion;
use App\Services\Payments;

$id = (int) $lead['id'];
$isLink = $c['mode'] === 'link';
$plan = $c['plan'];
$pay = $c['pay'];
$cur = $c['currency'];
$value = money($c['amount'], 2, $cur);
$planPrice = $plan['price'] !== null ? money((float) $plan['price'], 2, 'BRL') : null;
$differs = $c['store_custom'];
$renewal = $isLink ? ($c['new_renewal'] ?? null) : $c['renewal_date'];
$auto = Payments::isAuto($pay['method'], $cur);
$parts = Payments::schedule($c['amount'], (int) $pay['installments'], $pay['first_due'], $auto);
?>
<div class="page-head lead-head">
    <div class="lead-title">
        <a class="small back-link" href="<?= e(url('/admin/crm/' . $id)) ?>">← Cancelar e voltar para a ficha</a>
        <h2>Confirmar conversão</h2>
        <div class="lead-tags"><strong><?= e($lead['name']) ?></strong><span class="small muted">Passo 2 de 2: nada foi gravado ainda</span></div>
    </div>
</div>

<?php if ($problem): ?><div class="flash flash-err" role="alert" style="margin-bottom:12px"><span><?= e($problem) ?></span></div><?php endif; ?>

<form class="panel" method="post" action="<?= e(url('/admin/crm/' . $id . '/converter')) ?>" style="max-width:820px">
    <?= csrf_field() ?>
    <?php foreach ($keep as $k => $val): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($val) ?>"><?php endforeach; ?>
    <input type="hidden" name="confirm" value="1">

    <h3 style="margin-top:0">Confira antes de converter</h3>
    <dl class="dl conv-dl">
        <dt>Paciente</dt>
        <dd><?php if ($isLink): ?><strong><?= e($c['patient']['name']) ?></strong> <span class="muted">· <?= e($c['patient']['email']) ?></span><br><span class="small muted">Paciente que já existe: nenhum cadastro novo é criado.</span>
            <?php else: ?><strong><?= e($c['name']) ?></strong> <span class="muted">· <?= e($c['email']) ?></span><?= $c['phone'] ? '<span class="muted"> · ' . e($c['phone']) . '</span>' : '' ?><br><span class="small muted">Novo paciente, com acesso ao app (senha provisória gerada ao confirmar).</span><?php endif; ?></dd>
        <dt>Plano contratado</dt>
        <dd><strong><?= e($plan['name']) ?></strong> <span class="muted">· <?= (int) $plan['duration_days'] ?> dias</span></dd>
        <dt>Vencimento</dt>
        <dd><?= $renewal ? e(fmt_date($renewal)) : '<span class="muted">sem data</span>' ?><?php if ($isLink): ?> <span class="small muted">(soma <?= (int) $plan['duration_days'] ?> dias ao vencimento atual)</span><?php endif; ?></dd>
        <dt>Valor contratado</dt>
        <dd><strong><?= e($value) ?></strong><?php if ($differs): ?> <span class="tag">valor combinado diferente do plano<?= $planPrice ? ' (' . e($planPrice) . ')' : '' ?></span><?php elseif ($planPrice): ?> <span class="small muted">valor do plano</span><?php endif; ?></dd>
        <dt>Pagamento</dt>
        <dd><?= e(LeadConversion::conditionText($pay)) ?><?php if ($pay['installments'] > 1): ?> <span class="muted">· parcelas de <?= e(money((float) $parts[0]['amount'], 2, $cur)) ?></span><?php endif; ?>
            <br><span class="small muted">1ª parcela em <?= e(fmt_date($parts[0]['due_on'])) ?><?php if ($auto): ?> · cartão: cai sozinha no vencimento<?php elseif ($pay['paid_count'] > 0): ?> · já recebida<?php else: ?> · a receber<?php endif; ?></span></dd>
        <dt>Responsável</dt>
        <dd><?= $respName !== '' ? e($respName) : '<span class="muted">sem responsável</span>' ?></dd>
        <dt>Origem do lead</dt>
        <dd><?= $lead['source_name'] ? e($lead['source_name']) : '<span class="muted">-</span>' ?></dd>
    </dl>

    <div class="conv-does">
        <p><strong>Ao confirmar:</strong></p>
        <ul>
            <li><?= $isLink ? 'O plano de ' . e(first_name($c['patient']['name'])) . ' passa a ser ' . e($plan['name']) . ' e o vencimento é renovado.' : 'O paciente é criado em Pacientes, com o plano ' . e($plan['name']) . '.' ?></li>
            <li>A venda de <?= e($value) ?> entra em Financeiro<?= $pay['installments'] > 1 ? ', com ' . (int) $pay['installments'] . ' parcelas' : '' ?>.</li>
            <li>O lead vira "Convertido", sai das oportunidades abertas e a conversão fica na linha do tempo e nos indicadores do CRM.</li>
        </ul>
    </div>

    <label class="checkbox mb conv-confirm"><input type="checkbox" name="confirm_plan" value="1" required><span>Confirmo que o plano contratado é <strong><?= e($plan['name']) ?></strong>, no valor de <strong><?= e($value) ?></strong>.</span></label>
    <div class="form-actions">
        <button class="btn" type="submit">Confirmar conversão</button>
        <button class="btn btn-ghost" type="submit" name="back" value="1" formnovalidate>Voltar e corrigir</button>
    </div>
</form>
