<div class="page-head"><h2>Aniversários</h2></div>
<p class="muted small">Pacientes com plano em dia (ou sem data de vencimento) que fazem aniversário no período. O botão abre o WhatsApp com a mensagem pronta: você confere e envia. A data de nascimento é só do admin; o paciente nunca vê.</p>

<div class="xbar" style="margin-bottom:14px">
    <div class="chips" role="group" aria-label="Período">
        <?php foreach ($windows as $k => $lab): ?>
            <a class="chip" href="<?= e(url('/admin/aniversarios?q=' . $k)) ?>"<?= $key === (string) $k ? ' aria-current="true"' : '' ?>><?= e($lab) ?></a>
        <?php endforeach; ?>
    </div>
    <span class="tiny muted"><?= $from === $to ? e(fmt_date($from)) : e(fmt_date($from)) . ' a ' . e(fmt_date($to)) ?></span>
</div>

<?php if (!$rows): ?>
    <div class="empty"><strong>Ninguém faz aniversário neste período</strong>Escolha outro período acima ou preencha a data de nascimento na ficha dos pacientes.</div>
<?php else: ?>
    <?php partial('birthday-table', ['rows' => $rows]); ?>
<?php endif; ?>

<?php if ($missing > 0): ?>
    <p class="small muted" style="margin-top:14px"><?= (int) $missing ?> <?= $missing === 1 ? 'paciente em dia ainda está sem data de nascimento' : 'pacientes em dia ainda estão sem data de nascimento' ?>. Preencha na ficha de cada um (ou na planilha de importação).</p>
<?php endif; ?>

<div class="panel" style="margin-top:18px">
    <h3>Texto da mensagem</h3>
    <p class="small" style="white-space:pre-wrap;margin:0 0 8px"><?= e($sample) ?></p>
    <p class="small muted" style="margin:0">Exemplo com o nome "Maria". Para mudar o texto, vá em <a href="<?= e(url('/admin/config')) ?>">Configurações</a>.</p>
</div>
