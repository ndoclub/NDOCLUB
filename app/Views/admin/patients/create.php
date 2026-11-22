<div class="page-head"><h2>Novo paciente</h2></div>
<form class="panel" method="post" action="<?= e(url('/admin/pacientes')) ?>" style="max-width:720px" data-pay-form data-fees="<?= e(json_encode($fees ?: new stdClass())) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
        <h3 class="sub full" style="margin:0 0 12px">Dados do paciente</h3>
        <div class="field"><label for="name">Nome completo</label><input class="input" id="name" name="name" maxlength="120" value="<?= e(old('name')) ?>" required></div>
        <div class="field"><label for="email">E-mail (é o login)</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required></div>
        <div class="field"><label for="phone">WhatsApp</label><input class="input" id="phone" name="phone" inputmode="tel" value="<?= e(old('phone')) ?>" placeholder="(11) 99999-8888"></div>
        <div class="field"><label for="birth_date">Data de nascimento</label><input class="input" type="date" id="birth_date" name="birth_date" min="1900-01-01" max="<?= e(today()) ?>" value="<?= e(old('birth_date')) ?>">
            <p class="hint">Opcional. Serve para o aviso de aniversariantes no painel. Só você vê.</p></div>
        <div class="field"><label for="goal">Objetivo</label><input class="input" id="goal" name="goal" maxlength="120" value="<?= e(old('goal')) ?>" placeholder="Ex.: Emagrecimento"></div>
        <?php
        $pdOld = [];
        foreach (\App\Services\PersonalData::COLUMNS as $pdk) {
            $pdOld[$pdk] = (string) old($pdk, '');
        }
        partial('personal-fields', ['v' => $pdOld]);
        ?>
        <div class="field full"><label class="checkbox"><input type="checkbox" name="msg_optin" value="1"<?= !empty(old('msg_optin')) ? ' checked' : '' ?>><span>Autorizou receber mensagens (aniversário e novidades)</span></label>
            <p class="hint">Marque só se o paciente disse que aceita. Fica registrado o dia em que você marcou.</p></div>
        <h3 class="sub full" style="margin:12px 0 12px">Plano e acompanhamento</h3>
        <div class="field"><label for="plan_id">Plano</label>
            <select id="plan_id" name="plan_id" data-plan-select data-date-target="#renewal_date" data-price-fill="#c_amount">
                <option value="">Sem plano definido</option>
                <?php foreach ($plans as $pl): ?>
                    <option value="<?= (int) $pl['id'] ?>" data-days="<?= (int) $pl['duration_days'] ?>" data-price="<?= $pl['price'] !== null ? e(number_format((float) $pl['price'], 2, ',', '')) : '' ?>"<?= (string) old('plan_id') === (string) $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?> · <?= (int) $pl['duration_days'] ?> dias</option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Ao escolher, a data de vencimento é preenchida sozinha (você pode mudar).</p></div>
        <div class="field"><label for="renewal_date">Vence em</label><input class="input" type="date" id="renewal_date" name="renewal_date" value="<?= e(old('renewal_date', $defaultRenewal)) ?>"></div>
        <div class="field"><label for="joined_on">Paciente desde</label><input class="input" type="date" id="joined_on" name="joined_on" max="<?= e(today()) ?>" value="<?= e(old('joined_on', today())) ?>">
            <p class="hint">Dia em que começou o acompanhamento. O painel usa para contar novos pacientes. Se ele já é antigo, coloque a data real.</p></div>
        <div class="field"><label for="responsible_id">Responsável</label>
            <select id="responsible_id" name="responsible_id">
                <option value="">Sem responsável</option>
                <?php foreach ($respChoices as $rc): if (!$rc['active']) { continue; } ?>
                    <option value="<?= (int) $rc['id'] ?>"<?= (string) old('responsible_id') === (string) $rc['id'] ? ' selected' : '' ?>><?= e($rc['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Quem da equipe cuida deste paciente (modo babá). Dá para mudar depois.</p></div>
        <h3 class="sub full" style="margin:12px 0 4px">Pagamento</h3>
        <p class="muted small full" style="margin:0 0 12px">Já registra a venda em Financeiro. Para o valor, o sistema usa o do plano; mude se este paciente combinou outro valor. Só o admin vê.</p>
        <?php
        $payMethod = (string) old('method', '');
        partial('payment-fields', [
            'idp' => 'c_', 'amountName' => 'plan_price', 'amountId' => 'c_amount', 'amountLabel' => 'Valor total vendido',
            'amountValue' => old('plan_price'), 'amountPlaceholder' => 'Igual ao plano',
            'currencyName' => 'plan_currency', 'currency' => old('plan_currency', 'BRL'),
            'method' => $payMethod, 'allowNone' => true,
            'installments' => (int) old('installments', 1), 'due' => old('first_due', today()),
            'paidMode' => 'first', 'paidChecked' => !empty(old('first_paid')),
            'brand' => old('card_brand'), 'fee' => old('fee_pct'),
        ]);
        ?>
        <h3 class="sub full" style="margin:12px 0 12px">Acesso ao app</h3>
        <div class="field"><label for="password">Senha provisória</label><input class="input" id="password" name="password" value="<?= e($password) ?>" minlength="8" required>
            <p class="hint">O paciente troca no primeiro acesso.</p></div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Criar paciente</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/pacientes')) ?>">Cancelar</a>
    </div>
</form>
