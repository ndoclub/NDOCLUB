<?php
use App\Services\Leads;

$isEdit = (bool) $lead;
$action = $isEdit ? '/admin/crm/' . (int) $lead['id'] : '/admin/crm';
$v = fn(string $k, $d = '') => old($k, $isEdit ? (string) ($lead[$k] ?? $d) : (string) $d);
$moreOpen = $isEdit || $dups !== null || array_filter(array_map(fn($k) => old($k), ['email', 'instagram', 'sport_id', 'goal', 'plan_id', 'responsible_id', 'referred_by', 'notes', 'entered_on']));
$soft = $dups !== null && $dups['email'] === null && ($dups['phone'] || $dups['instagram'] || $dups['patient']);
?>
<div class="page-head"><h2><?= $isEdit ? 'Editar lead' : 'Novo lead' ?></h2></div>

<?php if ($dups !== null): ?>
<div class="panel dup-panel" role="alert">
    <h3>Este contato já está cadastrado</h3>
    <ul class="dup-list">
        <?php if ($dups['email']): ?><li>Já existe um lead com este <strong>e-mail</strong>: <a href="<?= e(url('/admin/crm/' . (int) $dups['email']['id'])) ?>"><?= e($dups['email']['name']) ?></a>. Abra esse lead e registre o contato nele.</li><?php endif; ?>
        <?php foreach ($dups['phone'] as $d): ?><li>Mesmo <strong>telefone</strong> do lead <a href="<?= e(url('/admin/crm/' . (int) $d['id'])) ?>"><?= e($d['name']) ?></a>.</li><?php endforeach; ?>
        <?php foreach ($dups['instagram'] as $d): ?><li>Mesmo <strong>Instagram</strong> do lead <a href="<?= e(url('/admin/crm/' . (int) $d['id'])) ?>"><?= e($d['name']) ?></a>.</li><?php endforeach; ?>
        <?php if ($dups['patient']): ?><li>Já é <strong>paciente</strong>: <a href="<?= e(url('/admin/pacientes/' . (int) $dups['patient']['id'])) ?>"><?= e($dups['patient']['name']) ?></a>.</li><?php endif; ?>
    </ul>
    <?php if ($dups['email'] !== null): ?>
        <p class="hint" style="margin-bottom:0">Com o mesmo e-mail não dá para cadastrar de novo. Se for outra pessoa, troque o e-mail.</p>
    <?php else: ?>
        <p class="hint" style="margin-bottom:0">Confira antes de continuar. Se for outra pessoa, marque a caixinha no fim do formulário e salve de novo.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<form class="panel" method="post" action="<?= e(url($action)) ?>" style="max-width:820px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field"><label for="name">Nome completo</label><input class="input" id="name" name="name" maxlength="120" value="<?= e($v('name')) ?>" placeholder="Ex.: Mariana Oliveira" required></div>
        <div class="field"><label for="phone">WhatsApp</label><input class="input" id="phone" name="phone" inputmode="tel" maxlength="30" value="<?= e($v('phone')) ?>" placeholder="(11) 90000-0000">
            <p class="hint">Pode ser com ou sem DDD e +55. Informe pelo menos um contato: WhatsApp, e-mail ou Instagram.</p></div>
        <div class="field"><label for="source_id">Origem</label>
            <select id="source_id" name="source_id" required>
                <option value="">Escolha…</option>
                <?php foreach ($sources as $s): ?><option value="<?= (int) $s['id'] ?>"<?= $v('source_id') === (string) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?><?= $s['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
            </select>
            <p class="hint">De onde o lead veio (anúncio, indicação…).</p></div>
        <div class="field"><label for="channel">Canal de contato</label>
            <?php $chSel = $v('channel', $isEdit ? '' : 'whatsapp'); ?>
            <select id="channel" name="channel">
                <option value="">Não informado</option>
                <?php foreach (Leads::CHANNELS as $k => $label): ?><option value="<?= e($k) ?>"<?= $chSel === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <p class="hint">Por onde vocês estão conversando. É diferente da origem.</p></div>
    </div>

    <details class="lead-more"<?= $moreOpen ? ' open' : '' ?>>
        <summary><?= icon('plus', 16) ?> Mais dados (opcional)</summary>
        <div class="form-grid" style="margin-top:14px">
            <div class="field"><label for="email">E-mail</label><input class="input" type="email" id="email" name="email" maxlength="160" value="<?= e($v('email')) ?>" placeholder="nome@exemplo.com"></div>
            <div class="field"><label for="instagram">Instagram</label><input class="input" id="instagram" name="instagram" maxlength="120" value="<?= e($isEdit && !old('instagram') && $lead['instagram'] ? '@' . $lead['instagram'] : old('instagram')) ?>" placeholder="@usuario">
                <p class="hint">Digite o @ ou cole o link do perfil.</p></div>
            <div class="field"><label for="sport_id">Modalidade de interesse</label>
                <select id="sport_id" name="sport_id">
                    <option value="">Não informada</option>
                    <?php foreach ($sports as $sp): ?><option value="<?= (int) $sp['id'] ?>"<?= $v('sport_id') === (string) $sp['id'] ? ' selected' : '' ?>><?= e($sp['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="field"><label for="plan_id">Plano de interesse</label>
                <select id="plan_id" name="plan_id">
                    <option value="">Ainda não definido</option>
                    <?php foreach ($plans as $pl): ?><option value="<?= (int) $pl['id'] ?>"<?= $v('plan_id') === (string) $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="field full"><label for="goal">Objetivo ou necessidade</label><input class="input" id="goal" name="goal" maxlength="160" value="<?= e($v('goal')) ?>" placeholder="Ex.: hipertrofia, preparação para prova de corrida"></div>
            <div class="field"><label for="responsible_id">Responsável</label>
                <select id="responsible_id" name="responsible_id">
                    <option value="">Sem responsável</option>
                    <?php foreach ($responsibles as $rp): ?><option value="<?= (int) $rp['id'] ?>"<?= $v('responsible_id') === (string) $rp['id'] ? ' selected' : '' ?>><?= e($rp['name']) ?><?= $rp['active'] ? '' : ' (desativada)' ?></option><?php endforeach; ?>
                </select></div>
            <div class="field"><label for="entered_on">Data de entrada</label><input class="input" type="date" id="entered_on" name="entered_on" max="<?= e(today()) ?>" value="<?= e($v('entered_on', today())) ?>"></div>
            <?php if (!$isEdit): ?>
            <div class="field"><label for="temperature">Temperatura</label>
                <select id="temperature" name="temperature">
                    <?php $tSel = $v('temperature', 'warm'); foreach (Leads::TEMPS as $k => $label): ?><option value="<?= e($k) ?>"<?= $tSel === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <p class="hint">Quente: quer fechar. Morno: avaliando. Frio: sem pressa. Dá para mudar depois.</p></div>
            <div class="field"><label for="stage_id">Etapa do funil</label>
                <select id="stage_id" name="stage_id">
                    <?php $sSel = $v('stage_id'); foreach ($stages as $st): ?><option value="<?= (int) $st['id'] ?>"<?= $sSel === (string) $st['id'] ? ' selected' : '' ?>><?= e($st['name']) ?></option><?php endforeach; ?>
                </select></div>
            <?php endif; ?>
            <div class="field full"><label for="referred_by">Quem indicou (se foi indicação de paciente)</label>
                <select id="referred_by" name="referred_by">
                    <option value="">Ninguém / não se aplica</option>
                    <?php foreach ($patients as $pt): ?><option value="<?= (int) $pt['id'] ?>"<?= $v('referred_by') === (string) $pt['id'] ? ' selected' : '' ?>><?= e($pt['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="field full"><label for="notes">Observações</label><textarea class="input" id="notes" name="notes" maxlength="1000" style="min-height:90px"><?= e($v('notes')) ?></textarea>
                <p class="hint">Dado importante sobre o lead. Para registrar uma conversa, use "Registrar contato" na ficha: assim fica na linha do tempo.</p></div>
        </div>
    </details>

    <?php if ($soft): ?>
        <label class="checkbox" style="margin-top:14px"><input type="checkbox" name="ok_dup" value="1"><span>Conferi: <strong>não é a mesma pessoa</strong>, cadastrar mesmo assim.</span></label>
    <?php endif; ?>

    <div class="form-actions" style="margin-top:16px">
        <button class="btn" type="submit"><?= $isEdit ? 'Salvar dados' : 'Cadastrar lead' ?></button>
        <a class="btn btn-ghost" href="<?= e(url($isEdit ? '/admin/crm/' . (int) $lead['id'] : '/admin/crm')) ?>">Cancelar</a>
    </div>
</form>
