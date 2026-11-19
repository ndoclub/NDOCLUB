<?php $weekdays = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo']; ?>
<section class="page" style="padding-bottom:8px">
    <p class="title-xl"><?= (int) $monthTotal ?> <?= $monthTotal === 1 ? 'treino' : 'treinos' ?></p>
    <p class="muted">feitos em <?= e(month_name((int) date('n'))) ?></p>
</section>

<?php if (!$workouts): ?>
    <div class="empty"><strong>Sua ficha de treino ainda não foi montada</strong>Assim que o Gabriel publicar, ela aparece aqui.</div>
<?php endif; ?>

<?php foreach ($workouts as $w): ?>
    <article class="workout">
        <?php if ($w['weekday']): ?><span class="when"><?= e($weekdays[(int) $w['weekday']] ?? '') ?></span><?php endif; ?>
        <h3><?= e($w['title']) ?></h3>
        <?php if ($w['details']): ?><div class="details"><?= e($w['details']) ?></div><?php endif; ?>
        <?php if ((int) $w['done_today'] > 0): ?>
            <span class="done-tag"><?= icon('check', 20) ?> Feito hoje</span>
        <?php else: ?>
            <form method="post" action="<?= e(url('/treino/' . $w['id'] . '/feito')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm" type="submit">Marcar como feito hoje</button>
            </form>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<section class="section">
    <h2 class="section-title">Calendário de <?= e(month_name((int) date('n'))) ?></h2>
    <div class="cal" role="grid" aria-label="Dias com treino feito">
        <?php foreach (['S', 'T', 'Q', 'Q', 'S', 'S', 'D'] as $h): ?><div class="cal-h"><?= $h ?></div><?php endforeach; ?>
        <?php for ($i = 0; $i < $startOffset; $i++): ?><div class="cal-day blank"></div><?php endfor; ?>
        <?php for ($d = 1; $d <= $daysInMonth; $d++): $done = isset($doneDays[$d]); ?>
            <div class="cal-day<?= $done ? ' done' : '' ?><?= $d === $todayDay ? ' today' : '' ?>"<?= $done ? ' aria-label="Dia ' . $d . ': treino feito"' : '' ?>><span><?= $d ?></span></div>
        <?php endfor; ?>
    </div>
</section>
