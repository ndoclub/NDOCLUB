<div class="page-head"><h2>Configurações</h2></div>
<form method="post" action="<?= e(url('/admin/config')) ?>" style="max-width:760px">
    <?= csrf_field() ?>
    <?php $v = fn($k) => old($k, $s[$k] ?? ''); ?>

    <div class="panel">
        <h3>WhatsApp da renovação</h3>
        <div class="form-grid">
            <div class="field"><label for="whatsapp_number">Número (55 + DDD + número)</label><input class="input" id="whatsapp_number" name="whatsapp_number" inputmode="numeric" value="<?= e($v('whatsapp_number')) ?>" required>
                <p class="hint">Ex.: 5511999998888. É para este número que o botão "Renovar pelo WhatsApp" envia.</p></div>
            <div class="field"><label for="whatsapp_message">Mensagem pronta</label><input class="input" id="whatsapp_message" name="whatsapp_message" maxlength="200" value="<?= e($v('whatsapp_message')) ?>"></div>
        </div>
    </div>

    <div class="panel">
        <h3>Mensagem de aniversário</h3>
        <div class="field"><textarea class="input" id="birthday_message" name="birthday_message" maxlength="300" style="min-height:96px" aria-label="Mensagem de aniversário"><?= e($v('birthday_message')) ?></textarea>
            <p class="hint">Use <strong>{nome}</strong> onde quiser o primeiro nome do paciente. Em branco = texto padrão. É o texto do botão "Dar parabéns" (Aniversários e Painel).</p></div>
    </div>

    <div class="panel">
        <h3>Mensagem de WhatsApp para leads</h3>
        <div class="field"><textarea class="input" id="lead_message" name="lead_message" maxlength="300" style="min-height:96px" aria-label="Mensagem de WhatsApp para leads"><?= e($v('lead_message')) ?></textarea>
            <p class="hint">Use <strong>{nome}</strong> onde quiser o primeiro nome do lead. Em branco = texto padrão. É o texto do botão de WhatsApp no CRM; vale para todos os tipos de retorno que não tiverem mensagem própria logo abaixo, e sempre para "Enviar WhatsApp", "Outra ação" e lead sem retorno marcado. Não é enviado sozinho: abre o WhatsApp com a mensagem pronta, e a pessoa da equipe envia.</p></div>
    </div>

    <div class="panel">
        <h3>Mensagens de WhatsApp por tipo de retorno</h3>
        <p class="hint" style="margin-top:-6px">O botão de WhatsApp do CRM escolhe o texto pelo tipo do próximo retorno do lead. Use <strong>{nome}</strong> para o primeiro nome. Em branco = vale a mensagem geral acima, se você escreveu uma; se não, o texto padrão que aparece em cinza no campo.</p>
        <?php foreach (\App\Services\Leads::ACTION_MESSAGES as $type => $default): $k = \App\Services\Leads::messageKey($type); ?>
        <div class="field"><label for="<?= e($k) ?>">Retorno do tipo "<?= e(\App\Services\Leads::ACTIONS[$type]) ?>"</label>
            <textarea class="input" id="<?= e($k) ?>" name="<?= e($k) ?>" maxlength="300" style="min-height:84px" placeholder="<?= e($default) ?>"><?= e($v($k)) ?></textarea></div>
        <?php endforeach; ?>
    </div>

    <div class="panel">
        <h3>Avisos de renovação</h3>
        <div class="form-grid">
            <div class="field"><label for="warn_yellow_days">Selo amarelo a partir de (dias)</label><input class="input" type="number" id="warn_yellow_days" name="warn_yellow_days" min="2" max="90" value="<?= e($v('warn_yellow_days')) ?>"></div>
            <div class="field"><label for="warn_red_days">Selo vermelho a partir de (dias)</label><input class="input" type="number" id="warn_red_days" name="warn_red_days" min="1" max="60" value="<?= e($v('warn_red_days')) ?>"></div>
        </div>
        <p class="hint" style="margin-top:0">Mais que o amarelo = verde. O botão de renovar aparece no app a partir do amarelo.</p>
    </div>

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

    <div class="form-actions"><button class="btn" type="submit">Salvar configurações</button></div>
</form>
