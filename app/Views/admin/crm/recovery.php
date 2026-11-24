<?php
use App\Services\Leads;

$cur = $f + ['pp' => $perPage];
$defaults = ['ord' => 'next', 'pp' => 25];
$link = function (array $over = []) use ($cur, $defaults) {
    $m = array_filter(array_merge($cur, $over), fn($v, $k) => $v !== '' && $v !== 0 && $v !== null && !(isset($defaults[$k]) && $defaults[$k] === $v), ARRAY_FILTER_USE_BOTH);
    return url('/admin/crm/reativacao') . ($m ? '?' . http_build_query($m) : '');
};
$hasFilters = $f['q'] !== '' || $f['reason'] !== '' || $f['resp'] !== '' || $f['f'] !== '';
$from = $total > 0 ? $offset + 1 : 0;
$to = min($total, $offset + $perPage);
$pageLink = fn(int $n) => $link(['pg' => $n]);
$nums = array_values(array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], fn($n) => $n >= 1 && $n <= $pages)));
sort($nums);
$activeResp = array_values(array_filter($respChoices, fn($x) => $x['active']));
$quick = [
    '' => ['Todos os perdidos', $counts['all']], 'ready' => ['Prontos para contato', $counts['ready']], 'waiting' => ['Aguardando a data', $counts['waiting']],
    'unscheduled' => ['Sem reativação programada', $counts['unscheduled']], 'done' => ['Cadência concluída', $counts['done']],
    'noauth' => ['Sem autorização', $counts['noauth']], 'dnc' => ['Não contatar', $counts['dnc']],
];
$stateCls = ['ready' => 'fu-today', 'waiting' => 'fu-scheduled', 'unscheduled' => 'fu-none', 'done' => 'fu-dnc', 'noauth' => 'fu-none', 'dnc' => 'fu-dnc'];
?>
<div class="page-head ppage-head">
    <div>
        <h2>CRM</h2>
        <p class="muted small" style="margin:4px 0 0">Leads perdidos que podem ser recuperados. Ficam separados das oportunidades abertas.</p>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/crm/configurar')) ?>"><?= icon('gear', 18) ?> Cadência: <?= e(implode(', ', $days)) ?> dias</a>
</div>
<?php partial('crm-tabs', ['active' => 'recovery']); ?>

<div class="pcards lcards" role="list" aria-label="Indicadores da reativação">
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm/reativacao')) ?>">
        <span class="pic"><?= icon('users', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['all'] ?></span>
        <span class="plbl">Leads perdidos</span>
    </a>
    <div class="pcard" role="listitem" title="Perdidos que autorizaram novos contatos e não estão como Não contatar, com a cadência ainda em andamento">
        <span class="pic"><?= icon('refresh', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['eligible'] ?></span>
        <span class="plbl">Elegíveis para recuperação</span>
        <span class="trend flat">autorizaram novos contatos</span>
    </div>
    <a class="pcard" role="listitem" href="<?= e(url('/admin/crm/reativacao?f=ready')) ?>">
        <span class="pic"><?= icon('clock', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['ready'] ?></span>
        <span class="plbl">Prontos para contato</span>
        <span class="trend flat">a data da tentativa chegou</span>
    </a>
    <div class="pcard" role="listitem" title="Leads que já foram perdidos e hoje estão em aberto ou viraram paciente">
        <span class="pic"><?= icon('trophy', 22) ?></span>
        <span class="pbig num"><?= (int) $counts['recovered'] ?></span>
        <span class="plbl">Recuperados</span>
        <span class="trend flat">voltaram ao funil ou viraram paciente</span>
    </div>
</div>

<form class="pfilters pf-wide" method="get" action="<?= e(url('/admin/crm/reativacao')) ?>">
    <input type="hidden" name="f" value="<?= e($f['f']) ?>">
    <input type="hidden" name="pp" value="<?= (int) $perPage ?>">
    <div class="pf-row">
        <div class="pf-search">
            <span class="pf-ico"><?= icon('search', 18) ?></span>
            <label class="sr-only" for="q">Buscar</label>
            <input class="input" type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Nome, WhatsApp, e-mail ou Instagram">
        </div>
        <label class="sr-only" for="reason">Motivo da perda</label>
        <select id="reason" name="reason" data-autosubmit>
            <option value="">Motivo da perda</option>
            <?php foreach ($reasons as $rs): ?><option value="<?= (int) $rs['id'] ?>"<?= $f['reason'] === (string) $rs['id'] ? ' selected' : '' ?>><?= e($rs['name']) ?><?= $rs['active'] ? '' : ' (desativado)' ?></option><?php endforeach; ?>
        </select>
        <label class="sr-only" for="resp">Responsável</label>
        <select id="resp" name="resp" data-autosubmit>
            <option value="">Responsável</option>
            <option value="none"<?= $f['resp'] === 'none' ? ' selected' : '' ?>>Sem responsável</option>
            <?php foreach ($responsibles as $rp): ?><option value="<?= (int) $rp['id'] ?>"<?= $f['resp'] === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
        </select>
        <label class="sr-only" for="ord">Ordenar por</label>
        <select id="ord" name="ord" data-autosubmit>
            <?php foreach (Leads::lostSorts() as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['ord'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm pf-go" type="submit" aria-label="Buscar" title="Buscar"><?= icon('search', 18) ?><span class="pf-go-t">Buscar</span></button>
    </div>
</form>

<div class="chips quick" role="group" aria-label="Atalhos">
    <?php foreach ($quick as $k => [$label, $n]): ?>
        <a class="chip" href="<?= e($link(['f' => $k, 'pg' => ''])) ?>"<?= $f['f'] === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?> <span class="chip-n">(<?= (int) $n ?>)</span></a>
    <?php endforeach; ?>
    <?php if ($hasFilters): ?><a class="chip chip-clear" href="<?= e(url('/admin/crm/reativacao' . ($f['ord'] !== 'next' ? '?ord=' . urlencode($f['ord']) : ''))) ?>">Limpar filtros</a><?php endif; ?>
</div>

<?php if (!$list): ?>
    <div class="empty">
        <?php if ($hasFilters): ?><strong>Nenhum lead encontrado</strong>Ajuste a busca ou os filtros.
        <?php else: ?><strong>Nenhum lead perdido</strong>Quando você marcar um lead como perdido (na ficha dele), ele aparece aqui. Se ele autorizou novos contatos, a reativação é programada para <?= (int) $days[0] ?> dias depois.<?php endif; ?>
    </div>
<?php else: ?>
<div class="ptable ltable rtable">
    <form class="bulk" id="bulk" method="post" action="<?= e(url('/admin/crm/responsavel')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e(current_path()) ?>">
        <label class="bulk-all"><input type="checkbox" data-check-all aria-label="Marcar todos os leads desta página"> <span>Selecionar todos</span></label>
        <span class="small"><strong data-check-count>0</strong> marcados</span>
        <span class="bulk-sp"></span>
        <label class="sr-only" for="bulk_resp">Responsável</label>
        <select id="bulk_resp" name="responsible_id">
            <option value="">Ações em lote: atribuir a…</option>
            <?php foreach ($activeResp as $rc): ?><option value="<?= (int) $rc['id'] ?>"><?= e($rc['name']) ?></option><?php endforeach; ?>
            <option value="0">Sem responsável (remover)</option>
        </select>
        <button class="btn btn-ghost btn-sm" type="submit">Aplicar</button>
        <?php if (!$activeResp): ?><a class="small" href="<?= e(url('/admin/responsaveis/novo')) ?>">Cadastre o Gabriel e o Comercial em Responsáveis primeiro</a><?php endif; ?>
    </form>
    <div class="tbl-wrap">
        <table class="tbl tbl-patients tbl-leads tbl-recovery">
            <colgroup><col class="k-chk"><col class="r-who"><col class="r-why"><col class="r-lost"><col class="r-rsp"><col class="r-state"><col class="r-next"><col class="l-act"></colgroup>
            <thead><tr>
                <th class="chk"><span class="sr-only">Marcar</span></th>
                <th>Lead</th><th>Motivo da perda</th><th>Perdido em</th><th>Resp.</th><th>Reativação</th><th>Próxima tentativa</th><th class="r">Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list as $r): $id = (int) $r['id']; $st = Leads::recoveryState($r);
                $contact = $r['phone'] ?: ($r['email'] ?: ($r['instagram'] ? '@' . $r['instagram'] : '')); $wa = Leads::recoveryEligible($r) ? Leads::waUrl($r) : null; ?>
                <tr>
                    <td class="chk"><input type="checkbox" form="bulk" name="ids[]" value="<?= $id ?>" data-check-item aria-label="Marcar <?= e($r['name']) ?>"></td>
                    <td class="c-who">
                        <a class="who" href="<?= e(url('/admin/crm/' . $id)) ?>"><span class="ini" aria-hidden="true"><?= e(initials($r['name'])) ?></span>
                            <span><span class="who-name"><?= e($r['name']) ?></span><br><span class="small muted who-mail" title="<?= e($contact) ?>"><?= e($contact) ?></span></span></a>
                    </td>
                    <td data-label="Motivo da perda"><?= $r['reason_name'] ? e($r['reason_name']) : '<span class="muted">-</span>' ?><?php if ($r['lost_note']): ?><br><span class="small muted" title="<?= e($r['lost_note']) ?>"><?= e(mb_strimwidth((string) $r['lost_note'], 0, 40, '…')) ?></span><?php endif; ?></td>
                    <td data-label="Perdido em"><?= e(fmt_date($r['lost_at'])) ?><br><span class="small muted"><?= e(Leads::agoText($r['lost_at'])) ?></span></td>
                    <td data-label="Responsável"<?= $r['responsible_name'] ? '' : ' class="c-empty"' ?>><?php if ($r['responsible_name']): ?><span class="resp" title="<?= e($r['responsible_name']) ?>"><span class="ini ini-sm" aria-hidden="true"><?= e(initials($r['responsible_name'])) ?></span><span class="resp-name"><?= e($r['responsible_name']) ?></span></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                    <td data-label="Reativação" class="c-state"><span class="fu <?= e($stateCls[$st['key']] ?? 'fu-none') ?>"><?= e($st['label']) ?></span></td>
                    <td data-label="Próxima tentativa" class="c-fu"><?php if (in_array($st['key'], ['ready', 'waiting'], true)): $nx = Leads::nextInfo($r['next_on']); ?>
                            <span class="nx nx-<?= e($st['key'] === 'ready' ? 'today' : $nx['cls']) ?>" title="<?= e(fmt_date($r['next_on'])) ?>"><?= e($st['key'] === 'ready' && $nx['days'] !== 0 ? 'pronto desde ' . substr(fmt_date($r['next_on']), 0, 5) : $nx['text']) ?></span><span class="small muted"> · <?= e(substr(fmt_date($r['next_on']), 0, 5)) ?></span>
                            <br><span class="small muted">tentativa <?= (int) $st['step'] ?> de <?= (int) $st['steps'] ?><?= $r['next_responsible_name'] ? ' · tarefa de ' . e(first_name($r['next_responsible_name'])) : '' ?></span>
                        <?php elseif ($st['key'] === 'done'): ?><span class="muted">concluída</span>
                        <?php else: ?><span class="muted">-</span><?php endif; ?></td>
                    <td class="r c-act">
                        <div class="row-actions">
                            <a class="ra" href="<?= e(url('/admin/crm/' . $id)) ?>" title="Abrir o lead" aria-label="Abrir <?= e($r['name']) ?>"><?= icon('eye', 18) ?></a>
                            <?php if ($wa): ?><a class="ra" href="<?= e($wa) ?>" target="_blank" rel="noopener" title="Abrir o WhatsApp com a mensagem pronta" aria-label="WhatsApp de <?= e($r['name']) ?>"><?= icon('chat', 18) ?></a><?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php partial('crm-pager', ['total' => $total, 'from' => $from, 'to' => $to, 'page' => $page, 'pages' => $pages, 'nums' => $nums, 'perPage' => $perPage, 'pageLink' => $pageLink, 'cur' => $cur, 'defaults' => $defaults, 'action' => url('/admin/crm/reativacao'), 'one' => 'lead perdido', 'many' => 'leads perdidos']); ?>
</div>
<?php endif; ?>
