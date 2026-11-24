<?php
use App\Services\Leads;

/**
 * Follow-up (09.4): os MESMOS leads do Funil e da Lista, em colunas por PRAZO do próximo retorno.
 * Arrastar para "Hoje" ou "Amanhã" reagenda; outras datas pelo seletor do cartão ou pela ficha. Tarefas de reativação aparecem com a etiqueta "Reativação".
 */
$link = function (array $over = []) use ($f) {
    $m = array_filter(array_merge($f, $over), fn($v) => $v !== '' && $v !== null);
    return url('/admin/crm/follow-up') . ($m ? '?' . http_build_query($m) : '');
};
$hasFilters = $f['q'] !== '' || $f['resp'] !== '' || $f['c'] !== '';
$by = array_column($columns, 'total', 'key');
$recSteps = count(Leads::recoveryDays());
$backHere = current_path();
$empty = [
    'late' => 'Nenhum retorno atrasado.',
    'today' => 'Nenhum retorno para hoje. Arraste um cartão para cá para marcar para hoje.',
    'tomorrow' => 'Nenhum retorno para amanhã. Arraste um cartão para cá para marcar para amanhã.',
    'week' => 'Nenhum retorno nesta faixa.',
    'later' => 'Nenhum retorno nesta faixa.',
    'none' => 'Todo lead em aberto tem retorno programado.',
];
$hint = [
    'late' => 'A data já passou',
    'today' => 'Solte aqui para hoje',
    'tomorrow' => 'Solte aqui para amanhã',
    'week' => 'De 2 a 7 dias',
    'later' => 'Daqui a mais de 7 dias',
    'none' => 'Sem data marcada',
];
?>
<div class="page-head ppage-head">
    <div>
        <h2>CRM</h2>
        <p class="muted small" style="margin:4px 0 0">Quem precisa de retorno, em colunas por prazo. Arraste o cartão para <strong>Hoje</strong> ou <strong>Amanhã</strong> para reagendar; para outra data use "Reagendar para…" no cartão.</p>
    </div>
    <a class="btn btn-sm" href="<?= e(url('/admin/crm/novo')) ?>"><?= icon('plus', 18) ?> Novo lead</a>
</div>
<?php partial('crm-tabs', ['active' => 'follow']); ?>

<form class="pfilters kb-filters" method="get" action="<?= e(url('/admin/crm/follow-up')) ?>">
    <input type="hidden" name="c" value="<?= e($f['c']) ?>">
    <div class="pf-row">
        <div class="pf-search">
            <span class="pf-ico"><?= icon('search', 18) ?></span>
            <label class="sr-only" for="q">Buscar</label>
            <input class="input" type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Nome, WhatsApp, e-mail ou Instagram">
        </div>
        <label class="sr-only" for="resp">Retorno de</label>
        <select id="resp" name="resp" data-autosubmit>
            <option value="">Retorno de</option>
            <option value="none"<?= $f['resp'] === 'none' ? ' selected' : '' ?>>Ninguém definido</option>
            <?php foreach ($respChoices as $rp): ?><option value="<?= (int) $rp['id'] ?>"<?= $f['resp'] === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm pf-go" type="submit" aria-label="Buscar" title="Buscar"><?= icon('search', 18) ?><span class="pf-go-t">Buscar</span></button>
    </div>
</form>

<div class="chips quick" role="group" aria-label="Atalhos">
    <?php foreach (Leads::FOLLOW_CHIPS as $k => $label): ?>
        <a class="chip" href="<?= e($link(['c' => $k])) ?>"<?= $f['c'] === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?> <span class="chip-n">(<?= (int) ($chipCounts[$k] ?? 0) ?>)</span></a>
    <?php endforeach; ?>
    <?php if ($hasFilters): ?><a class="chip chip-clear" href="<?= e(url('/admin/crm/follow-up')) ?>">Limpar filtros</a><?php endif; ?>
</div>
<div class="kb-bar">
    <p class="kb-sum small muted">
        <strong class="kb-sum-n"><?= (int) $total ?></strong> <?= $total === 1 ? 'retorno' : 'retornos' ?> no follow-up<?= $hasFilters ? ' (com os filtros)' : '' ?>
        <?php if (($by['late'] ?? 0) > 0): ?> · <strong class="kb-late"><?= (int) $by['late'] ?> <?= $by['late'] === 1 ? 'atrasado' : 'atrasados' ?></strong><?php endif; ?>
        <?php if (($by['none'] ?? 0) > 0): ?> · <?= (int) $by['none'] ?> <?= $by['none'] === 1 ? 'lead sem retorno programado' : 'leads sem retorno programado' ?><?php endif; ?>
    </p>
    <?php /* só aparece (via JavaScript) quando as colunas não cabem na tela */ ?>
    <div class="kb-nav" data-board-nav hidden>
        <button class="kb-arrow" type="button" data-board-go="-1" aria-label="Ver as colunas anteriores" title="Colunas anteriores">‹</button>
        <button class="kb-arrow" type="button" data-board-go="1" aria-label="Ver as próximas colunas" title="Próximas colunas">›</button>
    </div>
</div>

<div class="kboard kboard-follow" data-board data-follow data-csrf="<?= e(csrf_token()) ?>" role="list" aria-label="Follow-up por prazo do retorno">
    <?php foreach ($columns as $col): $key = $col['key'];
        $drop = $key === 'today' ? '0' : ($key === 'tomorrow' ? '1' : null); ?>
    <section class="kcol kcol-<?= e($key) ?>" role="listitem" data-bucket="<?= e($key) ?>"<?= $drop !== null ? ' data-drop="' . $drop . '"' : '' ?> data-where="neste prazo" aria-labelledby="fh-<?= e($key) ?>">
        <header class="kcol-h">
            <h3 id="fh-<?= e($key) ?>"><?= e($col['label']) ?></h3>
            <span class="kcol-n" data-count-wrap title="<?= (int) $col['total'] ?> <?= (int) $col['total'] === 1 ? 'lead' : 'leads' ?> neste prazo"><span data-count><?= (int) $col['total'] ?></span> <span data-unit><?= (int) $col['total'] === 1 ? 'lead' : 'leads' ?></span></span>
        </header>
        <p class="kcol-sum kcol-hint small"><?= e($hint[$key]) ?></p>
        <div class="kcol-body">
            <?php foreach ($col['cards'] as $r): $id = (int) $r['id']; $fu = Leads::followUp($r); $wa = Leads::waUrl($r);
                $lost = $r['status'] === 'lost';
                $int = implode(' · ', array_filter([$r['sport_name'], $r['plan_name']]));
                $act = $r['next_on'] ? (Leads::ACTIONS[(string) $r['next_type']] ?? '') : '';
                $owner = $r['next_responsible_name'] ?: $r['responsible_name']; ?>
            <article class="kcard<?= $lost ? ' kcard-rec' : '' ?>" draggable="true" data-lead="<?= $id ?>" data-name="<?= e($r['name']) ?>">
                <div class="kc-top">
                    <a class="kc-name" href="<?= e(url('/admin/crm/' . $id)) ?>"><?= e($r['name']) ?></a>
                    <span class="temp temp-<?= e($r['temperature']) ?>"><?= e(Leads::TEMPS[$r['temperature']] ?? $r['temperature']) ?></span>
                </div>
                <p class="kc-meta small muted">
                    <?php if ($lost): ?><span class="kc-tag kc-tag-rec" title="Lead perdido que autorizou novos contatos: tentativa de reativação">Reativação · <?= min($recSteps, (int) $r['recovery_step'] + 1) ?> de <?= $recSteps ?></span> <?php endif; ?>
                    <?= e(implode(' · ', array_filter([$lost ? ($r['lost_reason_name'] ? 'Perdido: ' . $r['lost_reason_name'] : 'Perdido') : $r['stage_name'], $int, $r['source_name'] ? 'via ' . $r['source_name'] : '']))) ?>
                </p>
                <p class="kc-fu">
                    <?php if ($fu['state'] === 'none'): ?><span class="fu fu-none">Sem retorno programado</span>
                    <?php else: ?><span class="fu fu-<?= e($fu['state']) ?>"><?= e($fu['state'] === 'today' ? 'Retorno hoje' : 'Retorno ' . $fu['text']) ?></span><?php endif; ?>
                    <span class="small muted" data-fu-date><?= $r['next_on'] ? e(substr(fmt_date($r['next_on']), 0, 5)) : '' ?></span>
                    <?php if ($act !== ''): ?><span class="small kc-act" title="O que fazer neste retorno"><?= e($act) ?></span><?php endif; ?>
                </p>
                <div class="kc-foot">
                    <?php if ($owner): ?><span class="resp" title="Retorno de: <?= e($owner) ?>"><span class="ini ini-sm" aria-hidden="true"><?= e(initials($owner)) ?></span><span class="sr-only">Retorno de: <?= e($owner) ?></span></span>
                    <?php else: ?><span class="kc-nores small" title="Ninguém é dono do retorno deste lead">Sem responsável</span><?php endif; ?>
                    <span class="small muted kc-last"><?= $r['last_contact_at'] ? 'Contato ' . e(Leads::agoText($r['last_contact_at'])) : 'Nunca contatado' ?><?= ($r['last_result'] ?? '') === 'no_answer' ? ' · <strong class="kc-noans">sem resposta</strong>' : '' ?></span>
                    <?php if ($wa): ?><a class="ra kc-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener" title="Abrir o WhatsApp com a mensagem pronta<?= $act !== '' ? ' (' . e($act) . ')' : '' ?>" aria-label="WhatsApp de <?= e($r['name']) ?>"><?= icon('chat', 18) ?></a><?php endif; ?>
                </div>
                <form class="kc-move" method="post" action="<?= e(url('/admin/crm/' . $id . '/reagendar')) ?>" data-ficha="<?= e(url('/admin/crm/' . $id . '#retorno')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="<?= e($backHere) ?>">
                    <label class="sr-only" for="rs<?= $id ?>">Reagendar <?= e($r['name']) ?> para</label>
                    <select id="rs<?= $id ?>" name="days">
                        <option value="">Reagendar para…</option>
                        <?php foreach (Leads::RESCHEDULE as $d => $label): ?><option value="<?= (int) $d ?>"><?= e($label) ?></option><?php endforeach; ?>
                        <option value="date">Outra data…</option>
                    </select>
                    <button class="btn btn-ghost btn-sm" type="submit">Reagendar</button>
                </form>
            </article>
            <?php endforeach; ?>
            <p class="kcol-empty small muted"<?= $col['cards'] ? ' hidden' : '' ?> data-empty><?= e($hasFilters && $key !== 'late' && $key !== 'none' ? 'Nenhum lead nesta faixa com estes filtros.' : $empty[$key]) ?></p>
            <?php if ($col['more'] > 0):
                $label = '+ ' . (int) $col['more'] . ' ' . ($col['more'] === 1 ? 'lead' : 'leads') . ' na Lista';
                $listable = !in_array($f['c'], ['noanswer', 'never', 'recovery'], true);
                $qs = array_filter([
                    'f' => $key, 'q' => $f['q'], 'task' => $f['resp'], 'temp' => $f['c'] === 'hot' ? 'hot' : '',
                ], fn($v) => $v !== '');
                if ($listable): ?>
            <a class="kcol-more small" href="<?= e(url('/admin/crm?' . http_build_query($qs))) ?>"><?= e($label) ?></a>
            <?php else: ?>
            <p class="kcol-more small muted" title="Refine a busca para ver os outros leads desta faixa"><?= e('+ ' . (int) $col['more'] . ' ' . ($col['more'] === 1 ? 'lead' : 'leads') . ' (refine a busca)') ?></p>
            <?php endif; endif; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>
