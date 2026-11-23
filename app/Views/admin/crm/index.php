<?php
use App\Services\Leads;

$cur = $f + ['pp' => $perPage];
$defaults = ['ord' => 'new', 'pp' => 25];
$link = function (array $over = []) use ($cur, $defaults) {
    $m = array_filter(array_merge($cur, $over), fn($v, $k) => $v !== '' && $v !== 0 && $v !== null && !(isset($defaults[$k]) && $defaults[$k] === $v), ARRAY_FILTER_USE_BOTH);
    return url('/admin/crm') . ($m ? '?' . http_build_query($m) : '');
};
$hasFilters = $f['q'] !== '' || $f['temp'] !== '' || $f['source'] !== '' || $f['stage'] !== '' || $f['sport'] !== '' || $f['plan'] !== '' || $f['resp'] !== '' || $f['p'] !== '' || $f['f'] !== '';
$moreOn = ($f['resp'] !== '' || $f['sport'] !== '' || $f['plan'] !== '' || $f['p'] !== '' || $f['ord'] !== 'new');
$from = $total > 0 ? $offset + 1 : 0;
$to = min($total, $offset + $perPage);
$pageLink = fn(int $n) => $link(['pg' => $n]);
$nums = array_values(array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], fn($n) => $n >= 1 && $n <= $pages)));
sort($nums);
$quick = ['' => ['Em aberto', $counts['open']], 'today' => ['Retorno hoje', $counts['today']], 'late' => ['Atrasados', $counts['late']], 'hot' => ['Quentes', $counts['hot']], 'dnc' => ['Não contatar', $counts['dnc']]];
?>
<div class="page-head ppage-head">
    <div>
        <h2>CRM</h2>
        <p class="muted small" style="margin:4px 0 0">Quem ainda não é paciente: de onde veio, em que etapa está e quando falar de novo.</p>
    </div>
    <a class="btn btn-sm" href="<?= e(url('/admin/crm/novo')) ?>"><?= icon('plus', 18) ?> Novo lead</a>
</div>
<?php partial('crm-tabs', ['active' => 'leads']); ?>

<div class="pcards lcards" role="list" aria-label="Indicadores">
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm')) ?>">
        <span class="pic"><?= icon('users', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['open'] ?></span>
        <span class="plbl">Leads em aberto</span>
    </a>
    <div class="pcard" role="listitem">
        <span class="pic"><?= icon('calendar', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['new_month'] ?></span>
        <span class="plbl">Novos no mês</span>
    </div>
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm?f=hot')) ?>">
        <span class="pic"><?= icon('star', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['hot'] ?></span>
        <span class="plbl">Leads quentes</span>
    </a>
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm?f=late')) ?>">
        <span class="pic"><?= icon('clock', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['late'] ?></span>
        <span class="plbl">Retornos atrasados</span>
        <span class="trend flat"><?= (int) $counts['today'] > 0 ? (int) $counts['today'] . ' para hoje' : 'nenhum para hoje' ?></span>
    </a>
</div>

<form class="pfilters" method="get" action="<?= e(url('/admin/crm')) ?>">
    <input type="hidden" name="f" value="<?= e($f['f']) ?>">
    <input type="hidden" name="pp" value="<?= (int) $perPage ?>">
    <div class="pf-row">
        <div class="pf-search">
            <span class="pf-ico"><?= icon('search', 18) ?></span>
            <label class="sr-only" for="q">Buscar</label>
            <input class="input" type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Nome, WhatsApp, e-mail ou Instagram">
        </div>
        <label class="sr-only" for="temp">Temperatura</label>
        <select id="temp" name="temp" data-autosubmit>
            <option value="">Temperatura</option>
            <?php foreach (Leads::TEMPS as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['temp'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <label class="sr-only" for="source">Origem</label>
        <select id="source" name="source" data-autosubmit>
            <option value="">Origem</option>
            <?php foreach ($sources as $s): ?><option value="<?= (int) $s['id'] ?>"<?= $f['source'] === (string) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?><?= $s['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
        </select>
        <label class="sr-only" for="stage">Etapa</label>
        <select id="stage" name="stage" data-autosubmit>
            <option value="">Etapa</option>
            <?php foreach ($stages as $st): if ($st['kind'] !== 'open') { continue; } ?><option value="<?= (int) $st['id'] ?>"<?= $f['stage'] === (string) $st['id'] ? ' selected' : '' ?>><?= e($st['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm pf-go" type="submit" aria-label="Buscar" title="Buscar"><?= icon('search', 18) ?><span class="pf-go-t">Buscar</span></button>
    </div>
    <details class="pf-more"<?= $moreOn ? ' open' : '' ?>>
        <summary><?= icon('filter', 16) ?> Mais filtros<?= $moreOn ? ' <span class="pf-dot" title="Há filtros ligados aqui dentro"></span>' : '' ?></summary>
        <div class="pf-more-grid lf-more">
            <div>
                <label for="resp">Responsável</label>
                <select id="resp" name="resp" data-autosubmit>
                    <option value="">Todos</option>
                    <option value="none"<?= $f['resp'] === 'none' ? ' selected' : '' ?>>Sem responsável</option>
                    <?php foreach ($responsibles as $rp): ?><option value="<?= (int) $rp['id'] ?>"<?= $f['resp'] === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="sport">Modalidade</label>
                <select id="sport" name="sport" data-autosubmit>
                    <option value="">Todas</option>
                    <?php foreach ($sports as $sp): ?><option value="<?= (int) $sp['id'] ?>"<?= $f['sport'] === (string) $sp['id'] ? ' selected' : '' ?>><?= e($sp['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="plan">Plano de interesse</label>
                <select id="plan" name="plan" data-autosubmit>
                    <option value="">Todos</option>
                    <?php foreach ($plans as $pl): ?><option value="<?= (int) $pl['id'] ?>"<?= $f['plan'] === (string) $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="p">Data de entrada</label>
                <select id="p" name="p" data-autosubmit>
                    <?php foreach (Leads::periods() as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['p'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="ord">Ordenar por</label>
                <select id="ord" name="ord" data-autosubmit>
                    <?php foreach (Leads::sorts() as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['ord'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </details>
</form>

<div class="chips quick" role="group" aria-label="Atalhos">
    <?php foreach ($quick as $k => [$label, $n]): ?>
        <a class="chip" href="<?= e($link(['f' => $k, 'pg' => ''])) ?>"<?= $f['f'] === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?> <span class="chip-n">(<?= (int) $n ?>)</span></a>
    <?php endforeach; ?>
    <?php if ($hasFilters): ?><a class="chip chip-clear" href="<?= e(url('/admin/crm' . ($f['ord'] !== 'new' ? '?ord=' . urlencode($f['ord']) : ''))) ?>">Limpar filtros</a><?php endif; ?>
</div>

<?php if (!$list): ?>
    <div class="empty">
        <?php if ($hasFilters): ?><strong>Nenhum lead encontrado</strong>Ajuste a busca ou os filtros.
        <?php else: ?><strong>Nenhum lead ainda</strong>Cadastre o primeiro com o botão "Novo lead". Dá para registrar em 10 segundos: nome, WhatsApp e origem.<?php endif; ?>
    </div>
<?php else: ?>
<div class="ptable ltable">
    <div class="tbl-wrap">
        <table class="tbl tbl-patients tbl-leads">
            <colgroup><col class="l-who"><col class="l-temp"><col class="l-src"><col class="l-int"><col class="l-stg"><col class="l-rsp"><col class="l-lst"><col class="l-nxt"><col class="l-act"></colgroup>
            <thead><tr>
                <th>Lead</th><th title="Temperatura">Temp.</th><th>Origem</th><th>Interesse</th><th>Etapa</th><th>Resp.</th><th>Último contato</th><th>Próximo retorno</th><th class="r">Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list as $r): $id = (int) $r['id']; $nx = Leads::nextInfo($r['next_on']); $wa = Leads::waUrl($r);
                $contact = $r['phone'] ?: ($r['email'] ?: ($r['instagram'] ? '@' . $r['instagram'] : '')); ?>
                <tr>
                    <td class="c-who">
                        <a class="who" href="<?= e(url('/admin/crm/' . $id)) ?>"><span class="ini" aria-hidden="true"><?= e(initials($r['name'])) ?></span>
                            <span><span class="who-name"><?= e($r['name']) ?></span><br><span class="small muted who-mail" title="<?= e($contact) ?>"><?= e($contact) ?></span></span></a>
                    </td>
                    <td data-label="Temperatura"><span class="temp temp-<?= e($r['temperature']) ?>"><?= e(Leads::TEMPS[$r['temperature']] ?? $r['temperature']) ?></span></td>
                    <td data-label="Origem"><?= $r['source_name'] ? e($r['source_name']) : '<span class="muted">-</span>' ?></td>
                    <?php $int = array_filter([$r['sport_name'], $r['plan_name']]); ?>
                    <td data-label="Interesse"<?= $int ? '' : ' class="c-empty"' ?>><?= $int ? e(implode(' · ', $int)) : '<span class="muted">-</span>' ?></td>
                    <td data-label="Etapa"><span class="plan-pill stage-pill"><?= e($r['stage_name']) ?></span></td>
                    <td data-label="Responsável"<?= $r['responsible_name'] ? '' : ' class="c-empty"' ?>><?php if ($r['responsible_name']): ?><span class="resp" title="<?= e($r['responsible_name']) ?>"><span class="ini ini-sm" aria-hidden="true"><?= e(initials($r['responsible_name'])) ?></span><span class="resp-name"><?= e($r['responsible_name']) ?></span></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                    <td data-label="Último contato"><?php if ($r['last_contact_at']): ?><?= e(fmt_date($r['last_contact_at'])) ?><br><span class="small muted"><?= e(Leads::agoText($r['last_contact_at'])) ?></span><?php else: ?><span class="muted">Nunca</span><?php endif; ?></td>
                    <td data-label="Próximo retorno"><?php if ((int) $r['do_not_contact'] === 1): ?><span class="st st-off">Não contatar</span>
                        <?php elseif ($r['next_on']): ?><span class="nx nx-<?= e($nx['cls']) ?>" title="<?= e(fmt_date($r['next_on'])) ?>"><?= e($nx['text']) ?></span><?php if ($r['next_type'] && isset(Leads::ACTIONS[$r['next_type']])): ?><br><span class="small muted"><?= e(Leads::ACTIONS[$r['next_type']]) ?></span><?php endif; ?>
                        <?php else: ?><span class="muted">sem retorno</span><?php endif; ?></td>
                    <td class="r c-act">
                        <div class="row-actions">
                            <a class="ra" href="<?= e(url('/admin/crm/' . $id)) ?>" title="Abrir o lead" aria-label="Abrir <?= e($r['name']) ?>"><?= icon('eye', 18) ?></a>
                            <a class="ra" href="<?= e(url('/admin/crm/' . $id . '/editar')) ?>" title="Editar os dados" aria-label="Editar <?= e($r['name']) ?>"><?= icon('pencil', 18) ?></a>
                            <?php if ($wa): ?><a class="ra" href="<?= e($wa) ?>" target="_blank" rel="noopener" title="Abrir o WhatsApp com a mensagem pronta" aria-label="WhatsApp de <?= e($r['name']) ?>"><?= icon('chat', 18) ?></a><?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="pager">
        <p class="small muted pager-info">Mostrando <strong><?= (int) $from ?>–<?= (int) $to ?></strong> de <strong><?= (int) $total ?></strong> <?= $total === 1 ? 'lead' : 'leads' ?></p>
        <?php if ($pages > 1): ?>
        <nav class="pager-nav" aria-label="Páginas">
            <?php if ($page > 1): ?><a class="pg" href="<?= e($pageLink($page - 1)) ?>" aria-label="Página anterior"><?= icon('left', 16) ?></a><?php else: ?><span class="pg off" aria-hidden="true"><?= icon('left', 16) ?></span><?php endif; ?>
            <?php $prev = 0; foreach ($nums as $n): if ($n - $prev > 1): ?><span class="pg gap" aria-hidden="true">…</span><?php endif; ?>
                <a class="pg<?= $n === $page ? ' on' : '' ?>" href="<?= e($pageLink($n)) ?>"<?= $n === $page ? ' aria-current="page"' : '' ?> aria-label="Página <?= $n ?>"><?= $n ?></a>
            <?php $prev = $n; endforeach; ?>
            <?php if ($page < $pages): ?><a class="pg" href="<?= e($pageLink($page + 1)) ?>" aria-label="Próxima página"><?= icon('right', 16) ?></a><?php else: ?><span class="pg off" aria-hidden="true"><?= icon('right', 16) ?></span><?php endif; ?>
        </nav>
        <?php endif; ?>
        <form class="pager-pp" method="get" action="<?= e(url('/admin/crm')) ?>">
            <?php foreach (array_diff_key($cur, ['pp' => 1]) as $k => $v): if ($v === '' || $v === 0 || $v === null || (isset($defaults[$k]) && $defaults[$k] === $v)) { continue; } ?>
                <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
            <?php endforeach; ?>
            <label for="pp">Mostrar:</label>
            <select id="pp" name="pp" data-autosubmit>
                <?php foreach ([10, 25, 50, 100] as $n): ?><option value="<?= $n ?>"<?= $perPage === $n ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>
