<?php
// Valor em R$ em destaque; dólar/euro numa linha à parte (nunca somados ao real)
$brl = fn(array $by) => (float) ($by['BRL'] ?? 0);
$foreign = fn(array $by) => array_diff_key($by, ['BRL' => 1]);
$fmt0 = fn(float $v) => money($v, floor($v) == $v ? 0 : 2);
$sold = $summary['sold'];
$pending = $summary['pending'];
$base = '/admin/financeiro';
$filters = ['month' => 'Vencem no mês', 'pending' => 'A receber no mês', 'received' => 'Recebidas no mês', 'late' => 'Atrasadas'];
$back = $base . '?m=' . $ym . '&f=' . $filter;
$maxBrl = 0.0;
$totals = [];
$anyForeign = false;
foreach ($receivables as $rv) {
    $maxBrl = max($maxBrl, $brl($rv['by']));
    foreach ($rv['by'] as $c => $v) {
        $totals[$c] = ($totals[$c] ?? 0) + $v;
        if ($c !== 'BRL') { $anyForeign = true; }
    }
}
$countAll = array_sum(array_column($receivables, 'count'));
?>
<div class="page-head">
    <h2>Financeiro</h2>
    <div class="month-nav">
        <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '?m=' . $prevYm . '&f=' . $filter)) ?>" aria-label="Mês anterior">‹</a>
        <strong><?= e($monthLabel) ?></strong>
        <a class="btn btn-ghost btn-sm" href="<?= e(url($base . '?m=' . $nextYm . '&f=' . $filter)) ?>" aria-label="Próximo mês">›</a>
        <?php if (!$isCurrent): ?><a class="btn btn-ghost btn-sm" href="<?= e(url($base . '?f=' . $filter)) ?>">Hoje</a><?php endif; ?>
    </div>
</div>
<p class="muted small">Faturamento é o que foi vendido no mês. Os recebíveis são as parcelas: cartão e Pix caem mês a mês, e você confere o que caiu clicando em "Recebi". Valores brutos (sem taxas). Só o admin vê.</p>

<div class="kpis fin">
    <div class="kpi">
        <span class="big num"><?= e($fmt0($brl($sold))) ?></span>
        <span class="lbl">faturamento do mês<?php if ($foreign($sold)): ?><br>+ <?= e(money_multi($foreign($sold))) ?><?php endif; ?></span>
    </div>
    <div class="kpi">
        <span class="big num"><?= e($fmt0($summary['received'])) ?></span>
        <span class="lbl">recebido no mês (R$)<?= $summary['receivedMissing'] > 0 ? '<br>' . (int) $summary['receivedMissing'] . ' sem o valor em R$' : '' ?></span>
    </div>
    <a class="kpi" href="<?= e(url($base . '?m=' . $ym . '&f=pending')) ?>">
        <span class="big num"><?= e($fmt0($brl($pending))) ?></span>
        <span class="lbl">a receber no mês<?php if ($foreign($pending)): ?><br>+ <?= e(money_multi($foreign($pending))) ?><?php endif; ?></span>
    </a>
    <a class="kpi<?= $late['count'] > 0 ? ' alert' : '' ?>" href="<?= e(url($base . '?m=' . $ym . '&f=late')) ?>">
        <span class="big num"><?= (int) $late['count'] ?></span>
        <span class="lbl">parcelas atrasadas<?= $late['count'] > 0 ? '<br>' . e(money_multi($late['by'])) : '' ?></span>
    </a>
</div>

<div class="chips">
    <?php foreach ($filters as $k => $label): ?>
        <a class="chip" href="<?= e(url($base . '?m=' . $ym . '&f=' . $k)) ?>"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
    <div class="empty"><strong><?= $filter === 'late' ? 'Nenhuma parcela atrasada' : 'Nenhuma parcela aqui' ?></strong><?= $filter === 'late' ? 'Tudo que venceu até hoje já caiu.' : 'Ao renovar um paciente com valor (ou em Registrar pagamento, na ficha), as parcelas aparecem aqui.' ?></div>
<?php else: ?>
<div class="tbl-wrap">
    <table class="tbl">
        <thead><tr><th><?= $filter === 'received' ? 'Recebida em' : 'Vence' ?></th><th>Paciente</th><th>Forma</th><th>Parcela</th><th class="r">Valor</th><th>Situação</th><th class="r">Ação</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="num"><?= e(fmt_date($filter === 'received' ? $r['received_on'] : $r['due_on'])) ?></td>
                <td><a class="who" href="<?= e(url('/admin/pacientes/' . (int) $r['user_id'])) ?>"><?= e($r['patient_name']) ?></a><?= !empty($r['plan_name']) ? '<br><span class="small muted">' . e($r['plan_name']) . '</span>' : '' ?></td>
                <td><?= e(\App\Services\Payments::methodLabel($r['method'])) ?></td>
                <td class="num"><?= (int) $r['number'] ?>/<?= (int) $r['total_n'] ?></td>
                <td class="r num"><?= e(money((float) $r['amount'], 2, $r['currency'])) ?></td>
                <td>
                    <?php if ($r['status'] === 'received'): ?>
                        <span class="tag ok">Recebida</span>
                        <?php if ($r['currency'] !== 'BRL'): ?><br><span class="small muted"><?= $r['received_brl'] !== null ? 'Caiu ' . e(money((float) $r['received_brl'])) : 'falta informar o R$' ?></span><?php endif; ?>
                    <?php elseif ($r['late']): ?><span class="tag bad">Atrasada</span>
                    <?php else: ?><span class="tag">A receber</span><?php endif; ?>
                </td>
                <td class="r"><?php partial('installment-actions', ['i' => $r, 'back' => $back]); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<h3 class="sub" style="margin-top:32px">Recebíveis: próximos 12 meses</h3>
<p class="muted small">O que ainda vai cair, mês a mês (só parcelas pendentes). Em dólar/euro fica na moeda original, sem converter.</p>
<?php if ($countAll === 0): ?>
    <div class="empty"><strong>Nada a receber</strong>Quando houver parcelas pendentes, a agenda aparece aqui.</div>
<?php else: ?>
<div class="tbl-wrap">
    <table class="tbl" style="min-width:0">
        <thead><tr><th>Mês</th><th class="r">Parc.</th><th class="r">R$</th><?php if ($anyForeign): ?><th class="r">Exterior</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($receivables as $key => $rv): $isLateRow = $key === 'late'; $empty = $rv['count'] === 0; ?>
            <tr<?= $empty ? ' class="muted"' : '' ?>>
                <td><?= $isLateRow ? '<span class="tag bad">' . e($rv['label']) . '</span>' : e($rv['label']) . ($key === date('Y-m') ? ' <span class="tag">mês atual</span>' : '') ?></td>
                <td class="r num"><?= (int) $rv['count'] ?></td>
                <td class="r num">
                    <?= $brl($rv['by']) > 0 ? e(money($brl($rv['by']))) : '<span class="muted">-</span>' ?>
                    <?php if ($maxBrl > 0 && $brl($rv['by']) > 0): ?><span class="bar"><span style="width:<?= (int) round($brl($rv['by']) / $maxBrl * 100) ?>%"></span></span><?php endif; ?>
                </td>
                <?php if ($anyForeign): ?>
                    <td class="r num"><?php
                        $fx = array_diff_key($rv['by'], ['BRL' => 1]);
                        if ($fx) { echo implode('<br>', array_map(fn($c, $v) => e(money((float) $v, 2, $c)), array_keys($fx), $fx)); } else { echo '<span class="muted">-</span>'; }
                    ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><th>Total</th><th class="r num"><?= (int) $countAll ?></th><th class="r num"><?= e(money((float) ($totals['BRL'] ?? 0))) ?></th><?php if ($anyForeign): ?><th class="r num"><?php
                $fxT = array_diff_key($totals, ['BRL' => 1]);
                echo $fxT ? implode('<br>', array_map(fn($c, $v) => e(money((float) $v, 2, $c)), array_keys($fxT), $fxT)) : '-';
            ?></th><?php endif; ?></tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
