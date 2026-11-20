<?php
$isEdit = (bool) $sport;
$action = $isEdit ? '/admin/modalidades/' . (int) $sport['id'] : '/admin/modalidades';
?>
<div class="page-head"><h2><?= $isEdit ? 'Editar modalidade' : 'Nova modalidade' ?></h2></div>
<form class="panel" method="post" action="<?= e(url($action)) ?>" style="max-width:520px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field full"><label for="name">Nome da modalidade</label><input class="input" id="name" name="name" maxlength="40" value="<?= e(old('name', $sport['name'] ?? '')) ?>" placeholder="Ex.: Ciclismo" required></div>
        <div class="field full"><label class="checkbox"><input type="checkbox" name="active" value="1"<?= (!$isEdit || $sport['active']) ? ' checked' : '' ?>><span>Ativa (aparece ao escolher as modalidades de um paciente)</span></label>
            <p class="hint">Modalidades não são apagadas: desative para esconder. Quem já tem continua com ela.</p></div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Salvar modalidade</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/modalidades')) ?>">Cancelar</a>
    </div>
</form>
