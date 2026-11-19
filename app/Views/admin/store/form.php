<?php
$isEdit = (bool) $item;
$action = $isEdit ? '/admin/loja/' . (int) $item['id'] : '/admin/loja';
$val = fn($k, $d = '') => old($k, $item[$k] ?? $d);
?>
<div class="page-head"><h2><?= $isEdit ? 'Editar brinde' : 'Novo brinde' ?></h2></div>
<form class="panel" method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field full"><label for="name">Nome</label><input class="input" id="name" name="name" maxlength="100" value="<?= e($val('name')) ?>" placeholder="Ex.: Camiseta No Days Off" required></div>
        <div class="field full"><label for="description">Descrição curta</label><input class="input" id="description" name="description" maxlength="300" value="<?= e($val('description')) ?>"></div>
        <div class="field"><label for="cost_points">Custo em pontos</label><input class="input" type="number" id="cost_points" name="cost_points" min="1" value="<?= e($val('cost_points', '500')) ?>" required></div>
        <div class="field"><label for="stock">Estoque</label><input class="input" type="number" id="stock" name="stock" min="0" value="<?= e($val('stock', '10')) ?>" required></div>
        <div class="field full"><label for="image">Foto do brinde</label>
            <?php if ($isEdit && $item['image']): ?><img class="thumb" style="margin-bottom:10px" src="<?= e(url('/media/img/' . $item['image'])) ?>" alt=""><?php endif; ?>
            <input class="input" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"></div>
        <div class="field full"><label class="checkbox"><input type="checkbox" name="active" value="1"<?= (!$isEdit || $item['active']) ? ' checked' : '' ?>><span>Visível na loja</span></label></div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Salvar brinde</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/loja')) ?>">Cancelar</a>
    </div>
</form>
