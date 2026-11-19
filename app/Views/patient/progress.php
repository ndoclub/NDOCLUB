<?php
$n = count($rows);
$cr = array_slice($rows, -30);
$latest = $n ? $rows[$n - 1] : null;
$first = $n ? $rows[0] : null;
$fmt = fn($v, $d = 1) => number_format((float) $v, $d, ',', '.');
?>
<section class="page" style="padding-bottom:8px">
    <?php if ($latest): ?>
        <p class="title-xl num"><?= $fmt($latest['weight_kg']) ?> kg</p>
        <p class="muted">em <?= e(fmt_date($latest['measured_on'])) ?>
            <?php if ($n > 1):
                $delta = (float) $latest['weight_kg'] - (float) $first['weight_kg']; ?>
                · <?= $delta > 0 ? '+' : ($delta < 0 ? '−' : '') ?><?= $fmt(abs($delta)) ?> kg desde <?= e(fmt_date($first['measured_on'])) ?>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <p class="lead">Registre seu peso para acompanhar a sua evolução. Só você e a equipe do Gabriel veem estes dados.</p>
    <?php endif; ?>
</section>

<?php if (count($cr) >= 2):
    $W = 360; $H = 190; $pl = 42; $pr = 14; $pt = 14; $pb = 28;
    $ws = array_map(fn($r) => (float) $r['weight_kg'], $cr);
    $min = min($ws); $max = max($ws);
    if ($max - $min < 1) { $mid = ($max + $min) / 2; $min = $mid - 1; $max = $mid + 1; }
    $padv = ($max - $min) * 0.15; $min -= $padv; $max += $padv;
    $t0 = strtotime($cr[0]['measured_on']); $t1 = strtotime($cr[count($cr) - 1]['measured_on']); $span = max(1, $t1 - $t0);
    $X = fn($t) => $pl + ($t - $t0) / $span * ($W - $pl - $pr);
    $Y = fn($w) => $pt + (1 - ($w - $min) / ($max - $min)) * ($H - $pt - $pb);
    $points = [];
    foreach ($cr as $r) { $points[] = [round($X(strtotime($r['measured_on'])), 1), round($Y((float) $r['weight_kg']), 1)]; }
    $poly = implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $points));
?>
<section class="page" style="padding-top:8px;padding-bottom:12px">
    <svg class="chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Gráfico do peso ao longo do tempo">
        <?php for ($i = 0; $i < 3; $i++): $v = $min + ($max - $min) * $i / 2; $y = $Y($v); ?>
            <line class="grid" x1="<?= $pl ?>" x2="<?= $W - $pr ?>" y1="<?= round($y, 1) ?>" y2="<?= round($y, 1) ?>"/>
            <text x="<?= $pl - 8 ?>" y="<?= round($y + 4, 1) ?>" text-anchor="end"><?= $fmt($v) ?></text>
        <?php endfor; ?>
        <polyline class="line" points="<?= e($poly) ?>"/>
        <?php foreach ($points as $p): ?><circle class="pt" cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="4"/><?php endforeach; ?>
        <text x="<?= $pl ?>" y="<?= $H - 6 ?>" text-anchor="start"><?= e(date('d/m', $t0)) ?></text>
        <text x="<?= $W - $pr ?>" y="<?= $H - 6 ?>" text-anchor="end"><?= e(date('d/m', $t1)) ?></text>
    </svg>
</section>
<?php elseif ($n === 1): ?>
    <p class="hint" style="padding:0 16px">Registre mais uma medida para ver o gráfico.</p>
<?php endif; ?>

<section class="section">
    <h2 class="section-title">Registrar medida</h2>
    <form method="post" action="<?= e(url('/evolucao')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="date">Data</label>
            <input class="input" type="date" id="date" name="date" value="<?= e(old('date', today())) ?>" max="<?= e(today()) ?>" required>
        </div>
        <div class="field">
            <label for="weight">Peso (kg)</label>
            <input class="input" type="text" inputmode="decimal" id="weight" name="weight" value="<?= e(old('weight')) ?>" placeholder="Ex.: 72,5" required>
        </div>
        <div class="field">
            <label for="waist">Cintura (cm), se quiser</label>
            <input class="input" type="text" inputmode="decimal" id="waist" name="waist" value="<?= e(old('waist')) ?>" placeholder="Ex.: 84">
        </div>
        <button class="btn btn-block" type="submit">Salvar medida</button>
        <p class="hint">Registrar de novo na mesma data atualiza o valor.</p>
    </form>
</section>

<?php if ($rows): ?>
<h2 class="section-title section-head">Histórico</h2>
<div class="list">
    <?php foreach (array_reverse($rows) as $r): ?>
        <div class="li">
            <div class="li-main">
                <span class="li-title num"><?= $fmt($r['weight_kg']) ?> kg<?= $r['waist_cm'] !== null ? ' - cintura ' . $fmt($r['waist_cm']) . ' cm' : '' ?></span>
                <span class="li-sub"><?= e(fmt_date($r['measured_on'])) ?></span>
            </div>
            <form method="post" action="<?= e(url('/evolucao/' . $r['id'] . '/excluir')) ?>" data-confirm="Remover esta medida?">
                <?= csrf_field() ?><button class="link-btn danger small" type="submit">Remover</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
