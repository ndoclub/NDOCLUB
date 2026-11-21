<?php
use App\Core\Charts;

/** Visão executiva: 5 dimensões do negócio. Tudo aqui tem valores em R$: só o admin chega nesta tela. */
$c = $x['cards'];
$fin = $x['finance'];
$al = $x['alerts'];
$link = fn(string $p, string $g) => url('/admin?p=' . $p . '&g=' . $g);

// R$ inteiro nos cartões; zero vira "—" (igual ao Financeiro)
$brl = fn(float $v, int $dec = 0) => $v > 0 ? money($v, $dec) : '—';
$foreign = fn(array $by) => array_diff_key(array_filter($by, fn($v) => $v > 0), ['BRL' => 1]);
$abroad = function (array $by) use ($foreign): string {
    $f = $foreign($by);
    return $f ? '<span class="xsub">+ ' . e(money_multi($f)) . ' (exterior)</span>' : '';
};
$trend = function (?array $t) {
    if ($t === null) {
        return '<span class="trend flat">— <span class="muted">sem base para comparar</span></span>';
    }
    $arrow = ['up' => '↑', 'down' => '↓', 'flat' => '→'][$t['dir']];
    $sign = ['up' => '+', 'down' => '−', 'flat' => ''][$t['dir']];
    $word = ['up' => 'a mais', 'down' => 'a menos', 'flat' => 'igual'][$t['dir']];
    return '<span class="trend ' . e($t['dir']) . '"><span aria-hidden="true">' . $arrow . '</span> ' . $sign . (int) $t['pct'] . '%<span class="sr-only"> ' . $word . '</span> <span class="muted">vs. período anterior</span></span>';
};
$pct = fn(?int $v) => $v === null ? '—' : (int) $v . '%';
$monthLabel = fn(array $m) => $m['label'] . '/' . substr($m['ym'], 0, 4);
?>

<div class="xbar">
    <div class="chips" role="group" aria-label="Período">
        <?php foreach (\App\Services\Insights::periods() as $k => $lab): ?>
            <a class="chip" href="<?= e($link($k, $graph)) ?>"<?= $period === $k ? ' aria-current="true"' : '' ?>><?= e($lab) ?></a>
        <?php endforeach; ?>
    </div>
    <span class="tiny muted"><?= e(fmt_date($x['window']['from'])) ?> a <?= e(fmt_date($x['window']['to'])) ?></span>
</div>

<!-- 1. Cartões -->
<div class="xkpis">
    <a class="xcard" href="<?= e(url('/admin/pacientes?st=active')) ?>">
        <span class="xlbl"><?= icon('users', 18) ?> Pacientes ativos</span>
        <span class="xrow"><span><span class="xbig num"><?= (int) $c['active']['value'] ?></span><?= $trend($c['active']['trend']) ?></span><?= Charts::bars($c['active']['spark']) ?></span>
    </a>
    <a class="xcard" href="<?= e(url('/admin/financeiro')) ?>">
        <span class="xlbl"><?= icon('cash', 18) ?> Vendido no período</span>
        <span class="xrow"><span><span class="xbig num"><?= e($brl($c['sold']['brl'])) ?></span><?= $trend($c['sold']['trend']) ?><?= $abroad($c['sold']['by']) ?></span><?= Charts::bars($c['sold']['spark']) ?></span>
    </a>
    <a class="xcard" href="<?= e(url('/admin/financeiro?f=received')) ?>">
        <span class="xlbl"><?= icon('check', 18) ?> Recebido no período</span>
        <span class="xrow"><span><span class="xbig num"><?= e($brl($c['received']['value'])) ?></span><?= $trend($c['received']['trend']) ?></span><?= Charts::bars($c['received']['spark']) ?></span>
    </a>
    <a class="xcard" href="<?= e(url('/admin/financeiro?f=pending')) ?>">
        <span class="xlbl"><?= icon('card', 18) ?> A receber</span>
        <span class="xrow"><span><span class="xbig num"><?= e($brl((float) ($c['open']['by']['BRL'] ?? 0))) ?></span>
            <span class="trend flat"><?= (int) $c['open']['count'] ?> <?= $c['open']['count'] === 1 ? 'parcela a vencer' : 'parcelas a vencer' ?></span><?= $abroad($c['open']['by']) ?></span></span>
    </a>
    <div class="xcard">
        <span class="xlbl"><?= icon('plus', 18) ?> Novos pacientes</span>
        <span class="xrow"><span><span class="xbig num"><?= (int) $c['new']['value'] ?></span><?= $trend($c['new']['trend']) ?></span><?= Charts::bars($c['new']['spark']) ?></span>
    </div>
    <div class="xcard">
        <span class="xlbl"><?= icon('chart', 18) ?> Taxa de renovação</span>
        <span class="xrow"><span><span class="xbig num"><?= e($pct($c['renewal']['rate']['pct'])) ?></span>
            <?php if ($c['renewal']['rate']['due'] > 0): ?>
                <span class="trend flat"><?= (int) $c['renewal']['rate']['renewed'] ?> de <?= (int) $c['renewal']['rate']['due'] ?> planos que venceram</span>
                <?php if ($c['renewal']['trend'] !== null): ?><?= str_replace('%', ' pp', $trend($c['renewal']['trend'])) ?><?php endif; ?>
            <?php else: ?>
                <span class="trend flat">nenhum plano venceu neste período</span>
            <?php endif; ?></span><?= Charts::ring($c['renewal']['rate']['pct']) ?></span>
    </div>
</div>

<div class="xgrid">

    <!-- 2. Saúde financeira -->
    <section class="panel xp xp-7" aria-labelledby="h-fin">
        <div class="xp-head">
            <h3 id="h-fin"><?= icon('chart', 20) ?> Evolução financeira</h3>
            <div class="chips seg" role="group" aria-label="Meses no gráfico">
                <?php foreach (\App\Services\Insights::graphs() as $k => $lab): ?>
                    <a class="chip" href="<?= e($link($period, $k)) ?>"<?= $graph === (string) $k ? ' aria-current="true"' : '' ?>><?= e($lab) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="xfin">
            <div class="xfin-chart">
                <div class="xlegend"><span><i class="lgd ch-s1"></i> Vendido</span><span><i class="lgd ch-s2"></i> Recebido</span></div>
                <?php
                $svg = Charts::lines(
                    array_column($x['chart'], 'label'),
                    [
                        ['name' => 'Vendido', 'cls' => 'ch-s1', 'values' => array_column($x['chart'], 'sold')],
                        ['name' => 'Recebido', 'cls' => 'ch-s2', 'values' => array_column($x['chart'], 'received')],
                    ]
                );
                ?>
                <?php if ($svg !== ''): ?>
                    <?= $svg ?>
                <?php else: ?>
                    <div class="empty"><strong>Ainda sem vendas em reais nesses meses</strong>Quando houver vendas ou parcelas recebidas, o gráfico aparece aqui.</div>
                <?php endif; ?>
            </div>
            <dl class="xfin-list">
                <div><dt>Vendido no período</dt><dd class="num"><?= e($brl($c['sold']['brl'], 2)) ?><?= $abroad($fin['sold']) ?></dd></div>
                <div><dt>Vendido no ano (acumulado)</dt><dd class="num"><?= e($brl((float) ($fin['yearSold']['BRL'] ?? 0), 2)) ?><?= $abroad($fin['yearSold']) ?></dd></div>
                <div><dt>Recebido no período</dt><dd class="num"><?= e($brl($fin['received'], 2)) ?></dd></div>
                <div><dt>A receber <span class="tiny muted">(parcelas a vencer)</span></dt><dd class="num"><?= e($brl((float) ($fin['open']['by']['BRL'] ?? 0), 2)) ?><?= $abroad($fin['open']['by']) ?></dd></div>
                <div class="<?= $fin['late']['count'] > 0 ? 'is-bad' : '' ?>"><dt>Inadimplência <span class="tiny muted">(atraso de 1 dia ou mais)</span></dt>
                    <dd class="num"><?= e($brl((float) ($fin['late']['by']['BRL'] ?? 0), 2)) ?><?= $abroad($fin['late']['by']) ?>
                        <?php if ($fin['late']['count'] > 0): ?><a class="xsub" href="<?= e(url('/admin/financeiro?f=late')) ?>"><?= (int) $fin['late']['count'] ?> <?= $fin['late']['count'] === 1 ? 'parcela' : 'parcelas' ?> · <?= (int) $fin['late']['patients'] ?> <?= $fin['late']['patients'] === 1 ? 'paciente' : 'pacientes' ?></a><?php endif; ?></dd></div>
            </dl>
        </div>
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
        <p class="tiny muted" style="margin:10px 0 0">Gráfico e tabela em reais (R$). Dólar e euro não entram na soma. "Recebido" é o líquido (depois da taxa do cartão). O mês atual ainda está em andamento.</p>
    </section>

    <!-- 3. Carteira -->
    <section class="panel xp xp-5" aria-labelledby="h-cart">
        <div class="xp-head"><h3 id="h-cart"><?= icon('users', 20) ?> Distribuição da carteira</h3><span class="tiny muted">hoje</span></div>
        <?php
        $plans = $x['plans'];
        $ranked = array_values(array_filter($plans, fn($p) => !$p['none']));
        $noPlan = array_values(array_filter($plans, fn($p) => $p['none']));
        $extra = [];
        if (count($ranked) > 4) {
            $extra = array_slice($ranked, 3);
            $ranked = array_slice($ranked, 0, 3);
        }
        $ramp = [1 => [4], 2 => [1, 4], 3 => [1, 3, 4], 4 => [1, 2, 3, 4]][count($ranked)] ?? [];
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
        <?php if (!$segs): ?>
            <div class="empty"><strong>Nenhum paciente ativo</strong>Cadastre pacientes com plano para ver a distribuição.</div>
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
        <h4 class="xsubh">Por modalidade <span class="tiny muted">(% dos atletas ativos; um paciente pode ter mais de uma)</span></h4>
        <?php if (!$x['sports']['list']): ?>
            <p class="muted small">Ainda não há atletas ativos com modalidade cadastrada.</p>
        <?php else: ?>
            <ul class="mbars">
                <?php foreach (array_slice($x['sports']['list'], 0, 6) as $s): ?>
                    <li><span class="nm"><?= e($s['name']) ?></span><span class="mb" aria-hidden="true"><i style="width:<?= (int) $s['pct'] ?>%"></i></span><b class="num"><?= (int) $s['pct'] ?>%</b></li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($x['sports']['list']) > 6): ?><p class="tiny muted" style="margin:6px 0 0">+ <?= count($x['sports']['list']) - 6 ?> outras modalidades (veja em Pacientes, filtro por modalidade).</p><?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- 4. Crescimento -->
    <section class="panel xp xp-5" aria-labelledby="h-cres">
        <div class="xp-head"><h3 id="h-cres"><?= icon('chart', 20) ?> Crescimento da carteira</h3><span class="tiny muted">pacientes ativos no fim de cada mês</span></div>
        <div class="xgrow">
            <div class="xhero<?= $x['growth']['pct'] !== null && $x['growth']['pct'] < 0 ? ' is-down' : '' ?>">
                <?php if ($x['growth']['pct'] === null): ?>
                    <b class="num">—</b><span>sem base para comparar nos últimos 6 meses</span>
                <?php else: ?>
                    <b class="num"><?= $x['growth']['pct'] > 0 ? '+' : ($x['growth']['pct'] < 0 ? '−' : '') ?><?= abs((int) $x['growth']['pct']) ?>%</b><span>de crescimento nos últimos 6 meses</span>
                <?php endif; ?>
            </div>
            <?= Charts::columns(array_column($x['growth']['months'], 'label'), array_column($x['growth']['months'], 'active')) ?>
        </div>
        <div class="tbl-wrap xmonths"><table class="tbl">
            <thead><tr><th>Mês</th><th class="r">Novos</th><th class="r">Ativos</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($x['growth']['months']) as $m): ?>
                <tr><td><?= e($monthLabel($m)) ?><?= $m['partial'] ? ' <span class="tag">em andamento</span>' : '' ?></td><td class="r num"><?= (int) $m['new'] ?></td><td class="r num"><?= (int) $m['active'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="tiny muted" style="margin:10px 0 0">Novos = pacientes que começaram no mês. Ativos = pacientes com plano em dia no último dia do mês. Estimativa pela data "Paciente desde" e pelo vencimento atual de cada paciente.</p>
    </section>

    <!-- 5. Equipe -->
    <section class="panel xp xp-7" aria-labelledby="h-eq">
        <div class="xp-head"><h3 id="h-eq"><?= icon('users', 20) ?> Desempenho dos responsáveis</h3><span class="tiny muted">renovação no período escolhido</span></div>
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
        <p class="tiny muted" style="margin:10px 0 0">Consultas = consultas feitas sobre a cota dos planos (só planos com cota definida). Renovação = planos que venceram no período e renovaram, entre os pacientes de cada um (entre parênteses, quantos venceram). Só o admin vê.</p>
    </section>

    <!-- 6. Comercial (em breve) -->
    <section class="panel xp xp-5 xsoon" aria-labelledby="h-lead">
        <div class="xp-head"><h3 id="h-lead"><?= icon('flag', 20) ?> Comercial · Leads</h3><span class="tag">Em breve</span></div>
        <p class="muted small">A aba <strong>Leads</strong> (Instagram, WhatsApp e indicação) é a próxima atualização. Quando existir, o funil e a taxa de conversão aparecem aqui, com dados reais:</p>
        <ol class="ghost-funnel" aria-hidden="true">
            <li style="width:100%">Recebidos</li><li style="width:82%">Em atendimento</li><li style="width:64%">Em negociação</li><li style="width:46%">Convertidos</li><li style="width:30%">Perdidos</li>
        </ol>
    </section>

    <!-- 7. Alertas -->
    <section class="panel xp xp-7" aria-labelledby="h-al">
        <div class="xp-head"><h3 id="h-al"><?= icon('shield', 20) ?> Alertas e acompanhamento</h3><a class="tiny" href="<?= e(url('/admin?aba=operacao')) ?>">Ver operação →</a></div>
        <?php
        $items = [
            ['bad',  $al['d7'],      'pacientes perto do vencimento (até 7 dias)', url('/admin/pacientes?v=d7')],
            ['bad',  $al['expired'], 'pacientes com plano vencido', url('/admin/pacientes?v=expired')],
            ['bad',  $al['late'],    'parcelas em atraso (inadimplência)', url('/admin/financeiro?f=late')],
            ['warn', $al['noresp'],  'pacientes ativos sem responsável (sem acompanhamento)', url('/admin/pacientes?st=active&r=none')],
            ['warn', $al['away'],    'pacientes sem abrir o app há 7 dias ou mais', url('/admin/pacientes?f=away')],
            ['info', $al['risk'],    'renovações em risco (vencem em até ' . (int) $x['yellow'] . ' dias e estão sumidos)', url('/admin?aba=operacao')],
            ['info', $al['birthdays'], 'aniversariantes hoje', url('/admin/aniversarios?q=hoje')],
        ];
        ?>
        <div class="xalerts">
            <ul class="xal">
                <?php foreach ($items as [$sev, $n, $txt, $href]): ?>
                    <li><a href="<?= e($href) ?>"><i class="dotsev <?= $n > 0 ? e($sev) : 'zero' ?>"></i><b class="num"><?= (int) $n ?></b><span><?= e($txt) ?></span></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="xside">
                <?php $ev = $x['nextEvent']; ?>
                <div class="xmini">
                    <span class="xlbl">Próxima prova</span>
                    <?php if ($ev): $dd = days_until($ev['event_date']); ?>
                        <a href="<?= e(url('/admin/pacientes/' . (int) $ev['id'])) ?>"><b><?= e(fmt_date($ev['event_date'])) ?> · <?= e($ev['event_name'] ?: 'Prova') ?></b></a>
                        <span class="small muted"><?= e($ev['name']) ?> · <?= $dd === 0 ? 'hoje' : ($dd === 1 ? 'amanhã' : 'em ' . (int) $dd . ' dias') ?></span>
                    <?php else: ?>
                        <span class="small muted">Nenhuma prova marcada.</span>
                    <?php endif; ?>
                </div>
                <a class="xmini" href="<?= e(url('/admin/moderacao')) ?>">
                    <span class="xlbl">Pendências de moderação</span>
                    <b class="<?= $al['reports'] > 0 ? 'bad' : '' ?>"><?= (int) $al['reports'] ?> <?= $al['reports'] === 1 ? 'denúncia aberta' : 'denúncias abertas' ?></b>
                    <span class="small muted"><?= (int) $al['redemptions'] ?> <?= $al['redemptions'] === 1 ? 'resgate pendente' : 'resgates pendentes' ?></span>
                </a>
            </div>
        </div>
    </section>
</div>
