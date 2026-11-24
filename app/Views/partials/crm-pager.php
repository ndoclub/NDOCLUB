<?php
/**
 * Paginação das listas do CRM. Recebe: $total, $from, $to, $page, $pages, $nums, $perPage, $pageLink (função), $cur, $defaults, $action (URL da lista),
 * $one e $many (substantivo no singular e no plural).
 */
?>
<div class="pager">
    <p class="small muted pager-info">Mostrando <strong><?= (int) $from ?>–<?= (int) $to ?></strong> de <strong><?= (int) $total ?></strong> <?= $total === 1 ? e($one) : e($many) ?></p>
    <?php if ($pages > 1): ?>
    <nav class="pager-nav" aria-label="Páginas">
        <?php if ($page > 1): ?><a class="pg" href="<?= e($pageLink($page - 1)) ?>" aria-label="Página anterior"><?= icon('left', 16) ?></a><?php else: ?><span class="pg off" aria-hidden="true"><?= icon('left', 16) ?></span><?php endif; ?>
        <?php $prev = 0; foreach ($nums as $n): if ($n - $prev > 1): ?><span class="pg gap" aria-hidden="true">…</span><?php endif; ?>
            <a class="pg<?= $n === $page ? ' on' : '' ?>" href="<?= e($pageLink($n)) ?>"<?= $n === $page ? ' aria-current="page"' : '' ?> aria-label="Página <?= $n ?>"><?= $n ?></a>
        <?php $prev = $n; endforeach; ?>
        <?php if ($page < $pages): ?><a class="pg" href="<?= e($pageLink($page + 1)) ?>" aria-label="Próxima página"><?= icon('right', 16) ?></a><?php else: ?><span class="pg off" aria-hidden="true"><?= icon('right', 16) ?></span><?php endif; ?>
    </nav>
    <?php endif; ?>
    <form class="pager-pp" method="get" action="<?= e($action) ?>">
        <?php foreach (array_diff_key($cur, ['pp' => 1]) as $k => $v): if ($v === '' || $v === 0 || $v === null || (isset($defaults[$k]) && $defaults[$k] === $v)) { continue; } ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
        <?php endforeach; ?>
        <label for="pp">Mostrar:</label>
        <select id="pp" name="pp" data-autosubmit>
            <?php foreach ([10, 25, 50, 100] as $n): ?><option value="<?= $n ?>"<?= $perPage === $n ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
        </select>
    </form>
</div>
