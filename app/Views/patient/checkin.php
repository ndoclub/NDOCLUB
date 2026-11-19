<?php if ($today): ?>
    <section class="page">
        <p class="done-tag" style="display:flex"><?= icon('check', 22) ?> Check-in de hoje feito</p>
        <p class="title-xl mt"><?= (int) $streak ?> <?= $streak === 1 ? 'dia' : 'dias' ?> seguidos</p>
        <div class="list mt-lg" style="border-top:1px solid var(--line)">
            <div class="li"><div class="li-main"><span class="li-sub">Sono</span><span class="li-title"><?= e($sleep[(int) $today['sleep_hours']] ?? '-') ?></span></div></div>
            <div class="li"><div class="li-main"><span class="li-sub">Água</span><span class="li-title"><?= e($water[(int) $today['water_ml']] ?? '-') ?></span></div></div>
            <div class="li"><div class="li-main"><span class="li-sub">Dieta</span><span class="li-title"><?= e($diet[(int) $today['diet_score']] ?? '-') ?></span></div></div>
        </div>
        <a class="btn btn-ghost btn-block mt-lg" href="<?= e(url('/home')) ?>">Voltar ao início</a>
    </section>
<?php else: ?>
    <form class="page" method="post" action="<?= e(url('/checkin')) ?>">
        <?= csrf_field() ?>
        <p class="lead mb">Três toques e pronto. Você ganha pontos e mantém a sequência de <?= (int) $streak ?> <?= $streak === 1 ? 'dia' : 'dias' ?>.</p>

        <fieldset class="choice-set">
            <legend>Quantas horas você dormiu?</legend>
            <div class="choice-grid">
                <?php foreach ($sleep as $v => $label): ?>
                    <label class="choice"><input type="radio" name="sleep" value="<?= (int) $v ?>" required><span><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="choice-set">
            <legend>Quanta água você bebeu?</legend>
            <div class="choice-grid">
                <?php foreach ($water as $v => $label): ?>
                    <label class="choice"><input type="radio" name="water" value="<?= (int) $v ?>" required><span><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="choice-set">
            <legend>Você seguiu a dieta?</legend>
            <div class="choice-grid three">
                <?php foreach ($diet as $v => $label): ?>
                    <label class="choice"><input type="radio" name="diet" value="<?= (int) $v ?>" required><span><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <button class="btn btn-block" type="submit">Concluir check-in</button>
    </form>
<?php endif; ?>
