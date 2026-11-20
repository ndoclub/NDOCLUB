<?php
$isEdit = (bool) $r;
$action = $isEdit ? '/admin/responsaveis/' . (int) $r['id'] : '/admin/responsaveis';
?>
<div class="page-head"><h2><?= $isEdit ? 'Editar responsável' : 'Novo responsável' ?></h2></div>
<form class="panel" method="post" action="<?= e(url($action)) ?>" style="max-width:520px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field full"><label for="name">Nome</label><input class="input" id="name" name="name" maxlength="80" value="<?= e(old('name', $r['name'] ?? '')) ?>" placeholder="Ex.: Camila" required></div>
        <div class="field full"><label class="checkbox"><input type="checkbox" name="active" value="1"<?= (!$isEdit || $r['active']) ? ' checked' : '' ?>><span>Ativo (aparece ao escolher o responsável de um paciente)</span></label>
            <p class="hint">Não dá para apagar: desative para esconder. Os pacientes dela continuam com a etiqueta até você atribuir a outra pessoa.</p></div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Salvar responsável</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/responsaveis')) ?>">Cancelar</a>
    </div>
</form>
