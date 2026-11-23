<div class="page-head ppage-head">
    <div>
        <h2>CRM</h2>
        <p class="muted small" style="margin:4px 0 0">As listas que aparecem nos leads. Nada é apagado: desative para esconder.</p>
    </div>
</div>
<?php partial('crm-tabs', ['active' => 'config']); ?>

<div class="cfg-cols">
<section class="panel">
    <h3>Origens</h3>
    <p class="muted small" style="margin-top:-6px">De onde o lead veio. Os leads já cadastrados mantêm a origem, mesmo se ela for desativada.</p>
    <?php foreach ($sources as $s): ?>
        <form class="cfg-row<?= $s['active'] ? '' : ' tag-soft' ?>" method="post" action="<?= e(url('/admin/crm/origens/' . (int) $s['id'])) ?>">
            <?= csrf_field() ?>
            <label class="sr-only" for="src<?= (int) $s['id'] ?>">Nome da origem</label>
            <input class="input" id="src<?= (int) $s['id'] ?>" name="name" maxlength="60" value="<?= e($s['name']) ?>" required>
            <label class="checkbox cfg-act"><input type="checkbox" name="active" value="1"<?= $s['active'] ? ' checked' : '' ?>><span>Ativa</span></label>
            <span class="small muted cfg-n"><?= (int) $s['leads'] ?> <?= (int) $s['leads'] === 1 ? 'lead' : 'leads' ?></span>
            <button class="btn btn-ghost btn-sm" type="submit">Salvar</button>
        </form>
    <?php endforeach; ?>
    <form class="cfg-row cfg-add" method="post" action="<?= e(url('/admin/crm/origens')) ?>">
        <?= csrf_field() ?>
        <label class="sr-only" for="src_new">Nova origem</label>
        <input class="input" id="src_new" name="name" maxlength="60" placeholder="Nova origem (ex.: Palestra na academia)" required>
        <button class="btn btn-sm" type="submit"><?= icon('plus', 16) ?> Adicionar</button>
    </form>
</section>

<section class="panel">
    <h3>Etapas do funil</h3>
    <p class="muted small" style="margin-top:-6px">A ordem é o número da esquerda. "Convertido" e "Perdido" são as etapas finais e ficam sempre no fim: elas têm botões próprios nas próximas atualizações.</p>
    <?php foreach ($stages as $st): $fixed = $st['kind'] !== 'open'; ?>
        <form class="cfg-row<?= $st['active'] ? '' : ' tag-soft' ?>" method="post" action="<?= e(url('/admin/crm/etapas/' . (int) $st['id'])) ?>">
            <?= csrf_field() ?>
            <?php if ($fixed): ?><span class="cfg-ord muted" title="Etapa final" aria-hidden="true">·</span>
            <?php else: ?><label class="sr-only" for="so<?= (int) $st['id'] ?>">Ordem</label><input class="input cfg-ord" type="number" min="1" max="98" id="so<?= (int) $st['id'] ?>" name="sort_order" value="<?= (int) $st['sort_order'] ?>"><?php endif; ?>
            <label class="sr-only" for="sn<?= (int) $st['id'] ?>">Nome da etapa</label>
            <input class="input" id="sn<?= (int) $st['id'] ?>" name="name" maxlength="60" value="<?= e($st['name']) ?>" required>
            <?php if ($fixed): ?><span class="small muted cfg-act"><?= $st['kind'] === 'won' ? 'final · ganho' : 'final · perdido' ?></span>
            <?php else: ?><label class="checkbox cfg-act"><input type="checkbox" name="active" value="1"<?= $st['active'] ? ' checked' : '' ?>><span>Ativa</span></label><?php endif; ?>
            <span class="small muted cfg-n"><?= (int) $st['leads'] ?> <?= (int) $st['leads'] === 1 ? 'lead' : 'leads' ?></span>
            <button class="btn btn-ghost btn-sm" type="submit">Salvar</button>
        </form>
    <?php endforeach; ?>
    <form class="cfg-row cfg-add" method="post" action="<?= e(url('/admin/crm/etapas')) ?>">
        <?= csrf_field() ?>
        <label class="sr-only" for="stage_new">Nova etapa</label>
        <input class="input" id="stage_new" name="name" maxlength="60" placeholder="Nova etapa (entra antes de Convertido)" required>
        <button class="btn btn-sm" type="submit"><?= icon('plus', 16) ?> Adicionar</button>
    </form>
</section>
</div>
