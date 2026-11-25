<?php
use App\Core\Charts;

/**
 * Visão executiva: "como está o negócio?". Tem valores em R$: só o admin chega nesta tela.
 * Ordem: pontos de atenção (faixa curta) > 4 números grandes > gráfico principal > carteira, crescimento e equipe.
 * Os números vêm todos do Insights::build(), iguais aos de antes; só a apresentação mudou.
 */
$c = $x['cards'];
$fin = $x['finance'];
$al = $x['alerts'];
$link = fn(string $p, string $g) => url('/admin?aba=executiva&p=' . $p . '&g=' . $g);

// R$ inteiro nos cartões; zero vira "—" (igual ao Financeiro)
$brl = fn(float $v, int $dec = 0) => $v > 0 ? money($v, $dec) : '—';
$foreign = fn(array $by) => array_diff_key(array_filter($by, fn($v) => $v > 0), ['BRL' => 1]);
$abroad = function (array $by) use ($foreign): string {
    $f = $foreign($by);
    return $f ? '<span class="xsub">+ ' . e(money_multi($f)) . ' (exterior)</span>' : '';
};
// Comparação com o período anterior; sem base para comparar vira só um traço discreto
$trend = function (?array $t) {
    if ($t === null) {
        return '<span class="trend flat" title="Sem período anterior para comparar">—</span>';
    }
    $arrow = ['up' => '↑', 'down' => '↓', 'flat' => '→'][$t['dir']];
    $sign = ['up' => '+', 'down' => '−', 'flat' => ''][$t['dir']];
    $word = ['up' => 'a mais', 'down' => 'a menos', 'flat' => 'igual'][$t['dir']];
    return '<span class="trend ' . e($t['dir']) . '"><span aria-hidden="true">' . $arrow . '</span> ' . $sign . (int) $t['pct'] . '%<span class="sr-only"> ' . $word . '</span> <span class="muted">vs. período anterior</span></span>';
};
$pct = fn(?int $v) => $v === null ? '—' : (int) $v . '%';
$monthLabel = fn(array $m) => $m['label'] . '/' . substr($m['ym'], 0, 4);
$yellow = (int) $x['yellow'];
?>

<div class="xbar">
    <div class="chips" role="group" aria-label="Período">
        <?php foreach (\App\Services\Insights::periods() as $k => $lab): ?>
            <a class="chip" href="<?= e($link($k, $graph)) ?>"<?= $period === $k ? ' aria-current="true"' : '' ?>><?= e($lab) ?></a>
        <?php endforeach; ?>
    </div>
    <span class="tiny muted"><?= e(fmt_date($x['window']['from'])) ?> a <?= e(fmt_date($x['window']['to'])) ?></span>
</div>

<!-- 1. Pontos de atenção: só aparece o que tem número; quem quer agir abre a Operação -->
<?php
$items = [
    ['bad',  $al['d7'],        'vence em até 7 dias',            'vencem em até 7 dias',            url('/admin/pacientes?v=d7'), 'Pacientes perto do vencimento'],
    ['bad',  $al['expired'],   'plano vencido',                  'planos vencidos',                 url('/admin/pacientes?v=expired'), 'Pacientes com plano vencido'],
    ['bad',  $al['late'],      'parcela em atraso',              'parcelas em atraso',              url('/admin/financeiro?f=late'), 'Parcelas em atraso (inadimplência)'],
    ['warn', $al['noresp'],    'sem responsável',                'sem responsável',                 url('/admin/pacientes?st=active&r=none'), 'Pacientes em carteira sem responsável (sem acompanhamento)'],
    ['info', $al['birthdays'], 'aniversariante hoje',            'aniversariantes hoje',            url('/admin/aniversarios?q=hoje'), 'Aniversariantes de hoje'],
];
$shown = array_values(array_filter($items, fn($i) => (int) $i[1] > 0));
?>
<section class="xstrip" aria-label="Pontos de atenção">
    <div class="xstrip-head">
        <span class="xstrip-t">Pontos de atenção</span>
        <?php if ($shown): ?><a class="xstrip-go" href="<?= e(url('/admin')) ?>">Ver operação →</a><?php endif; ?>
    </div>
    <?php if (!$shown): ?>
        <span class="xstrip-ok"><?= icon('check', 16) ?> Nada pedindo atenção agora.</span>
    <?php else: ?>
        <ul class="xpills">
            <?php foreach ($shown as [$sev, $n, $one, $many, $href, $tip]): ?>
                <li><a class="apill" href="<?= e($href) ?>" title="<?= e($tip) ?>"><i class="dotsev <?= e($sev) ?>"></i><b class="num"><?= (int) $n ?></b><span><?= e((int) $n === 1 ? $one : $many) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<!-- 2. Quatro números -->
<div class="xkpis">
    <a class="xcard" href="<?= e(url('/admin/pacientes?st=active')) ?>">
        <span class="xlbl"><?= icon('users', 18) ?> Pacientes em carteira</span>
        <span class="xbig num"><?= (int) $c['active']['value'] ?></span>
        <?= $trend($c['active']['trend']) ?>
        <span class="xsub"><?= (int) $c['new']['value'] ?> <?= (int) $c['new']['value'] === 1 ? 'novo paciente' : 'novos pacientes' ?> no período</span>
    </a>
    <a class="xcard" href="<?= e(url('/admin/financeiro')) ?>">
        <span class="xlbl"><?= icon('cash', 18) ?> Vendido no período</span>
        <span class="xbig num"><?= e($brl($c['sold']['brl'])) ?></span>
        <?= $trend($c['sold']['trend']) ?>
        <?= $abroad($c['sold']['by']) ?>
    </a>
    <a class="xcard" href="<?= e(url('/admin/financeiro?f=received')) ?>">
        <span class="xlbl"><?= icon('check', 18) ?> Recebido no período</span>
        <span class="xbig num"><?= e($brl($c['received']['value'])) ?></span>
        <?= $trend($c['received']['trend']) ?>
    </a>
    <div class="xcard">
        <span class="xlbl"><?= icon('chart', 18) ?> Taxa de renovação</span>
        <?php $rr = $c['renewal']['rate']; ?>
        <span class="xrow">
            <span class="xbig num"><?= e($pct($rr['pct'])) ?></span>
            <?php if ($rr['pct'] !== null && $rr['pct'] > 0): ?><?= Charts::ring($rr['pct'], 48) ?><?php endif; ?>
        </span>
        <?php if ($rr['due'] > 0): ?>
            <span class="xsub"><?= (int) $rr['renewed'] ?> de <?= (int) $rr['due'] ?> planos que venceram</span>
            <?php if ($c['renewal']['trend'] !== null): ?><?= str_replace('%', ' pp', $trend($c['renewal']['trend'])) ?><?php endif; ?>
        <?php else: ?>
            <span class="xsub">Nenhum plano venceu neste período.</span>
        <?php endif; ?>
    </div>
</div>

<div class="xgrid">

    <!-- 3. Gráfico principal -->
    <section class="panel xp xp-7" aria-labelledby="h-fin">
        <div class="xp-head">
            <h3 id="h-fin">Evolução financeira</h3>
            <div class="chips seg" role="group" aria-label="Meses no gráfico">
                <?php foreach (\App\Services\Insights::graphs() as $k => $lab): ?>
                    <a class="chip" href="<?= e($link($period, $k)) ?>"<?= $graph === (string) $k ? ' aria-current="true"' : '' ?>><?= e($lab) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <dl class="xnums">
            <div><dt>Vendido no ano</dt><dd class="num"><?= e($brl((float) ($fin['yearSold']['BRL'] ?? 0), 2)) ?></dd><?= $abroad($fin['yearSold']) ?></div>
            <div><dt>A receber <span class="tiny muted">(parcelas a vencer)</span></dt><dd class="num"><?= e($brl((float) ($fin['open']['by']['BRL'] ?? 0), 2)) ?></dd><?= $abroad($fin['open']['by']) ?></div>
        </dl>
        <?php if ($fin['late']['count'] > 0): ?>
            <a class="xwarn" href="<?= e(url('/admin/financeiro?f=late')) ?>"><i class="dotsev bad"></i>Inadimplência: <b class="num"><?= e($brl((float) ($fin['late']['by']['BRL'] ?? 0), 2)) ?></b><?php
                $lf = $foreign($fin['late']['by']); if ($lf): ?> + <?= e(money_multi($lf)) ?> (exterior)<?php endif; ?> · <?= (int) $fin['late']['count'] ?> <?= $fin['late']['count'] === 1 ? 'parcela' : 'parcelas' ?> · <?= (int) $fin['late']['patients'] ?> <?= $fin['late']['patients'] === 1 ? 'paciente' : 'pacientes' ?> →</a>
        <?php else: ?>
            <p class="xnowarn">Inadimplência: nenhuma parcela em atraso.</p>
        <?php endif; ?>

        <div class="xlegend"><span><i class="lgd ch-s1"></i> Vendido</span><span><i class="lgd ch-s2"></i> Recebido</span></div>
        <?php
        $svg = Charts::lines(
            array_column($x['chart'], 'label'),
            [
                ['name' => 'Vendido', 'cls' => 'ch-s1', 'values' => array_column($x['chart'], 'sold')],
                ['name' => 'Recebido', 'cls' => 'ch-s2', 'values' => array_column($x['chart'], 'received')],
            ],
            560,
            380
        );
        ?>
        <?php if ($svg !== ''): ?>
            <?= $svg ?>
        <?php else: ?>
            <div class="empty"><strong>Ainda sem vendas em reais nesses meses</strong>Quando houver vendas ou parcelas recebidas, o gráfico aparece aqui.</div>
        <?php endif; ?>
        <details class="xtable">
            <summary>Ver em tabela</summary>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>Mês</th><th class="r">Vendido</th><th class="r">Recebido</th></tr></thead>
                <tbody>
                <?php foreach ($x['chart'] as $m): ?>
                    <tr><td><?= e($monthLabel($m)) ?><?= $m['partial'] ? ' <span class="tag">em andamento</span>' : '' ?></td><td class="r num"><?= e(money($m['sold'], 2)) ?></td><td class="r num"><?= e(money($m['received'], 2)) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </details>
        <p class="tiny muted xfoot">Valores em reais (R$). Dólar e euro não entram na soma. "Recebido" é o líquido (depois da taxa do cartão). O mês atual ainda está em andamento.</p>
    </section>

    <div class="xcol">
    <!-- 4. Carteira: um gráfico por vez -->
    <section class="panel" aria-labelledby="h-cart">
        <div class="xp-head"><h3 id="h-cart">Distribuição da carteira</h3><span class="tiny muted">hoje</span></div>
        <?php
        $plans = $x['plans'];
        $ranked = array_values(array_filter($plans, fn($p) => !$p['none']));
        $noPlan = array_values(array_filter($plans, fn($p) => $p['none']));
        $extra = [];
        if (count($ranked) > 4) {
            $extra = array_slice($ranked, 3);
            $ranked = array_slice($ranked, 0, 3);
        }
        $ramp = [1 => [1], 2 => [1, 3], 3 => [1, 2, 3], 4 => [1, 2, 3, 4]][count($ranked)] ?? [];
        $segs = [];
        foreach ($ranked as $i => $p) {
            $segs[] = ['label' => $p['name'], 'value' => $p['count'], 'cls' => 'dn-' . $ramp[$i], 'pct' => $p['pct']];
        }
        if ($extra) {
            $segs[] = ['label' => 'Outros planos', 'value' => array_sum(array_column($extra, 'count')), 'cls' => 'dn-x', 'pct' => array_sum(array_column($extra, 'pct'))];
        }
        if ($noPlan) {
            $segs[] = ['label' => 'Sem plano', 'value' => $noPlan[0]['count'], 'cls' => 'dn-0', 'pct' => $noPlan[0]['pct']];
        }
        ?>
        <div class="segtabs" data-segtabs>
            <div class="segtabs-bar" role="tablist" aria-label="Ver a carteira por" hidden>
                <button type="button" role="tab" id="ct-plano" aria-controls="cp-plano" aria-selected="true" data-seg="plano">Plano</button>
                <button type="button" role="tab" id="ct-sexo" aria-controls="cp-sexo" aria-selected="false" data-seg="sexo" tabindex="-1">Sexo</button>
                <button type="button" role="tab" id="ct-modal" aria-controls="cp-modal" aria-selected="false" data-seg="modal" tabindex="-1">Modalidade</button>
            </div>

            <div class="segtabs-panel" role="tabpanel" id="cp-plano" aria-labelledby="ct-plano" data-seg-panel="plano">
                <?php if (!$segs): ?>
                    <div class="empty"><strong>Nenhum paciente em carteira</strong>Cadastre pacientes com plano para ver a distribuição.</div>
                <?php else: ?>
                    <div class="xcart">
                        <?= Charts::donut($segs, (string) (int) $x['activeTotal'], $x['activeTotal'] === 1 ? 'paciente' : 'pacientes') ?>
                        <ul class="xlist">
                            <?php foreach ($segs as $s): ?>
                                <li><i class="lgd <?= e($s['cls']) ?>"></i><span class="nm"><?= e($s['label']) ?></span><b class="num"><?= (int) $s['pct'] ?>%</b><span class="muted num">(<?= (int) $s['value'] ?>)</span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="segtabs-panel" role="tabpanel" id="cp-sexo" aria-labelledby="ct-sexo" data-seg-panel="sexo">
                <h4 class="xsubh">Por sexo <span class="tiny muted">(% de quem tem o sexo informado)</span></h4>
                <?php if (!$x['sex']['known']): ?>
                    <p class="muted small">Ainda não há sexo informado. Preencha na ficha de cada paciente (ou na planilha de importação) para este gráfico aparecer.</p>
                <?php else: ?>
                    <ul class="mbars has-n">
                        <?php foreach ($x['sex']['list'] as $s): ?>
                            <li><span class="nm"><?= e($s['name']) ?></span><span class="mb" aria-hidden="true"><i style="width:<?= (int) $s['pct'] ?>%"></i></span><b class="num"><?= (int) $s['pct'] ?>%</b><span class="muted num">(<?= (int) $s['count'] ?>)</span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($x['sex']['unknown'] > 0): ?><p class="tiny muted" style="margin:6px 0 0"><?= (int) $x['sex']['unknown'] ?> <?= $x['sex']['unknown'] === 1 ? 'paciente' : 'pacientes' ?> em carteira sem sexo informado (fora da conta).</p><?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="segtabs-panel" role="tabpanel" id="cp-modal" aria-labelledby="ct-modal" data-seg-panel="modal">
                <h4 class="xsubh">Por modalidade <span class="tiny muted">(% dos atletas; um paciente pode ter mais de uma)</span></h4>
                <?php if (!$x['sports']['list']): ?>
                    <p class="muted small">Ainda não há atletas com modalidade cadastrada.</p>
                <?php else: ?>
                    <ul class="mbars">
                        <?php foreach (array_slice($x['sports']['list'], 0, 6) as $s): ?>
                            <li><span class="nm"><?= e($s['name']) ?></span><span class="mb" aria-hidden="true"><i style="width:<?= (int) $s['pct'] ?>%"></i></span><b class="num"><?= (int) $s['pct'] ?>%</b></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($x['sports']['list']) > 6): ?><p class="tiny muted" style="margin:6px 0 0">+ <?= count($x['sports']['list']) - 6 ?> outras modalidades (veja em Pacientes, filtro por modalidade).</p><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 5. Crescimento -->
    <section class="panel" aria-labelledby="h-cres">
        <div class="xp-head">
            <h3 id="h-cres">Crescimento da carteira</h3>
            <?php $gp = $x['growth']['pct']; ?>
            <?php if ($gp === null): ?>
                <span class="xdelta flat" title="Ainda não há 6 meses de histórico para comparar">—</span>
            <?php else: ?>
                <span class="xdelta <?= $gp < 0 ? 'down' : ($gp > 0 ? 'up' : 'flat') ?>"><b class="num"><?= $gp > 0 ? '+' : ($gp < 0 ? '−' : '') ?><?= abs((int) $gp) ?>%</b> em 6 meses</span>
            <?php endif; ?>
        </div>
        <p class="tiny muted" style="margin:-6px 0 8px">pacientes em carteira no fim de cada mês</p>
        <?= Charts::columns(array_column($x['growth']['months'], 'label'), array_column($x['growth']['months'], 'active')) ?>
        <details class="xtable">
            <summary>Ver em tabela</summary>
            <div class="tbl-wrap xmonths"><table class="tbl">
                <thead><tr><th>Mês</th><th class="r">Novos</th><th class="r">Em carteira</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($x['growth']['months']) as $m): ?>
                    <tr><td><?= e($monthLabel($m)) ?><?= $m['partial'] ? ' <span class="tag">em andamento</span>' : '' ?></td><td class="r num"><?= (int) $m['new'] ?></td><td class="r num"><?= (int) $m['active'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </details>
        <p class="tiny muted xfoot">Novos = pacientes que começaram no mês. Em carteira = pacientes com plano em dia no último dia do mês. Estimativa pela data "Paciente desde" e pelo vencimento atual de cada paciente.</p>
    </section>

    </div>

    <!-- 6. Equipe -->
    <section class="panel xp" aria-labelledby="h-eq">
        <div class="xp-head"><h3 id="h-eq">Desempenho dos responsáveis</h3><span class="tiny muted">renovação no período escolhido</span></div>
        <?php $team = $x['team']; ?>
        <?php if (!$team['rows'] && $team['noResponsible'] === 0): ?>
            <div class="empty"><strong>Nenhum responsável cadastrado</strong>Cadastre em Admin &gt; Responsáveis e atribua os pacientes (Gabriel e as estagiárias).</div>
        <?php else: ?>
            <div class="tbl-wrap"><table class="tbl xteam">
                <thead><tr><th>Responsável</th><th class="r">Pacientes</th><th class="r">Consultas</th><th class="r">Renovação</th></tr></thead>
                <tbody>
                <?php foreach ($team['rows'] as $r): ?>
                    <tr>
                        <td><a class="who" href="<?= e(url('/admin/pacientes?st=active&r=' . (int) $r['id'])) ?>"><span class="ini"><?= e(initials($r['name'])) ?></span><?= e($r['name']) ?><?= $r['active'] ? '' : ' <span class="tag">desativada</span>' ?></a></td>
                        <td class="r num"><?= (int) $r['patients'] ?></td>
                        <td class="r num"><?= e($pct($r['cons'])) ?></td>
                        <td class="r num"><?= e($pct($r['renewal'])) ?><?= $r['due'] > 0 ? ' <span class="tiny muted">(' . (int) $r['due'] . ')</span>' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="<?= $team['noResponsible'] > 0 ? 'xnoresp' : '' ?>">
                    <td><a class="who" href="<?= e(url('/admin/pacientes?st=active&r=none')) ?>"><span class="ini">?</span><span>Sem responsável<span class="tiny muted" style="display:block">sem acompanhamento</span></span></a></td>
                    <td class="r num"><?= (int) $team['noResponsible'] ?></td><td class="r muted">—</td><td class="r muted">—</td>
                </tr>
                </tbody>
            </table></div>
        <?php endif; ?>
        <p class="tiny muted xfoot">Consultas = consultas feitas sobre a cota dos planos (só planos com cota definida). Renovação = planos que venceram no período e renovaram, entre os pacientes de cada um (entre parênteses, quantos venceram). Só o admin vê.</p>
    </section>
</div>
