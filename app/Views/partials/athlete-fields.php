<?php
/*
 * Campos do perfil esportivo (usado pelo admin e pelo paciente).
 * Recebe: $athlete (linha de athlete_profiles ou null), $choices (modalidades), $selected (ids marcados).
 */
$a = $athlete ?? [];
$on = !empty($a['is_athlete']);
$coachOn = !empty($a['has_coach']);
$compOn = !empty($a['competes']);
$selected = $selected ?? [];
?>
<label class="checkbox mb"><input type="checkbox" name="is_athlete" value="1" data-reveal="#athlete-fields"<?= $on ? ' checked' : '' ?>><span><strong>É atleta</strong> (pratica esporte com regularidade)</span></label>

<div id="athlete-fields"<?= $on ? '' : ' hidden' ?>>
    <fieldset class="choice-set">
        <legend>Modalidades</legend>
        <?php if ($choices): ?>
        <div class="pills">
            <?php foreach ($choices as $s): ?>
                <label class="pill"><input type="checkbox" name="sport_ids[]" value="<?= (int) $s['id'] ?>"<?= in_array((int) $s['id'], $selected, true) ? ' checked' : '' ?>><span><?= e($s['name']) ?><?= empty($s['active']) ? ' (desativada)' : '' ?></span></label>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="hint">Nenhuma modalidade cadastrada ainda.</p>
        <?php endif; ?>
    </fieldset>

    <label class="checkbox mb"><input type="checkbox" name="has_coach" value="1" data-reveal="#coach-field"<?= $coachOn ? ' checked' : '' ?>><span>Tem assessoria ou treinador esportivo</span></label>
    <div class="field" id="coach-field"<?= $coachOn ? '' : ' hidden' ?>>
        <label for="coach_name">Nome da assessoria / treinador</label>
        <input class="input" id="coach_name" name="coach_name" maxlength="80" value="<?= e($a['coach_name'] ?? '') ?>" placeholder="Ex.: Assessoria Pace">
    </div>

    <label class="checkbox mb"><input type="checkbox" name="competes" value="1" data-reveal="#event-fields"<?= $compOn ? ' checked' : '' ?>><span>Participa de campeonatos / provas</span></label>
    <div class="form-grid" id="event-fields"<?= $compOn ? '' : ' hidden' ?>>
        <div class="field"><label for="event_name">Próxima prova</label>
            <input class="input" id="event_name" name="event_name" maxlength="100" value="<?= e($a['event_name'] ?? '') ?>" placeholder="Ex.: Maratona de São Paulo"></div>
        <div class="field"><label for="event_date">Data da prova</label>
            <input class="input" type="date" id="event_date" name="event_date" value="<?= e($a['event_date'] ?? '') ?>"></div>
    </div>
</div>
