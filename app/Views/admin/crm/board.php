<?php
use App\Services\Leads;

/** Funil (Kanban) do CRM: os mesmos leads em aberto da Lista, em colunas por etapa. */
$link = function (array $over = []) use ($f) {
    $m = array_filter(array_merge($f, $over), fn($v) => $v !== '' && $v !== null);
    return url('/admin/crm/quadro') . ($m ? '?' . http_build_query($m) : '');
};
$hasFilters = $f['q'] !== '' || $f['temp'] !== '' || $f['source'] !== '' || $f['resp'] !== '' || $f['f'] !== '';
$mny = fn(float $v, string $cur = 'BRL') => money($v, fmod($v, 1.0) == 0.0 ? 0 : 2, $cur);
$quick = [
    '' => ['Em aberto', $counts['open']], 'late' => ['Retorno atrasado', $counts['late']], 'today' => ['Retorno hoje', $counts['today']],
    'hot' => ['Quentes', $counts['hot']], 'noresp' => ['Sem responsável', $counts['noresp']],
];
$backHere = current_path();
?>
<div class="page-head ppage-head">
    <div>
        <h2>CRM</h2>
        <p class="muted small" style="margin:4px 0 0">Quem ainda não é paciente, em colunas por etapa do funil. Arraste o cartão para a etapa em que o lead está agora. Para ver por prazo de retorno, use a aba Follow-up.</p>
    </div>
    <a class="btn btn-sm" href="<?= e(url('/admin/crm/novo')) ?>"><?= icon('plus', 18) ?> Novo lead</a>
</div>
<?php partial('crm-tabs', ['active' => 'board']); ?>

<form class="pfilters kb-filters" method="get" action="<?= e(url('/admin/crm/quadro')) ?>">
    <input type="hidden" name="f" value="<?= e($f['f']) ?>">
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
        <label class="sr-only" for="resp">Responsável</label>
        <select id="resp" name="resp" data-autosubmit>
            <option value="">Responsável</option>
            <option value="none"<?= $f['resp'] === 'none' ? ' selected' : '' ?>>Sem responsável</option>
            <?php foreach ($respChoices as $rp): ?><option value="<?= (int) $rp['id'] ?>"<?= $f['resp'] === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm pf-go" type="submit" aria-label="Buscar" title="Buscar"><?= icon('search', 18) ?><span class="pf-go-t">Buscar</span></button>
    </div>
</form>

<div class="chips quick" role="group" aria-label="Atalhos">
    <?php foreach ($quick as $k => [$label, $n]): ?>
        <a class="chip" href="<?= e($link(['f' => $k])) ?>"<?= $f['f'] === $k ? ' aria-current="true"' : '' ?>><?= e($label) ?> <span class="chip-n">(<?= (int) $n ?>)</span></a>
    <?php endforeach; ?>
    <?php if ($hasFilters): ?><a class="chip chip-clear" href="<?= e(url('/admin/crm/quadro')) ?>">Limpar filtros</a><?php endif; ?>
</div>
<div class="kb-bar">
    <p class="kb-sum small muted">
        <strong class="kb-sum-n"><?= (int) $total ?></strong> <?= $total === 1 ? 'lead no funil' : 'leads no funil' ?><?= $hasFilters ? ' (com os filtros)' : '' ?>
        · <a href="<?= e(url('/admin/crm?f=pot')) ?>" title="Soma do valor do plano de interesse dos leads nas etapas marcadas em Configurar">Receita potencial em negociação: <strong data-pot-total data-total="<?= e((string) $ind['potential']) ?>"><?= e($mny($ind['potential'])) ?></strong></a>
        <span data-pot-unpriced-wrap<?= $ind['unpriced'] > 0 ? '' : ' hidden' ?>> · <span data-pot-unpriced data-n="<?= (int) $ind['unpriced'] ?>"><?= (int) $ind['unpriced'] ?></span> sem plano de interesse (fora da soma)</span>
    </p>
    <?php /* só aparece (via JavaScript) quando as colunas não cabem na tela: evita arrastar a barra de rolagem */ ?>
    <div class="kb-nav" data-board-nav hidden>
        <button class="kb-arrow" type="button" data-board-go="-1" aria-label="Ver as colunas anteriores" title="Colunas anteriores">‹</button>
        <button class="kb-arrow" type="button" data-board-go="1" aria-label="Ver as próximas colunas" title="Próximas colunas">›</button>
    </div>
</div>

<?php if (!$columns): ?>
    <div class="empty"><strong>Nenhuma etapa ativa</strong>Ative ou crie as etapas do funil em Configurar.</div>
<?php else: ?>
<div class="kboard" data-board data-csrf="<?= e(csrf_token()) ?>" role="list" aria-label="Funil de leads por etapa">
    <?php foreach ($columns as $col): $st = $col['stage']; $sid = (int) $st['id']; ?>
    <section class="kcol" role="listitem" data-stage="<?= $sid ?>" data-name="<?= e($st['name']) ?>" data-active="<?= (int) $st['active'] ?>" aria-labelledby="kh<?= $sid ?>">
        <header class="kcol-h">
            <h3 id="kh<?= $sid ?>"><?= e($st['name']) ?><?= (int) $st['active'] ? '' : ' <span class="small muted">(desativada)</span>' ?></h3>
            <span class="kcol-n" data-count-wrap title="<?= (int) $col['total'] ?> <?= (int) $col['total'] === 1 ? 'lead' : 'leads' ?> nesta etapa"><span data-count><?= (int) $col['total'] ?></span> <span data-unit><?= (int) $col['total'] === 1 ? 'lead' : 'leads' ?></span></span>
        </header>
        <?php if (!empty($st['potential'])): ?>
            <p class="kcol-sum small" data-sum="<?= e((string) $col['potential']) ?>" data-unpriced="<?= (int) $col['unpriced'] ?>" title="Soma do plano de interesse dos leads desta coluna (etapa marcada como receita potencial)"><?= e($mny($col['potential'])) ?><?= $col['unpriced'] > 0 ? ' · ' . (int) $col['unpriced'] . ' sem plano' : '' ?></p>
        <?php endif; ?>
        <div class="kcol-body">
            <?php foreach ($col['cards'] as $r): $id = (int) $r['id']; $fu = Leads::followUp($r); $wa = Leads::waUrl($r);
                $int = implode(' · ', array_filter([$r['sport_name'], $r['plan_name']])); ?>
            <article class="kcard" draggable="true" data-lead="<?= $id ?>" data-name="<?= e($r['name']) ?>" data-price="<?= $r['plan_price'] !== null ? e((string) (float) $r['plan_price']) : '' ?>">
                <div class="kc-top">
                    <a class="kc-name" href="<?= e(url('/admin/crm/' . $id)) ?>"><?= e($r['name']) ?></a>
                    <span class="temp temp-<?= e($r['temperature']) ?>"><?= e(Leads::TEMPS[$r['temperature']] ?? $r['temperature']) ?></span>
                </div>
                <?php if ($int !== '' || $r['source_name']): ?>
                <p class="kc-meta small muted"><?= e(implode(' · ', array_filter([$int, $r['source_name'] ? 'via ' . $r['source_name'] : '']))) ?></p>
                <?php endif; ?>
                <p class="kc-fu">
                    <?php if ($fu['state'] === 'dnc'): ?><span class="fu fu-dnc">Não contatar</span>
                    <?php elseif ($fu['state'] === 'none'): ?><span class="fu fu-none">Sem retorno programado</span>
                    <?php else: ?><span class="fu fu-<?= e($fu['state']) ?>"><?= e($fu['state'] === 'late' ? 'Retorno ' . $fu['text'] : ($fu['state'] === 'today' ? 'Retorno hoje' : 'Retorno ' . $fu['text'])) ?></span>
                        <span class="small muted"><?= e(substr(fmt_date($r['next_on']), 0, 5)) ?></span><?php endif; ?>
                </p>
                <div class="kc-foot">
                    <?php if ($r['responsible_name']): ?><span class="resp" title="Responsável: <?= e($r['responsible_name']) ?>"><span class="ini ini-sm" aria-hidden="true"><?= e(initials($r['responsible_name'])) ?></span><span class="sr-only">Responsável: <?= e($r['responsible_name']) ?></span></span>
                    <?php else: ?><span class="kc-nores small" title="Ninguém é dono do retorno deste lead">Sem responsável</span><?php endif; ?>
                    <span class="small muted kc-last"><?= $r['last_contact_at'] ? 'Contato ' . e(Leads::agoText($r['last_contact_at'])) : 'Nunca contatado' ?></span>
                    <?php if ($wa): ?><a class="ra kc-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener" title="Abrir o WhatsApp com a mensagem pronta" aria-label="WhatsApp de <?= e($r['name']) ?>"><?= icon('chat', 18) ?></a><?php endif; ?>
                </div>
                <form class="kc-move" method="post" action="<?= e(url('/admin/crm/' . $id . '/etapa')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="<?= e($backHere) ?>">
                    <label class="sr-only" for="mv<?= $id ?>">Mover <?= e($r['name']) ?> para</label>
                    <select id="mv<?= $id ?>" name="stage_id">
                        <option value="">Mover para…</option>
                        <?php foreach ($moveStages as $ms): if ((int) $ms['id'] === $sid) { continue; } ?><option value="<?= (int) $ms['id'] ?>"><?= e($ms['name']) ?></option><?php endforeach; ?>
                        <optgroup label="Finalizar">
                            <option value="won">Converter em paciente…</option>
                            <option value="lost">Marcar como perdido…</option>
                        </optgroup>
                    </select>
                    <button class="btn btn-ghost btn-sm" type="submit">Mover</button>
                </form>
            </article>
            <?php endforeach; ?>
            <p class="kcol-empty small muted"<?= $col['cards'] ? ' hidden' : '' ?> data-empty>Nenhum lead nesta etapa.<?= $hasFilters ? '' : ' Arraste um cartão para cá.' ?></p>
            <?php if ($col['more'] > 0): ?>
            <a class="kcol-more small" href="<?= e(url('/admin/crm?stage=' . $sid . ($f['q'] !== '' ? '&q=' . urlencode($f['q']) : '') . ($f['temp'] !== '' ? '&temp=' . urlencode($f['temp']) : '') . ($f['source'] !== '' ? '&source=' . urlencode($f['source']) : '') . ($f['resp'] !== '' ? '&resp=' . urlencode($f['resp']) : '') . ($f['f'] !== '' ? '&f=' . urlencode($f['f']) : ''))) ?>">+ <?= (int) $col['more'] ?> <?= $col['more'] === 1 ? 'lead' : 'leads' ?> na Lista</a>
            <?php endif; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>
<div class="kfinal" data-final hidden aria-hidden="true">
    <span class="kfinal-t small">Solte aqui para finalizar:</span>
    <div class="kfinal-zone kfinal-won" data-final-to="won"><?= icon('trophy', 20) ?> Converter em paciente</div>
    <div class="kfinal-zone kfinal-lost" data-final-to="lost"><?= icon('flag', 20) ?> Marcar como perdido</div>
</div>
<?php endif; ?>
