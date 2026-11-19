<?php foreach (flashes() as $f): ?>
    <div class="flash flash-<?= $f['type'] === 'err' ? 'err' : 'ok' ?>" role="<?= $f['type'] === 'err' ? 'alert' : 'status' ?>">
        <span><?= e($f['msg']) ?></span>
        <button type="button" class="flash-close" data-dismiss aria-label="Fechar aviso"><?= icon('x', 18) ?></button>
    </div>
<?php endforeach; ?>
