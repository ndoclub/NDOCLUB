<?php
$base = '/admin/financeiro';
$sold = $summary['sold'];
$pending = $summary['pending'];
$filters = ['month' => 'Todas do mês', 'pending' => 'Falta cair', 'received' => 'Já caiu'];
if ($filter === 'late') { $filters['late'] = 'Atrasadas'; }
$back = $base . '?m=' . $ym . '&f=' . $filter;
$brl = fn(array $by) => (float) ($by['BRL'] ?? 0);

/** Número grande do cartão: primeiro valor > 0 (R$ primeiro); os demais vão numa linha embaixo. Zero vira um traço. */
$big = function (array $by): array {
    $items = [];
    foreach (array_keys(currencies()) as $c) {
        if (!empty($by[$c])) { $items[$c] = (float) $by[$c]; }
    }
    if (!$items) { return ['—', '']; }
    $c = array_key_first($items);
    $v = $items[$c];
    unset($items[$c]);
    return [money($v, floor($v) == $v ? 0 : 2, $c), $items ? '+ ' . money_multi($items) : ''];
};
[$soldBig, $soldExtra] = $big($sold);
[$pendBig, $pendExtra] = $big($pending);
$recvBig = $summary['received'] > 0 ? money($summary['received'], floor($summary['received']) == $summary['received'] ? 0 : 2) : '—';
$plural = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);

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
$lateCount = 0;
foreach ($attention as $a) { if ($a['late']) { $lateCount++; } }
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
<p class="muted small"><strong>Vendido</strong> é o que foi vendido no mês (valor cheio). <strong>Já caiu</strong> e <strong>Falta cair</strong> são o dinheiro que entra na conta, em valor líquido (depois da taxa do cartão). Cartão cai sozinho no vencimento; Pix você confere. Só o admin vê. <a href="<?= e(url($base . '/taxas')) ?>">Taxas do cartão</a></p>

<div class="kpis fin">
    <div class="kpi">
        <span class="big num"><?= e($soldBig) ?></span>
        <span class="lbl">vendido no mês<?= $soldExtra !== '' ? '<br>' . e($soldExtra) : '' ?><br><span class="muted"><?= $summary['soldCount'] > 0 ? e($plural($summary['soldCount'], 'venda', 'vendas')) : 'nenhuma venda ainda' ?></span></span>
    </div>
    <a class="kpi" href="<?= e(url($base . '?m=' . $ym . '&f=received')) ?>">
        <span class="big num"><?= e($recvBig) ?></span>
        <span class="lbl">já caiu no mês<br><span class="muted"><?= $summary['receivedCount'] > 0 ? e($plural($summary['receivedCount'], 'parcela', 'parcelas')) . ' · líquido' : 'nada caiu ainda' ?><?= $summary['receivedMissing'] > 0 ? ' · ' . (int) $summary['receivedMissing'] . ' sem o R$' : '' ?></span></span>
    </a>
    <a class="kpi" href="<?= e(url($base . '?m=' . $ym . '&f=pending')) ?>">
        <span class="big num"><?= e($pendBig) ?></span>
        <span class="lbl">falta cair no mês<?= $pendExtra !== '' ? '<br>' . e($pendExtra) : '' ?><br><span class="muted"><?= $summary['pendingCount'] > 0 ? e($plural($summary['pendingCount'], 'parcela', 'parcelas')) : 'nada a receber' ?></span></span>
    </a>
</div>

<h3 class="sub fin-sub">Pix para cobrar e conferir
    <?php if ($attention): ?><span class="tag<?= $lateCount > 0 ? ' bad' : ' warn' ?>"><?= count($attention) ?><?= $lateCount > 0 ? ' · ' . (int) $lateCount . ($lateCount === 1 ? ' atrasada' : ' atrasadas') : ' hoje' ?></span><?php endif; ?></h3>
<?php if (!$attention): ?>
    <div class="fin-ok"><?= icon('check', 18) ?> Tudo em dia: nenhum Pix para cobrar ou conferir hoje.</div>
<?php else: ?>
    <p class="muted small">Parcelas que vencem hoje ou já venceram (Pix, Wise, dinheiro...). Cobre pelo WhatsApp e, quando cair, clique em Recebi (pode anexar o comprovante). Cartão não aparece aqui: cai sozinho.</p>
    <div class="fin-list">
        <?php foreach ($attention as $r): partial('installment-row', ['r' => $r, 'back' => $back]); endforeach; ?>
    </div>
<?php endif; ?>

<h3 class="sub fin-sub">Parcelas do mês</h3>
<div class="chips">
    <?php foreach ($filters as $k => $label): ?>
        <a class="chip" href="<?= e(url($base . '?m=' . $ym . '&f=' . $k)) ?>"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
<?php if (!$rows): ?>
    <div class="empty"><strong><?= $filter === 'late' ? 'Nenhuma parcela atrasada' : 'Nenhuma parcela aqui' ?></strong><?= $filter === 'late' ? 'Tudo que venceu até hoje já caiu.' : 'Ao cadastrar um paciente com valor, renovar, ou em Registrar pagamento (na ficha), as parcelas aparecem aqui.' ?></div>
<?php else: ?>
    <div class="fin-list">
        <?php foreach ($rows as $r): partial('installment-row', ['r' => $r, 'back' => $back]); endforeach; ?>
    </div>
<?php endif; ?>

<h3 class="sub" style="margin-top:32px">Recebíveis: próximos 12 meses</h3>
<p class="muted small">O que ainda vai cair, mês a mês (só parcelas pendentes, em valor líquido). Em dólar/euro fica na moeda original, sem converter.</p>
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
