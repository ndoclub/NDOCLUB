<?php
$groups = [];
foreach ($columns as $key => $c) {
    $groups[$c[1]][] = [$key, $c];
}
$listBox = function (string $title, array $items, string $empty) {
    echo '<div class="imp-list"><strong>' . e($title) . '</strong>';
    if ($items) {
        echo '<span>' . e(implode(', ', $items)) . '</span>';
    } else {
        echo '<span class="muted">' . e($empty) . '</span>';
    }
    echo '</div>';
};
?>
<div class="page-head">
    <h2>Importar pacientes</h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/pacientes')) ?>">Voltar aos pacientes</a>
</div>

<?php if ($pending): ?>
<div class="panel imp-pending">
    <h3>Você tem uma prévia esperando</h3>
    <p class="small">Arquivo <strong><?= e($pending['file']) ?></strong> (<?= (int) $pending['count'] ?> <?= $pending['count'] === 1 ? 'linha' : 'linhas' ?>). Nada foi salvo ainda.</p>
    <div class="form-actions" style="margin-top:0">
        <a class="btn btn-sm" href="<?= e(url('/admin/importar/previa')) ?>">Ver a prévia</a>
        <form class="inline-form" method="post" action="<?= e(url('/admin/importar/descartar')) ?>" data-confirm="Descartar esta prévia? Nada foi salvo.">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" type="submit">Descartar</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="imp-steps">
    <div class="panel">
        <span class="imp-n">1</span>
        <h3>Baixe o modelo</h3>
        <p class="small muted">Planilha Excel oficial do NDO, com os cabeçalhos certos, orientações, exemplos fictícios e listas suspensas com os seus planos, modalidades e responsáveis.</p>
        <a class="btn btn-sm" href="<?= e(url('/admin/importar/modelo')) ?>">Baixar modelo (.xlsx)</a>
    </div>
    <div class="panel">
        <span class="imp-n">2</span>
        <h3>Preencha</h3>
        <p class="small muted">Um paciente por linha, na aba <strong>Pacientes</strong>. Só <strong>Nome</strong> e <strong>E-mail</strong> são obrigatórios. Veja os detalhes de cada coluna logo abaixo.</p>
    </div>
    <div class="panel">
        <span class="imp-n">3</span>
        <h3>Envie e confira</h3>
        <p class="small muted">Você vê uma <strong>prévia</strong> linha por linha antes de salvar. Nada entra no sistema até você confirmar.</p>
        <form method="post" action="<?= e(url('/admin/importar/validar')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field" style="margin-bottom:10px">
                <label for="arquivo">Planilha preenchida (.xlsx ou .csv, até 2 MB)</label>
                <input class="input" type="file" id="arquivo" name="arquivo" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
            </div>
            <button class="btn btn-sm" type="submit">Validar planilha</button>
        </form>
    </div>
</div>

<div class="panel">
    <h3>Como a importação funciona</h3>
    <ul class="imp-rules">
        <li><strong>Prévia antes de salvar.</strong> Cada linha aparece como <span class="tag ok">Pronto</span> <span class="tag warn">Aviso</span> <span class="tag">Duplicado</span> ou <span class="tag bad">Erro</span>.</li>
        <li><strong>Linhas com erro nunca entram.</strong> Você baixa um relatório dos problemas, corrige a planilha e envia de novo.</li>
        <li><strong>Duplicados são pulados:</strong> e-mail que já existe (ou repetido na planilha), ou mesmo nome com o mesmo WhatsApp. Nome ou WhatsApp parecidos viram aviso.</li>
        <li><strong>Até <?= (int) $maxRows ?> pacientes por arquivo.</strong> Em caso de dúvida, comece com poucas linhas.</li>
        <li><strong>Paciente importado não recebe senha nem acesso ao app.</strong> Você libera depois, um a um, em <em>Gerar senha provisória</em> na ficha. Até lá ele não entra, não aparece no ranking e não conta como "sumido".</li>
        <li><strong>Valores (plano, combinado, pagamentos) são só do admin.</strong> O paciente nunca vê. Ao informar pagamento, as parcelas entram no Financeiro com as mesmas regras do cadastro manual.</li>
        <li><strong>Privacidade:</strong> a planilha tem dados pessoais. Não envie por WhatsApp ou e-mail, e apague o arquivo do computador depois de importar. O sistema não guarda o arquivo, só o resultado.</li>
    </ul>
</div>

<div class="panel">
    <h3>Valores aceitos hoje no seu sistema</h3>
    <p class="small muted">Os nomes na planilha precisam ser iguais a estes (maiúsculas e acentos não importam). O modelo baixado já traz essas listas na aba <em>Listas</em>.</p>
    <?php $listBox('Planos ativos', $lists['plans'], 'Nenhum plano cadastrado (cadastre em Admin > Planos).'); ?>
    <?php $listBox('Modalidades ativas', $lists['sports'], 'Nenhuma modalidade cadastrada (cadastre em Admin > Modalidades).'); ?>
    <?php $listBox('Responsáveis ativos', $lists['responsibles'], 'Nenhum responsável cadastrado (cadastre em Admin > Responsáveis).'); ?>
    <?php $listBox('Formas de pagamento', $lists['methods'], ''); ?>
    <?php $listBox('Moedas', $lists['currencies'], ''); ?>
    <?php $listBox('Bandeiras do cartão', $lists['brands'], ''); ?>
</div>

<div class="panel">
    <h3>Colunas do modelo</h3>
    <p class="small muted">A ordem das colunas pode mudar e colunas opcionais podem ficar vazias. O sistema reconhece pelo nome do cabeçalho.</p>
    <?php foreach ($groups as $group => $items): ?>
        <h4 class="imp-group"><?= e($group) ?></h4>
        <div class="tbl-wrap">
            <table class="tbl imp-cols">
                <thead><tr><th>Coluna</th><th>O que preencher</th><th>Regra</th><th>Exemplo</th></tr></thead>
                <tbody>
                <?php foreach ($items as [$key, $c]): ?>
                    <tr>
                        <td><strong><?= e(rtrim(str_replace('*', '', $c[0]))) ?></strong>
                            <?php if ($c[2] === true): ?><span class="tag bad">obrigatória</span>
                            <?php elseif ($c[2] === 'cond'): ?><span class="tag warn">obrigatória com pagamento</span>
                            <?php else: ?><span class="tag">opcional</span><?php endif; ?></td>
                        <td><?= e($c[4]) ?></td>
                        <td class="small muted"><?= e($c[5]) ?></td>
                        <td class="small"><?= $c[6] !== '' ? e($c[6]) : '<span class="muted">-</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>
