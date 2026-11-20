<?php
$labelClass = ['ok' => 'ok', 'warn' => 'warn', 'dup' => '', 'err' => 'bad'];
$tab = function (string $key, string $label, int $n) use ($only) {
    $href = url('/admin/importar/previa') . ($key !== '' ? '?v=' . $key : '');
    return '<a class="chip" href="' . e($href) . '"' . ($only === $key ? ' aria-current="true"' : '') . '>' . e($label) . ' (' . $n . ')</a>';
};
$problems = $summary['warn'] + $summary['dup'] + $summary['err'];
?>
<div class="page-head">
    <h2>Prévia da importação</h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/importar')) ?>">Voltar</a>
</div>

<div class="panel imp-safe">
    <strong>Nada foi salvo ainda.</strong>
    <span class="small muted">Arquivo <?= e($file) ?> · aba <?= e($sheet) ?>. Confira as linhas abaixo e só então confirme.</span>
</div>

<?php if ($ignored): ?>
<div class="panel imp-note" role="status">
    <span class="small">Estas colunas não fazem parte do modelo e foram <strong>ignoradas</strong>: <?= e(implode(', ', $ignored)) ?>.</span>
</div>
<?php endif; ?>

<div class="kpis imp-kpis">
    <div class="kpi"><span class="big num"><?= (int) $summary['total'] ?></span><span class="lbl">linhas lidas</span></div>
    <div class="kpi imp-ok"><span class="big num"><?= (int) $summary['ok'] ?></span><span class="lbl">prontas</span></div>
    <div class="kpi imp-warn"><span class="big num"><?= (int) $summary['warn'] ?></span><span class="lbl">com aviso</span></div>
    <div class="kpi"><span class="big num"><?= (int) $summary['dup'] ?></span><span class="lbl">duplicadas (puladas)</span></div>
    <div class="kpi imp-err"><span class="big num"><?= (int) $summary['err'] ?></span><span class="lbl">com erro (não entram)</span></div>
</div>

<div class="panel imp-confirm">
    <?php if ($summary['importable'] > 0): ?>
        <form method="post" action="<?= e(url('/admin/importar/confirmar')) ?>" data-confirm="Importar <?= (int) $summary['importable'] ?> paciente(s) agora?">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e((string) ($_SESSION['_import']['token'] ?? '')) ?>">
            <p><strong><?= (int) $summary['importable'] ?></strong> <?= $summary['importable'] === 1 ? 'paciente será importado' : 'pacientes serão importados' ?>.
                <?php if ($summary['dup'] + $summary['err'] > 0): ?><span class="muted">As <?= (int) ($summary['dup'] + $summary['err']) ?> linhas duplicadas ou com erro ficam de fora.</span><?php endif; ?></p>
            <?php if ($summary['warn'] > 0): ?>
                <div class="field" style="margin-bottom:12px"><label class="checkbox"><input type="checkbox" name="ack" value="1" required> Conferi os avisos e quero importar essas linhas mesmo assim</label></div>
            <?php endif; ?>
            <div class="form-actions" style="margin-top:0">
                <button class="btn" type="submit">Confirmar e importar <?= (int) $summary['importable'] ?></button>
                <?php if ($problems > 0): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/importar/problemas.csv')) ?>">Baixar relatório dos problemas (.csv)</a><?php endif; ?>
            </div>
        </form>
    <?php else: ?>
        <p><strong>Nenhuma linha está pronta para importar.</strong> Corrija a planilha e envie de novo.</p>
        <?php if ($problems > 0): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/importar/problemas.csv')) ?>">Baixar relatório dos problemas (.csv)</a><?php endif; ?>
    <?php endif; ?>
    <form class="inline-form" method="post" action="<?= e(url('/admin/importar/descartar')) ?>" style="display:block;margin-top:12px">
        <?= csrf_field() ?>
        <button class="link-btn" type="submit">Descartar esta prévia e enviar outro arquivo</button>
    </form>
</div>

<div class="chips">
    <?= $tab('', 'Todas', $summary['total']) ?>
    <?= $tab('problems', 'Precisam de atenção', $problems) ?>
    <?= $tab('err', 'Erros', $summary['err']) ?>
    <?= $tab('dup', 'Duplicadas', $summary['dup']) ?>
    <?= $tab('warn', 'Avisos', $summary['warn']) ?>
    <?= $tab('ok', 'Prontas', $summary['ok']) ?>
</div>

<?php if (!$rows): ?>
    <div class="empty"><strong>Nenhuma linha neste filtro</strong>Escolha outro filtro acima.</div>
<?php else: ?>
<div class="tbl-wrap">
    <table class="tbl imp-tbl">
        <thead><tr><th>Linha</th><th>Situação</th><th>Paciente</th><th class="c-plan">Plano e vencimento</th><th class="c-pay">Pagamento</th><th class="c-obs">Observações</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $v = $r['view']; ?>
            <tr class="imp-row imp-<?= e($r['status']) ?>">
                <td class="num"><?= (int) $r['line'] ?></td>
                <td><span class="tag <?= e($labelClass[$r['status']]) ?>"><?= e($labels[$r['status']]) ?></span></td>
                <td>
                    <strong><?= $v['name'] !== '' ? e($v['name']) : '<span class="muted">(sem nome)</span>' ?></strong><br>
                    <span class="small muted"><?= e($v['email']) ?><?= $v['phone'] !== '' ? ' · ' . e($v['phone']) : '' ?></span>
                    <?php if ($v['sports'] !== ''): ?><br><span class="sport"><?= e($v['sports']) ?></span><?php endif; ?>
                    <div class="imp-inline small">
                    <?php $sum = array_filter([$v['plan'] !== '' ? $v['plan'] : '', $v['renewal'] ? 'vence ' . fmt_date($v['renewal']) : '', $v['responsible'] !== '' ? 'resp.: ' . $v['responsible'] : '', $v['pay'] !== '' ? $v['pay'] : ($v['price'] !== '' ? 'combinado ' . $v['price'] : '')]); ?>
                    <?php if ($sum): ?><div class="imp-sum muted"><?= e(implode(' · ', $sum)) ?></div><?php endif; ?>
                    <?php if ($r['dups'] || $r['errors'] || $r['warnings']): ?>
                    <ul class="imp-msgs">
                        <?php foreach ($r['dups'] as $m): ?><li class="m-dup"><?= e($m) ?></li><?php endforeach; ?>
                        <?php foreach ($r['errors'] as $m): ?><li class="m-err"><?= e($m) ?></li><?php endforeach; ?>
                        <?php foreach ($r['warnings'] as $m): ?><li class="m-warn"><?= e($m) ?></li><?php endforeach; ?>
                    </ul>
                    <?php else: ?><span class="muted">Tudo certo.</span><?php endif; ?>
                    </div>
                </td>
                <td class="small c-plan">
                    <?= $v['plan'] !== '' ? e($v['plan']) : '<span class="muted">sem plano</span>' ?>
                    <?php if ($v['renewal']): ?><br><span class="muted">vence <?= e(fmt_date($v['renewal'])) ?></span><?php endif; ?>
                    <?php if ($v['responsible'] !== ''): ?><br><span class="muted">resp.: <?= e($v['responsible']) ?></span><?php endif; ?>
                </td>
                <td class="small c-pay"><?= $v['pay'] !== '' ? e($v['pay']) : '<span class="muted">sem pagamento</span>' ?><?= $v['price'] !== '' && $v['pay'] === '' ? '<br><span class="muted">combinado ' . e($v['price']) . '</span>' : '' ?></td>
                <td class="small c-obs">
                    <?php if ($r['dups'] || $r['errors'] || $r['warnings']): ?>
                    <ul class="imp-msgs">
                        <?php foreach ($r['dups'] as $m): ?><li class="m-dup"><?= e($m) ?></li><?php endforeach; ?>
                        <?php foreach ($r['errors'] as $m): ?><li class="m-err"><?= e($m) ?></li><?php endforeach; ?>
                        <?php foreach ($r['warnings'] as $m): ?><li class="m-warn"><?= e($m) ?></li><?php endforeach; ?>
                    </ul>
                    <?php else: ?><span class="muted">Tudo certo.</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
