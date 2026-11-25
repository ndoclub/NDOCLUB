<?php $n = count($r['created']); $f = count($r['failed']); ?>
<div class="page-head">
    <h2>Importação concluída</h2>
    <a class="btn btn-sm" href="<?= e(url('/admin/pacientes')) ?>">Ver a lista de pacientes</a>
</div>

<div class="kpis imp-kpis">
    <div class="kpi imp-ok"><span class="big num"><?= $n ?></span><span class="lbl">importados</span></div>
    <div class="kpi"><span class="big num"><?= (int) $r['dup'] ?></span><span class="lbl">duplicados (pulados)</span></div>
    <div class="kpi imp-err"><span class="big num"><?= (int) $r['err'] ?></span><span class="lbl">com erro (não entraram)</span></div>
    <div class="kpi"><span class="big num"><?= $f ?></span><span class="lbl">falhas ao salvar</span></div>
</div>

<div class="panel">
    <h3>Próximos passos</h3>
    <ul class="imp-rules">
        <li>Os <?= $n ?> pacientes entram <strong>sem senha e sem acesso ao app</strong>. Quando o app começar, o acesso é liberado na aba <em>App do paciente</em> da ficha de cada um.</li>
        <li>Pagamentos informados já estão em <a href="<?= e(url('/admin/financeiro')) ?>">Financeiro</a>. Confira o faturamento do mês.</li>
        <?php if ($r['dup'] + $r['err'] > 0): ?><li>Linhas duplicadas ou com erro não foram importadas. Corrija a planilha e envie só essas linhas.</li><?php endif; ?>
        <li>Apague a planilha do seu computador (e de qualquer conversa) para não deixar dados pessoais soltos.</li>
    </ul>
</div>

<?php if ($f > 0): ?>
<div class="panel">
    <h3>Linhas que não foram salvas</h3>
    <ul class="imp-msgs">
        <?php foreach ($r['failed'] as $x): ?><li class="m-err">Linha <?= (int) $x['line'] ?> (<?= e($x['name']) ?>): <?= e($x['reason']) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($n > 0): ?>
<div class="panel">
    <h3>Pacientes importados</h3>
    <div class="imp-created">
        <?php foreach ($r['created'] as $x): ?>
            <a class="chip" href="<?= e(url('/admin/pacientes/' . (int) $x['id'])) ?>"><?= e($x['name']) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="form-actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/importar')) ?>">Importar outro arquivo</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/pacientes')) ?>">Ir para Pacientes</a>
</div>
