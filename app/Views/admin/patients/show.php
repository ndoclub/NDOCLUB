<?php
$id = (int) $p['id'];
$athleteOn = $athlete && (int) $athlete['is_athlete'] === 1;
$athleteNames = [];
if ($athleteOn) {
    foreach ($sportChoices as $sc) {
        if (in_array((int) $sc['id'], $athleteSportIds, true)) { $athleteNames[] = $sc['name']; }
    }
}
$daysToEvent = ($athleteOn && $athlete['competes'] && $athlete['event_date']) ? days_until($athlete['event_date']) : null;
$waWelcome = $welcome ? wa_phone_url($p['phone'], $welcome) : null;
$weekdays = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
?>
<div class="page-head">
    <div class="row">
        <?= avatar($p, 56) ?>
        <div>
            <h2 style="font-size:22px"><?= e($p['name']) ?></h2>
            <div class="row wrap" style="gap:8px;margin-top:6px">
                <?php if ($p['status'] === 'inactive'): ?><span class="tag">Desativado</span><?php else: partial('seal', ['renew' => $renew]); endif; ?>
                <?php partial('level', ['level' => $level]); ?>
                <?php foreach ($athleteNames as $sn): ?><span class="sport"><?= e($sn) ?></span><?php endforeach; ?>
                <?php if ($planRow): ?><span class="tag"><?= e($planRow['name']) ?><?= $priceInfo['amount'] !== null ? ' · ' . e(money($priceInfo['amount'], 2, $priceInfo['currency'])) . ($priceInfo['source'] === 'manual' ? ' (combinado)' : ($priceInfo['source'] === 'reajuste' ? ' (mantido até renovar)' : '')) : ' · sem valor' ?></span><?php endif; ?>
            </div>
        </div>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/pacientes')) ?>">Voltar à lista</a>
</div>

<?php if ($welcome): ?>
<div class="panel">
    <h3>Mensagem de boas-vindas (aparece só agora)</h3>
    <div class="copy-box" id="welcome"><?= e($welcome) ?></div>
    <div class="form-actions" style="margin-top:0">
        <button class="btn btn-sm" type="button" data-copy="#welcome">Copiar mensagem</button>
        <?php if ($waWelcome): ?><a class="btn btn-ghost btn-sm" href="<?= e($waWelcome) ?>" target="_blank" rel="noopener">Enviar pelo WhatsApp</a><?php endif; ?>
    </div>
    <p class="hint">A senha provisória não fica salva em lugar nenhum e não será mostrada de novo.</p>
</div>
<?php endif; ?>

<div class="kpis">
    <div class="kpi"><span class="big num"><?= (int) $streak ?></span><span class="lbl">dias seguidos</span></div>
    <div class="kpi"><span class="big num"><?= pts($balance) ?></span><span class="lbl">saldo de pontos</span></div>
    <div class="kpi"><span class="big num"><?= pts($monthPts) ?></span><span class="lbl">pontos no mês</span></div>
    <div class="kpi"><span class="big num"><?= $rank && $rank['pts'] > 0 ? '#' . (int) $rank['pos'] : '-' ?></span><span class="lbl">ranking do mês</span></div>
    <div class="kpi"><span class="big num"><?= (int) $workoutsMonth ?></span><span class="lbl">treinos no mês</span></div>
    <div class="kpi"><span class="big num" style="font-size:22px"><?= $lastCheckin ? e(fmt_date($lastCheckin)) : '-' ?></span><span class="lbl">último check-in</span></div>
</div>

<div class="cols">
<div>
    <form class="panel" method="post" action="<?= e(url('/admin/pacientes/' . $id)) ?>">
        <?= csrf_field() ?>
        <h3>Dados</h3>
        <div class="form-grid">
            <div class="field"><label for="name">Nome</label><input class="input" id="name" name="name" maxlength="120" value="<?= e($p['name']) ?>" required></div>
            <div class="field"><label for="email">E-mail</label><input class="input" type="email" id="email" name="email" value="<?= e($p['email']) ?>" required></div>
            <div class="field"><label for="phone">WhatsApp</label><input class="input" id="phone" name="phone" inputmode="tel" value="<?= e($p['phone'] ?? '') ?>"></div>
            <div class="field"><label for="goal">Objetivo</label><input class="input" id="goal" name="goal" maxlength="120" value="<?= e($p['goal'] ?? '') ?>"></div>
            <div class="field"><label for="plan_id">Plano</label>
                <select id="plan_id" name="plan_id" data-plan-select data-price-target="#plan_price">
                    <option value="">Sem plano definido</option>
                    <?php foreach ($plans as $pl): ?>
                        <option value="<?= (int) $pl['id'] ?>" data-days="<?= (int) $pl['duration_days'] ?>" data-price="<?= $pl['price'] !== null ? e(number_format((float) $pl['price'], 2, ',', '')) : '' ?>"<?= (int) ($p['plan_id'] ?? 0) === (int) $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?> · <?= (int) $pl['duration_days'] ?> dias<?= !$pl['active'] ? ' (desativado)' : '' ?></option>
                    <?php endforeach; ?>
                </select></div>
            <?php partial('price-field', [
                'value' => $customPrice !== null ? number_format($customPrice, 2, ',', '') : '',
                'currency' => $priceInfo['source'] === 'plan' ? 'BRL' : $priceInfo['currency'],
                'placeholder' => $planRow && $planRow['price'] !== null ? number_format((float) $planRow['price'], 2, ',', '') : 'Igual ao do plano',
                'hint' => $priceInfo['source'] === 'reajuste'
                    ? 'Mantendo o valor antigo até renovar; depois passa para ' . ($priceInfo['planPrice'] !== null ? money($priceInfo['planPrice']) : 'o valor do plano') . '. Só o admin vê.'
                    : 'Em branco = valor do plano' . ($planRow && $planRow['price'] !== null ? ' (' . money((float) $planRow['price']) . ')' : '') . '. Só se for diferente (ou em dólar/euro). Só o admin vê.',
            ]); ?>
            <div class="field"><label for="renewal_date">Vence em</label><input class="input" type="date" id="renewal_date" name="renewal_date" value="<?= e(substr((string) $p['renewal_date'], 0, 10)) ?>"></div>
            <div class="field"><label for="status">Acesso</label>
                <select id="status" name="status">
                    <option value="active"<?= $p['status'] === 'active' ? ' selected' : '' ?>>Ativo</option>
                    <option value="inactive"<?= $p['status'] === 'inactive' ? ' selected' : '' ?>>Desativado (não consegue entrar)</option>
                </select></div>
        </div>
        <div class="form-actions"><button class="btn btn-sm" type="submit">Salvar dados</button></div>
    </form>

    <form class="panel" method="post" action="<?= e(url('/admin/pacientes/' . $id . '/esporte')) ?>">
        <?= csrf_field() ?>
        <h3>Perfil esportivo</h3>
        <?php partial('athlete-fields', ['athlete' => $athlete, 'choices' => $sportChoices, 'selected' => $athleteSportIds]); ?>
        <?php if ($athleteOn): ?>
            <p class="hint">
                <?= $athlete['show_public'] ? 'O paciente mostra as modalidades no perfil da comunidade.' : 'O paciente não mostra as modalidades no perfil da comunidade (escolha dele).' ?>
                <?php if ($daysToEvent !== null && $daysToEvent >= 0): ?> Prova em <?= (int) $daysToEvent ?> <?= $daysToEvent === 1 ? 'dia' : 'dias' ?>.<?php endif; ?>
            </p>
        <?php endif; ?>
        <div class="form-actions"><button class="btn btn-sm" type="submit">Salvar perfil esportivo</button></div>
    </form>

    <div class="panel">
        <h3>Renovar acompanhamento</h3>
        <p class="muted small">Soma os dias a partir do vencimento atual (ou de hoje, se já venceu) e lança o bônus de pontos. Com valor preenchido, também registra o pagamento e as parcelas em Financeiro.</p>
        <?php
        $sugAmount = $suggest['amount'];
        $sugCur = $suggest['currency'];
        $defMethod = array_key_exists($lastMethod, \App\Services\Payments::methods()) ? $lastMethod : 'pix';
        $defDue = $defMethod === 'cartao' ? date('Y-m-d', strtotime('+30 day')) : today();
        $planDays = $planRow ? (int) $planRow['duration_days'] : 90;
        if (!in_array($planDays, $renewChoices, true)) { $planDays = 90; }
        ?>
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/renovar')) ?>" data-confirm="Confirmar a renovação?" data-pay-form>
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field"><label for="days">Período</label>
                    <select id="days" name="days">
                        <?php foreach ($renewChoices as $d): ?>
                            <option value="<?= (int) $d ?>"<?= $d === $planDays ? ' selected' : '' ?>>+<?= (int) $d ?> dias<?= ($planRow && $d === (int) $planRow['duration_days']) ? ' (' . e($planRow['name']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="field"><label for="amount">Valor total vendido</label>
                    <div class="row" style="gap:8px">
                        <select name="currency" aria-label="Moeda" data-pay-currency style="flex:0 0 96px">
                            <?php foreach (currencies() as $code => $c): ?>
                                <option value="<?= e($code) ?>"<?= $sugCur === $code ? ' selected' : '' ?>><?= e($c[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input class="input" id="amount" name="amount" inputmode="decimal" placeholder="Ex.: 1200,00" value="<?= $sugAmount !== null ? e(number_format($sugAmount, 2, ',', '')) : '' ?>">
                    </div></div>
                <div class="field"><label for="method">Forma de pagamento</label>
                    <select id="method" name="method" data-pay-method>
                        <?php foreach (\App\Services\Payments::methods() as $k => $label): ?>
                            <option value="<?= e($k) ?>"<?= $defMethod === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="field"><label for="installments">Parcelas</label>
                    <select id="installments" name="installments">
                        <?php for ($n = 1; $n <= 12; $n++): ?>
                            <option value="<?= $n ?>"><?= $n === 1 ? 'À vista (1x)' : $n . 'x' ?></option>
                        <?php endfor; ?>
                    </select></div>
                <div class="field"><label for="first_due">1ª parcela em</label>
                    <input class="input" type="date" id="first_due" name="first_due" value="<?= e($defDue) ?>" data-pay-due>
                    <p class="hint">As próximas caem todo mês, no mesmo dia. No cartão, a 1ª costuma cair 30 dias depois.</p></div>
                <div class="field align-input"><label class="checkbox"><input type="checkbox" name="first_paid" value="1" data-pay-paid<?= $defMethod === 'cartao' ? '' : ' checked' ?>><span>1ª parcela já recebida</span></label></div>
                <div class="field" data-brl-field<?= $sugCur === 'BRL' ? ' hidden' : '' ?>><label for="received_brl">Quanto caiu em reais (R$)</label>
                    <input class="input" id="received_brl" name="received_brl" inputmode="decimal" placeholder="Ex.: 4950,00">
                    <p class="hint">Em dólar/euro, o total em R$ usa o valor que creditou na conta.</p></div>
            </div>
            <div class="form-actions"><button class="btn btn-sm" type="submit">Renovar</button></div>
        </form>
        <p class="hint">Deixe o valor em branco para só somar os dias, sem registrar pagamento. O paciente nunca vê valores.</p>
        <?php if ($renewals): ?>
            <div class="tbl-wrap"><table class="tbl" style="min-width:0">
                <thead><tr><th>Quando</th><th>De</th><th>Até</th><th class="r">Valor</th></tr></thead>
                <tbody>
                <?php foreach ($renewals as $r): ?>
                    <tr><td><?= e(fmt_date($r['created_at'])) ?></td><td><?= e(fmt_date($r['previous_date'])) ?></td><td><?= e(fmt_date($r['new_date'])) ?> <span class="tag">+<?= (int) $r['days_added'] ?> d</span><?= !empty($r['plan_name']) ? '<br><span class="small muted">' . e($r['plan_name']) . '</span>' : '' ?></td>
                        <td class="r num"><?= $r['amount'] !== null ? e(money((float) $r['amount'], 2, (string) ($r['currency'] ?? 'BRL'))) : '-' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3>Pagamentos</h3>
        <p class="muted small">Cada pagamento tem as parcelas (recebíveis). Quando cair, clique em "Recebi"; se marcou sem querer, "Desfazer". Só o admin vê.</p>
        <?php $backHere = '/admin/pacientes/' . $id; ?>
        <?php if (!$payments): ?>
            <p class="muted small" style="margin:0 0 12px">Nenhum pagamento registrado ainda.</p>
        <?php endif; ?>
        <?php foreach ($payments as $pay): ?>
            <details class="pay"<?= $pay['late_count'] > 0 ? ' open' : '' ?>>
                <summary>
                    <strong class="num"><?= e(money((float) $pay['total'], 2, $pay['currency'])) ?></strong>
                    <span class="muted small"><?= e(\App\Services\Payments::methodLabel($pay['method'])) ?> · <?= (int) $pay['installments'] === 1 ? 'à vista' : (int) $pay['installments'] . 'x' ?> · vendido em <?= e(fmt_date($pay['sold_on'])) ?><?= !empty($pay['plan_name']) ? ' · ' . e($pay['plan_name']) : '' ?></span>
                    <span class="tag<?= $pay['received_count'] === (int) $pay['installments'] ? ' ok' : '' ?>"><?= (int) $pay['received_count'] ?>/<?= (int) $pay['installments'] ?> recebidas</span>
                    <?php if ($pay['late_count'] > 0): ?><span class="tag bad"><?= (int) $pay['late_count'] ?> <?= $pay['late_count'] === 1 ? 'atrasada' : 'atrasadas' ?></span><?php endif; ?>
                </summary>
                <div class="tbl-wrap"><table class="tbl" style="min-width:0">
                    <thead><tr><th>Parcela</th><th>Vence</th><th class="r">Valor</th><th>Situação</th><th class="r">Ação</th></tr></thead>
                    <tbody>
                    <?php foreach ($pay['items'] as $it): ?>
                        <tr>
                            <td class="num"><?= (int) $it['number'] ?>/<?= (int) $pay['installments'] ?></td>
                            <td><?= e(fmt_date($it['due_on'])) ?></td>
                            <td class="r num"><?= e(money((float) $it['amount'], 2, $it['currency'])) ?></td>
                            <td>
                                <?php if ($it['status'] === 'received'): ?>
                                    <span class="tag ok">Recebida <?= e(fmt_date($it['received_on'])) ?></span>
                                    <?php if ($it['currency'] !== 'BRL'): ?><br><span class="small muted"><?= $it['received_brl'] !== null ? 'Caiu ' . e(money((float) $it['received_brl'])) : 'falta informar o R$' ?></span><?php endif; ?>
                                <?php elseif ($it['late']): ?><span class="tag bad">Atrasada</span>
                                <?php else: ?><span class="tag">A receber</span><?php endif; ?>
                            </td>
                            <td class="r"><?php partial('installment-actions', ['i' => $it, 'back' => $backHere]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </details>
        <?php endforeach; ?>

        <details class="pay-add">
            <summary class="btn btn-ghost btn-sm">+ Registrar pagamento sem renovar</summary>
            <p class="muted small" style="margin-top:12px">Para contratos que já existiam antes do sistema (ex.: anual no cartão em 12x que já tem parcelas recebidas).</p>
            <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/pagamentos')) ?>" data-pay-form>
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field"><label for="total">Valor total</label>
                        <div class="row" style="gap:8px">
                            <select name="currency" aria-label="Moeda" data-pay-currency style="flex:0 0 96px">
                                <?php foreach (currencies() as $code => $c): ?><option value="<?= e($code) ?>"><?= e($c[0]) ?></option><?php endforeach; ?>
                            </select>
                            <input class="input" id="total" name="total" inputmode="decimal" placeholder="Ex.: 4500,00" required>
                        </div></div>
                    <div class="field"><label for="p_method">Forma de pagamento</label>
                        <select id="p_method" name="method" data-pay-method>
                            <?php foreach (\App\Services\Payments::methods() as $k => $label): ?><option value="<?= e($k) ?>"<?= $defMethod === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field"><label for="p_installments">Parcelas</label>
                        <select id="p_installments" name="installments">
                            <?php for ($n = 1; $n <= 12; $n++): ?><option value="<?= $n ?>"><?= $n === 1 ? 'À vista (1x)' : $n . 'x' ?></option><?php endfor; ?>
                        </select></div>
                    <div class="field"><label for="sold_on">Data da venda</label><input class="input" type="date" id="sold_on" name="sold_on" value="<?= e(today()) ?>" max="<?= e(today()) ?>"></div>
                    <div class="field"><label for="p_first_due">Vencimento da 1ª parcela</label><input class="input" type="date" id="p_first_due" name="first_due" value="<?= e($defDue) ?>" data-pay-due></div>
                    <div class="field"><label for="paid_count">Parcelas já recebidas</label>
                        <select id="paid_count" name="paid_count" data-pay-paidcount>
                            <?php for ($n = 0; $n <= 12; $n++): ?><option value="<?= $n ?>"><?= $n === 0 ? 'Nenhuma' : $n ?></option><?php endfor; ?>
                        </select>
                        <p class="hint">As primeiras parcelas, na ordem.</p></div>
                    <div class="field" data-brl-field hidden><label for="p_received_brl">Total que caiu em reais (R$)</label>
                        <input class="input" id="p_received_brl" name="received_brl" inputmode="decimal" placeholder="Soma do R$ das parcelas recebidas"></div>
                </div>
                <div class="form-actions"><button class="btn btn-sm" type="submit">Registrar pagamento</button></div>
            </form>
        </details>
    </div>

    <form class="panel" method="post" action="<?= e(url('/admin/pacientes/' . $id . '/dieta')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <h3>Dieta</h3>
        <div class="field"><label for="dtitle">Título</label><input class="input" id="dtitle" name="title" maxlength="120" value="<?= e($diet['title'] ?? 'Plano alimentar') ?>"></div>
        <div class="field"><label for="dlink">Link do WebDiet (ou outro)</label><input class="input" type="url" id="dlink" name="link_url" maxlength="500" value="<?= e($diet['link_url'] ?? '') ?>" placeholder="https://..."></div>
        <div class="field"><label for="dpdf">PDF da dieta</label><input class="input" type="file" id="dpdf" name="pdf" accept="application/pdf">
            <?php if (!empty($diet['pdf_file'])): ?><p class="hint">Já existe um PDF enviado. Enviar outro substitui o atual.</p><?php endif; ?></div>
        <div class="field"><label for="dnotes">Recados para o paciente</label><textarea class="input" id="dnotes" name="notes" style="min-height:80px"><?= e($diet['notes'] ?? '') ?></textarea></div>
        <div class="form-actions">
            <button class="btn btn-sm" type="submit">Salvar dieta</button>
            <?php if (!empty($diet['pdf_file'])): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/media/dieta/' . $diet['pdf_file'])) ?>" target="_blank" rel="noopener">Ver PDF</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if (!empty($diet['pdf_file'])): ?>
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/dieta/pdf-remover')) ?>" data-confirm="Remover o PDF da dieta?" style="margin:-6px 0 18px">
            <?= csrf_field() ?><button class="link-btn danger small" type="submit">Remover PDF</button>
        </form>
    <?php endif; ?>
</div>

<div>
    <div class="panel">
        <h3>Pontos</h3>
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/pontos')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="ptype">O que lançar</label>
                <select id="ptype" name="type">
                    <option value="manual">Ajuste manual (informe os pontos)</option>
                    <option value="referral">Indicação que virou paciente (valor da regra)</option>
                </select>
            </div>
            <div class="form-grid">
                <div class="field"><label for="ppts">Pontos (use - para tirar)</label><input class="input" type="number" id="ppts" name="points" step="1" min="-5000" max="5000" placeholder="Ex.: 50"></div>
                <div class="field"><label for="pnote">Motivo</label><input class="input" id="pnote" name="note" maxlength="200" placeholder="Ex.: Desafio do mês"></div>
            </div>
            <label class="checkbox small mb"><input type="checkbox" name="counts_ranking" value="1"><span>Contar também no ranking do mês (só vale para ajustes positivos)</span></label>
            <button class="btn btn-sm" type="submit">Lançar no extrato</button>
        </form>
        <?php if ($ledger): ?>
        <div class="tbl-wrap mt"><table class="tbl" style="min-width:0">
            <thead><tr><th>Quando</th><th>Motivo</th><th class="r">Pontos</th></tr></thead>
            <tbody>
            <?php foreach ($ledger as $l): ?>
                <tr><td><?= e(fmt_date($l['created_at'])) ?></td><td><?= e($l['label']) ?><?= $l['note'] ? '<br><span class="small muted">' . e($l['note']) . '</span>' : '' ?></td>
                    <td class="r num <?= $l['points'] >= 0 ? 'pos' : 'neg' ?>"><?= $l['points'] >= 0 ? '+' : '−' ?><?= pts(abs($l['points'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="row-between"><h3 style="margin:0">Treinos deste paciente</h3>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/treinos/novo?user_id=' . $id)) ?>">Novo treino</a></div>
        <?php if ($workouts): ?>
            <div class="tbl-wrap mt"><table class="tbl" style="min-width:0"><tbody>
                <?php foreach ($workouts as $w): ?>
                    <tr><td><a href="<?= e(url('/admin/treinos/' . $w['id'])) ?>"><?= e($w['title']) ?></a></td>
                        <td><?= $w['weekday'] ? e($weekdays[(int) $w['weekday']]) : '-' ?></td>
                        <td><?= $w['active'] ? '<span class="tag ok">ativo</span>' : '<span class="tag">inativo</span>' ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><p class="muted small mt" style="margin-bottom:0">Nenhum treino individual. Treinos gerais (para todos) aparecem para ele também.</p><?php endif; ?>
    </div>

    <?php if ($measures): ?>
    <div class="panel">
        <h3>Medidas registradas</h3>
        <div class="tbl-wrap"><table class="tbl" style="min-width:0">
            <thead><tr><th>Data</th><th class="r">Peso</th><th class="r">Cintura</th></tr></thead>
            <tbody>
            <?php foreach ($measures as $m): ?>
                <tr><td><?= e(fmt_date($m['measured_on'])) ?></td><td class="r num"><?= e(number_format((float) $m['weight_kg'], 1, ',', '.')) ?> kg</td>
                    <td class="r num"><?= $m['waist_cm'] !== null ? e(number_format((float) $m['waist_cm'], 1, ',', '.')) . ' cm' : '-' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <div class="panel">
        <h3>Acesso e privacidade</h3>
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/senha')) ?>" data-confirm="Gerar uma nova senha provisória? A senha atual deixa de funcionar.">
            <?= csrf_field() ?>
            <p class="muted small">Esqueceu a senha? Gere uma provisória; o paciente escolhe uma nova ao entrar.</p>
            <button class="btn btn-ghost btn-sm" type="submit">Gerar nova senha provisória</button>
        </form>
        <hr style="border:0;border-top:1px solid var(--line);margin:20px 0">
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/excluir')) ?>" data-confirm="Excluir DEFINITIVAMENTE este paciente e todos os dados dele? Não dá para desfazer.">
            <?= csrf_field() ?>
            <p class="muted small">Exclusão definitiva (pedido de exclusão pela LGPD): apaga cadastro, pontos, publicações, medidas, dieta e fotos.</p>
            <label class="checkbox small mb"><input type="checkbox" name="confirm" value="1" required><span>Entendo que não dá para desfazer.</span></label>
            <button class="btn btn-ghost btn-danger btn-sm" type="submit">Excluir paciente e dados</button>
        </form>
        <p class="hint">Consentimento: <?= $p['consent_at'] ? 'aceito em ' . e(fmt_datetime($p['consent_at'])) . ' (versão ' . e($p['consent_version']) . ')' : 'ainda não aceito' ?>.</p>
    </div>
</div>
</div>
