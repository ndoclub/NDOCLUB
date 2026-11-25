<?php
/**
 * Módulo App do paciente (09.5): tudo o que é do app, num lugar só.
 * O menu lateral agrupa as páginas do módulo; aqui ficam os números que saíram do Painel e os atalhos.
 */
$plural = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);
$nRes = (int) $o['redemptions'];
$nRep = (int) $o['reports'];
$nAway = (int) $o['away'];
$nNo = (int) $o['noAccess'];
$nRisk = (int) $o['risk'];
?>
<div class="page-head"><h2>App do paciente</h2></div>
<p class="muted small" style="max-width:640px;margin-top:0">Tudo o que é do app fica aqui. Estes números só passam a mostrar movimento quando os pacientes começarem a usar.</p>

<h3 class="osec">Para fazer</h3>
<div class="otasks">
    <div class="otask<?= $nRes === 0 ? ' is-zero' : '' ?>">
        <a class="otask-main" href="<?= e(url('/admin/resgates')) ?>">
            <span class="otask-ico"><?= icon('bag', 20) ?></span>
            <span class="big num"><?= $nRes ?></span>
            <span class="lbl"><?= $nRes === 1 ? 'resgate para entregar' : 'resgates para entregar' ?></span>
        </a>
        <span class="otask-sub"><?= $nRes === 0 ? 'Nada esperando por você.' : 'Pedidos de prêmio da loja.' ?></span>
    </div>

    <div class="otask<?= $nRep > 0 ? ' is-alert' : ' is-zero' ?>">
        <a class="otask-main" href="<?= e(url('/admin/moderacao')) ?>">
            <span class="otask-ico"><?= icon('flag', 20) ?></span>
            <span class="big num"><?= $nRep ?></span>
            <span class="lbl"><?= $nRep === 1 ? 'denúncia para analisar' : 'denúncias para analisar' ?></span>
        </a>
        <span class="otask-sub"><?= $nRep === 0 ? 'Feed sem denúncias.' : 'Publicações denunciadas no feed.' ?></span>
    </div>

    <div class="otask<?= $nAway === 0 ? ' is-zero' : '' ?>">
        <a class="otask-main" href="<?= e(url('/admin/pacientes?f=away')) ?>">
            <span class="otask-ico"><?= icon('eye', 20) ?></span>
            <span class="big num"><?= $nAway ?></span>
            <span class="lbl">sem abrir o app há 7 dias ou mais</span>
        </a>
        <span class="otask-sub"><?php if ($nAway === 0): ?>Todo mundo apareceu esta semana.<?php elseif ($nRisk > 0): ?><b class="bad"><?= e($plural($nRisk, 'vence', 'vencem')) ?></b> em até <?= (int) $o['yellow'] ?> dias: renovação em risco.<?php else: ?>Hora de mandar uma mensagem.<?php endif; ?></span>
    </div>

    <div class="otask<?= $nNo === 0 ? ' is-zero' : '' ?>">
        <a class="otask-main" href="<?= e(url('/admin/pacientes?f=noapp')) ?>">
            <span class="otask-ico"><?= icon('users', 20) ?></span>
            <span class="big num"><?= $nNo ?></span>
            <span class="lbl">sem acesso ao app</span>
        </a>
        <span class="otask-sub"><?= $nNo === 0 ? 'Todos já têm acesso.' : 'O acesso é liberado na aba App do paciente da ficha.' ?></span>
    </div>
</div>

<div class="ostats" aria-label="Resumo do app">
    <span><b class="num"><?= (int) $o['withAccess'] ?></b><span class="lbl">com acesso liberado</span></span>
    <span><b class="num"><?= (int) $o['active7'] ?></b><span class="lbl">abriram o app em 7 dias</span></span>
    <span><b class="num"><?= (int) $o['checkins'] ?></b><span class="lbl">check-ins hoje</span></span>
</div>

<h3 class="osec">Ir para</h3>
<div class="chips" role="group" aria-label="Páginas do app">
    <a class="chip" href="<?= e(url('/admin/treinos')) ?>">Treinos</a>
    <a class="chip" href="<?= e(url('/admin/ranking')) ?>">Ranking</a>
    <a class="chip" href="<?= e(url('/admin/loja')) ?>">Loja</a>
    <a class="chip" href="<?= e(url('/admin/resgates')) ?>">Resgates</a>
    <a class="chip" href="<?= e(url('/admin/pontos')) ?>">Regras de pontos</a>
    <a class="chip" href="<?= e(url('/admin/moderacao')) ?>">Moderação</a>
    <a class="chip" href="<?= e(url('/admin/app/ajustes')) ?>">Ajustes do app</a>
    <a class="chip" href="<?= e(url('/feed')) ?>">Ver o feed</a>
</div>
