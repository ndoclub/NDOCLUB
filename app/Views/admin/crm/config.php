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
    <p class="muted small" style="margin-top:-6px">A ordem é o número da esquerda. "Convertido" e "Perdido" são as etapas finais e ficam sempre no fim: quem as usa são os botões "Converter em paciente" e "Marcar como perdido" da ficha. Marque "Conta na receita potencial" nas etapas em que o lead já está perto de fechar: a soma do plano de interesse dos leads nelas vira o indicador "Receita potencial em negociação".</p>
    <?php foreach ($stages as $st): $fixed = $st['kind'] !== 'open'; ?>
        <form class="cfg-row<?= $st['active'] ? '' : ' tag-soft' ?>" method="post" action="<?= e(url('/admin/crm/etapas/' . (int) $st['id'])) ?>">
            <?= csrf_field() ?>
            <?php if ($fixed): ?><span class="cfg-ord muted" title="Etapa final" aria-hidden="true">·</span>
            <?php else: ?><label class="sr-only" for="so<?= (int) $st['id'] ?>">Ordem</label><input class="input cfg-ord" type="number" min="1" max="98" id="so<?= (int) $st['id'] ?>" name="sort_order" value="<?= (int) $st['sort_order'] ?>"><?php endif; ?>
            <label class="sr-only" for="sn<?= (int) $st['id'] ?>">Nome da etapa</label>
            <input class="input" id="sn<?= (int) $st['id'] ?>" name="name" maxlength="60" value="<?= e($st['name']) ?>" required>
            <?php if ($fixed): ?><span class="small muted cfg-act"><?= $st['kind'] === 'won' ? 'final · ganho' : 'final · perdido' ?></span>
            <?php else: ?><label class="checkbox cfg-act"><input type="checkbox" name="active" value="1"<?= $st['active'] ? ' checked' : '' ?>><span>Ativa</span></label>
                <label class="checkbox cfg-act" title="Soma o valor do plano de interesse dos leads desta etapa no indicador Receita potencial em negociação"><input type="checkbox" name="potential" value="1"<?= !empty($st['potential']) ? ' checked' : '' ?>><span>Conta na receita potencial</span></label><?php endif; ?>
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

<section class="panel">
    <h3>Motivos de perda</h3>
    <p class="muted small" style="margin-top:-6px">Aparecem quando um lead é marcado como perdido. Os leads já perdidos mantêm o motivo, mesmo se ele for desativado.</p>
    <?php foreach ($reasons as $rs): ?>
        <form class="cfg-row<?= $rs['active'] ? '' : ' tag-soft' ?>" method="post" action="<?= e(url('/admin/crm/motivos/' . (int) $rs['id'])) ?>">
            <?= csrf_field() ?>
            <label class="sr-only" for="rs<?= (int) $rs['id'] ?>">Motivo da perda</label>
            <input class="input" id="rs<?= (int) $rs['id'] ?>" name="name" maxlength="80" value="<?= e($rs['name']) ?>" required>
            <label class="checkbox cfg-act"><input type="checkbox" name="active" value="1"<?= $rs['active'] ? ' checked' : '' ?>><span>Ativo</span></label>
            <span class="small muted cfg-n"><?= (int) $rs['leads'] ?> <?= (int) $rs['leads'] === 1 ? 'lead' : 'leads' ?></span>
            <button class="btn btn-ghost btn-sm" type="submit">Salvar</button>
        </form>
    <?php endforeach; ?>
    <form class="cfg-row cfg-add" method="post" action="<?= e(url('/admin/crm/motivos')) ?>">
        <?= csrf_field() ?>
        <label class="sr-only" for="reason_new">Novo motivo</label>
        <input class="input" id="reason_new" name="name" maxlength="80" placeholder="Novo motivo (ex.: Mudou de cidade)" required>
        <button class="btn btn-sm" type="submit"><?= icon('plus', 16) ?> Adicionar</button>
    </form>
</section>

<section class="panel">
    <h3>Cadência da reativação</h3>
    <p class="muted small" style="margin-top:-6px">Quantos dias depois da perda o time tenta falar de novo com um lead que autorizou novos contatos. O primeiro contato só pode acontecer a partir de <?= (int) $minDays ?> dias. Use de 1 a 5 prazos, separados por vírgula.</p>
    <form class="cfg-row cfg-add" method="post" action="<?= e(url('/admin/crm/reativacao/cadencia')) ?>">
        <?= csrf_field() ?>
        <label class="sr-only" for="cad_days">Dias depois da perda</label>
        <input class="input" id="cad_days" name="days" maxlength="40" value="<?= e(implode(', ', $recDays)) ?>" inputmode="numeric" placeholder="20, 30, 45" required>
        <button class="btn btn-sm" type="submit">Salvar cadência</button>
    </form>
    <p class="hint">Hoje: <?= e(implode(', ', $recDays)) ?> dias, ou seja, <?= count($recDays) ?> <?= count($recDays) === 1 ? 'tentativa' : 'tentativas' ?>. A mudança vale para as próximas tentativas; as já agendadas continuam nas datas marcadas.</p>
</section>
</div>
