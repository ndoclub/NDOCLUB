<?php if (!$diet || (!$diet['link_url'] && !$diet['pdf_file'] && !$diet['notes'])): ?>
    <div class="empty">
        <strong>Seu plano alimentar ainda não foi enviado</strong>
        Assim que o Gabriel publicar, ele aparece aqui.
    </div>
<?php else: ?>
    <section class="page">
        <h2 class="title-xl"><?= e($diet['title']) ?></h2>
        <p class="muted small mt">Atualizado em <?= e(fmt_date($diet['updated_at'])) ?></p>

        <div class="stack mt-lg">
            <?php if ($diet['pdf_file']): ?>
                <a class="btn btn-block" href="<?= e(url('/media/dieta/' . $diet['pdf_file'])) ?>" target="_blank" rel="noopener"><?= icon('doc', 20) ?> Abrir o PDF da dieta</a>
            <?php endif; ?>
            <?php if ($diet['link_url']): ?>
                <a class="btn <?= $diet['pdf_file'] ? 'btn-ghost ' : '' ?>btn-block" href="<?= e($diet['link_url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external', 20) ?> Abrir no app de dieta</a>
            <?php endif; ?>
        </div>

        <?php if ($diet['notes']): ?>
            <h3 class="section-title mt-lg">Recados do Gabriel</h3>
            <p><?= text_html($diet['notes']) ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>
