<?php
use App\Services\Leads;

$id = (int) $lead['id'];
$isLink = $mode === 'link';
$v = fn(string $k, string $d = '') => old($k, $d);
$planSel = (string) old('plan_id', (string) ($plan['id'] ?? ''));
$amountDefault = $plan && $plan['price'] !== null ? number_format((float) $plan['price'], 2, ',', '') : '';
$payMethod = (string) old('method', '');
$otherLeads = array_merge($dups['email'] ? [$dups['email']] : [], $dups['phone'], $dups['instagram']);
$seen = [];
$otherLeads = array_values(array_filter($otherLeads, function ($d) use (&$seen) { if (isset($seen[$d['id']])) { return false; } $seen[$d['id']] = 1; return true; }));
?>
<div class="page-head lead-head">
    <div class="lead-title">
        <a class="small back-link" href="<?= e(url('/admin/crm/' . $id)) ?>">← Voltar para a ficha do lead</a>
        <h2>Converter em paciente</h2>
        <div class="lead-tags">
            <strong><?= e($lead['name']) ?></strong>
            <span class="plan-pill"><?= e($lead['stage_name']) ?></span>
            <?php if ($lead['status'] === 'lost'): ?><span class="st st-off">Lead perdido</span><?php endif; ?>
            <?php if ($lead['source_name']): ?><span class="small muted">Origem: <?= e($lead['source_name']) ?></span><?php endif; ?>
        </div>
    </div>
</div>

<?php if ($mode === ''): ?>
<div class="panel dup-panel" role="alert">
    <h3>Já existe um paciente com este contato</h3>
    <p style="margin-top:0">Pelo e-mail, telefone ou Instagram, este lead parece ser <a href="<?= e(url(\App\Controllers\Admin\PatientController::ficha((int) $dups['patient']['id']))) ?>"><?= e($dups['patient']['name']) ?></a>, que já é paciente.</p>
    <p class="muted small">Se for a mesma pessoa, vincule: o plano dele é renovado e a venda entra no Financeiro, sem criar um segundo cadastro. Se for outra pessoa, crie um paciente novo.</p>
    <div class="form-actions">
        <a class="btn" href="<?= e(url('/admin/crm/' . $id . '/converter?modo=vincular&paciente=' . (int) $dups['patient']['id'])) ?>">Vincular a <?= e(first_name($dups['patient']['name'])) ?> (paciente que já existe)</a>
        <a class="btn btn-ghost" href="<?= e(url('/admin/crm/' . $id . '/converter?modo=novo&outra=1')) ?>">É outra pessoa: criar paciente novo</a>
    </div>
</div>
<?php return; endif; ?>

<?php if ($otherLeads): ?>
<div class="panel dup-panel" style="border-color:var(--line-strong)" role="status">
    <strong>Atenção:</strong> há outro lead com o mesmo contato:
    <?php foreach ($otherLeads as $i => $d): ?><?= $i ? ', ' : ' ' ?><a href="<?= e(url('/admin/crm/' . (int) $d['id'])) ?>"><?= e($d['name']) ?></a><?php endforeach; ?>.
    Confira se não é a mesma pessoa cadastrada duas vezes.
</div>
<?php endif; ?>

<form class="panel" method="post" action="<?= e(url('/admin/crm/' . $id . '/converter')) ?>" style="max-width:820px" data-pay-form data-fees="<?= e(json_encode($fees ?: new stdClass())) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="modo" value="<?= $isLink ? 'vincular' : 'novo' ?>">
    <?php if (!$isLink && $outra): ?><input type="hidden" name="ok_dup" value="1"><?php endif; ?>
    <div class="form-grid">
        <?php if ($isLink): ?>
            <h3 class="sub full" style="margin:0 0 12px">Paciente que já existe</h3>
            <div class="field full"><label for="patient_id">Vincular a</label>
                <?php $pSel = (string) old('patient_id', (string) $pickPatient); ?>
                <select id="patient_id" name="patient_id" required>
                    <option value="">Escolha o paciente…</option>
                    <?php foreach ($patients as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $pSel === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?> · <?= e($p['email']) ?><?= $p['status'] === 'inactive' ? ' (acesso desativado)' : '' ?></option><?php endforeach; ?>
                </select>
                <p class="hint">O plano abaixo passa a ser o plano dele, o vencimento soma os dias do plano e a venda entra no Financeiro dele. <a href="<?= e(url('/admin/crm/' . $id . '/converter?modo=novo' . ($dups['patient'] ? '&outra=1' : ''))) ?>">Criar um paciente novo em vez disso</a>.</p></div>
        <?php else: ?>
            <h3 class="sub full" style="margin:0 0 12px">Dados do paciente</h3>
            <div class="field"><label for="name">Nome completo</label><input class="input" id="name" name="name" maxlength="120" value="<?= e($v('name', (string) $lead['name'])) ?>" required></div>
            <div class="field"><label for="email">E-mail (é o login)</label><input class="input" type="email" id="email" name="email" maxlength="160" value="<?= e($v('email', (string) $lead['email'])) ?>" required>
                <?php if (!$lead['email']): ?><p class="hint">O lead não tinha e-mail. Peça ao paciente: ele entra no app com este e-mail.</p><?php endif; ?></div>
            <div class="field"><label for="phone">WhatsApp</label><input class="input" id="phone" name="phone" inputmode="tel" maxlength="30" value="<?= e($v('phone', (string) $lead['phone'])) ?>" placeholder="(11) 99999-8888"></div>
            <div class="field"><label for="birth_date">Data de nascimento</label><input class="input" type="date" id="birth_date" name="birth_date" min="1900-01-01" max="<?= e(today()) ?>" value="<?= e($v('birth_date')) ?>">
                <p class="hint">Opcional. Serve para o aviso de aniversariantes.</p></div>
            <div class="field full"><label for="goal">Objetivo</label><input class="input" id="goal" name="goal" maxlength="120" value="<?= e($v('goal', (string) $lead['goal'])) ?>" placeholder="Ex.: Emagrecimento"></div>
            <p class="muted small full" style="margin:0">O Instagram do lead<?= $lead['instagram'] ? ' (@' . e($lead['instagram']) . ')' : '' ?> vai junto. CPF, endereço e o resto dos dados pessoais você completa depois, na ficha do paciente.
                <a href="<?= e(url('/admin/crm/' . $id . '/converter?modo=vincular')) ?>">Este lead já é paciente? Vincular a um paciente que já existe</a>.</p>
        <?php endif; ?>

        <h3 class="sub full" style="margin:12px 0 12px">Plano contratado</h3>
        <div class="field"><label for="plan_id">Plano</label>
            <select id="plan_id" name="plan_id" required data-plan-select<?= $isLink ? '' : ' data-date-target="#renewal_date"' ?> data-price-fill="#c_amount">
                <option value="">Escolha o plano contratado…</option>
                <?php foreach ($plans as $pl): ?>
                    <option value="<?= (int) $pl['id'] ?>" data-days="<?= (int) $pl['duration_days'] ?>" data-price="<?= $pl['price'] !== null ? e(number_format((float) $pl['price'], 2, ',', '')) : '' ?>"<?= $planSel === (string) $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?> · <?= (int) $pl['duration_days'] ?> dias</option>
                <?php endforeach; ?>
            </select>
            <p class="hint"><?= $lead['plan_name'] ? 'Plano de interesse do lead: ' . e($lead['plan_name']) . '. Confirme o que ele realmente contratou.' : 'O lead não tinha plano de interesse: escolha o que ele contratou.' ?></p></div>
        <?php if (!$isLink): ?>
            <div class="field"><label for="renewal_date">Vence em</label><input class="input" type="date" id="renewal_date" name="renewal_date" value="<?= e($v('renewal_date', $defaultRenewal)) ?>">
                <p class="hint">Preenchido pelo plano (você pode mudar).</p></div>
            <div class="field"><label for="joined_on">Paciente desde</label><input class="input" type="date" id="joined_on" name="joined_on" max="<?= e(today()) ?>" value="<?= e($v('joined_on', today())) ?>"></div>
        <?php endif; ?>
        <div class="field"><label for="responsible_id">Responsável pelo paciente</label>
            <?php $rSel = (string) old('responsible_id', (string) ($lead['responsible_id'] ?? '')); ?>
            <select id="responsible_id" name="responsible_id">
                <option value="">Sem responsável</option>
                <?php foreach ($respChoices as $rc): if (!$rc['active'] && (string) $rc['id'] !== $rSel) { continue; } ?>
                    <option value="<?= (int) $rc['id'] ?>"<?= $rSel === (string) $rc['id'] ? ' selected' : '' ?>><?= e($rc['name']) ?><?= $rc['active'] ? '' : ' (desativada)' ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Quem da equipe cuida deste paciente. Vem do lead.</p></div>

        <h3 class="sub full" style="margin:12px 0 4px">Pagamento</h3>
        <p class="muted small full" style="margin:0 0 12px">Converter lança a venda em Financeiro. O valor vem do plano; mude se o combinado foi outro. Só o admin vê.</p>
        <?php
        partial('payment-fields', [
            'idp' => 'c_', 'amountName' => 'plan_price', 'amountId' => 'c_amount', 'amountLabel' => 'Valor contratado',
            'amountValue' => old('plan_price', $amountDefault), 'amountPlaceholder' => 'Igual ao plano',
            'currencyName' => 'plan_currency', 'currency' => old('plan_currency', 'BRL'),
            'method' => $payMethod, 'allowNone' => true, 'hideNone' => true,
            'installments' => (int) old('installments', '1'), 'due' => old('first_due', today()),
            'paidMode' => 'first', 'paidChecked' => !empty(old('first_paid')),
            'brand' => old('card_brand'), 'fee' => old('fee_pct'),
        ]);
        ?>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Revisar conversão</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/crm/' . $id)) ?>">Cancelar</a>
    </div>
    <p class="hint" style="margin-bottom:0">Nada é gravado agora: o próximo passo mostra um resumo para você confirmar o plano contratado.</p>
</form>
