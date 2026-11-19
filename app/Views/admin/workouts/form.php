<?php
$isEdit = (bool) $w;
$action = $isEdit ? '/admin/treinos/' . (int) $w['id'] : '/admin/treinos';
$val = fn($k, $d = '') => old($k, $w[$k] ?? $d);
$selUser = (int) old('user_id', (string) ($w['user_id'] ?? $presetUser));
$selDay = (int) old('weekday', (string) ($w['weekday'] ?? 0));
?>
<div class="page-head"><h2><?= $isEdit ? 'Editar treino' : 'Novo treino' ?></h2></div>
<form class="panel" method="post" action="<?= e(url($action)) ?>" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field full"><label for="title">Nome do treino</label><input class="input" id="title" name="title" maxlength="80" value="<?= e($val('title')) ?>" placeholder="Ex.: Treino A - Peito e tríceps" required></div>
        <div class="field"><label for="user_id">Para quem</label>
            <select id="user_id" name="user_id">
                <option value="0">Todos os pacientes (treino geral)</option>
                <?php foreach ($patients as $pt): ?><option value="<?= (int) $pt['id'] ?>"<?= $selUser === (int) $pt['id'] ? ' selected' : '' ?>><?= e($pt['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="field"><label for="weekday">Dia da semana</label>
            <select id="weekday" name="weekday">
                <option value="0">Qualquer dia</option>
                <?php foreach ([1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'] as $n => $label): ?>
                    <option value="<?= $n ?>"<?= $selDay === $n ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Com o dia definido, o treino aparece na tela inicial naquele dia.</p></div>
        <div class="field full"><label for="details">Exercícios</label>
            <textarea class="input" id="details" name="details" style="min-height:180px" placeholder="Supino reto - 4x10&#10;Crucifixo - 3x12&#10;Tríceps testa - 3x12"><?= e($val('details')) ?></textarea>
            <p class="hint">Um exercício por linha. As quebras de linha são mantidas.</p></div>
        <div class="field"><label for="sort_order">Ordem na lista</label><input class="input" type="number" id="sort_order" name="sort_order" value="<?= e($val('sort_order', '0')) ?>"></div>
        <div class="field"><label class="label">Status</label>
            <label class="checkbox" style="margin-top:12px"><input type="checkbox" name="active" value="1"<?= (!$isEdit || $w['active']) ? ' checked' : '' ?>><span>Ativo (visível para o paciente)</span></label></div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Salvar treino</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/treinos')) ?>">Cancelar</a>
    </div>
</form>
<?php if ($isEdit): ?>
<form method="post" action="<?= e(url('/admin/treinos/' . (int) $w['id'] . '/excluir')) ?>" data-confirm="Excluir este treino e o histórico de &quot;feito&quot; dele?">
    <?= csrf_field() ?><button class="link-btn danger" type="submit">Excluir este treino</button>
</form>
<?php endif; ?>
