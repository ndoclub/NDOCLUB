<div class="page-head"><h2>Ajustes do app</h2></div>
<form method="post" action="<?= e(url('/admin/app/ajustes')) ?>" style="max-width:760px">
    <?= csrf_field() ?>
    <?php $v = fn($k) => old($k, $s[$k] ?? ''); ?>

    <div class="panel">
        <h3>Níveis (pontos ganhos desde sempre)</h3>
        <div class="form-grid">
            <div class="field"><label for="level_prata">Prata a partir de</label><input class="input" type="number" id="level_prata" name="level_prata" min="1" value="<?= e($v('level_prata')) ?>"></div>
            <div class="field"><label for="level_ouro">Ouro a partir de</label><input class="input" type="number" id="level_ouro" name="level_ouro" min="2" value="<?= e($v('level_ouro')) ?>"></div>
            <div class="field"><label for="level_black">Black a partir de</label><input class="input" type="number" id="level_black" name="level_black" min="3" value="<?= e($v('level_black')) ?>"></div>
        </div>
        <p class="hint" style="margin-top:0">Todo mundo começa no Bronze. O nível nunca cai, mesmo quando o ranking zera.</p>
    </div>

    <div class="panel">
        <h3>Tema do dia no feed</h3>
        <?php foreach ([1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'] as $n => $label): ?>
            <div class="form-grid">
                <div class="field"><label for="theme_<?= $n ?>"><?= e($label) ?>: título</label><input class="input" id="theme_<?= $n ?>" name="theme_<?= $n ?>" maxlength="80" value="<?= e($v('theme_' . $n)) ?>"></div>
                <div class="field"><label for="theme_<?= $n ?>_q">Pergunta</label><input class="input" id="theme_<?= $n ?>_q" name="theme_<?= $n ?>_q" maxlength="160" value="<?= e($v('theme_' . $n . '_q')) ?>"></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel">
        <h3>Regras de convivência</h3>
        <div class="field"><textarea class="input" id="community_rules" name="community_rules" maxlength="1500" style="min-height:130px" aria-label="Regras de convivência"><?= e($v('community_rules')) ?></textarea>
            <p class="hint">Aparecem no feed e no termo de privacidade.</p></div>
    </div>

    <div class="panel">
        <h3>Termo de consentimento</h3>
        <p class="muted small">Versão atual: <strong><?= e($s['consent_version']) ?></strong></p>
        <label class="checkbox small"><input type="checkbox" name="bump_consent" value="1"><span>Atualizei o termo: pedir que todos aceitem de novo no próximo acesso</span></label>
    </div>

    <div class="form-actions"><button class="btn" type="submit">Salvar ajustes do app</button></div>
</form>
