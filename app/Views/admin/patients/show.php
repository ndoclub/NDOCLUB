<?php
$id = (int) $p['id'];
$waWelcome = $welcome ? wa_phone_url($p['phone'], $welcome) : null;
$weekdays = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
?>
<div class="page-head">
    <div class="row">
        <?= avatar($p, 56) ?>
        <div>
            <h2 style="font-size:22px"><?= e($p['name']) ?></h2>
            <div class="row wrap" style="gap:8px;margin-top:6px">
                <?php if ($p['status'] === 'inactive'): ?><span class="tag">Desativado</span><?php else: partial('seal', ['renew' => $renew]); endif; ?>
                <?php partial('level', ['level' => $level]); ?>
            </div>
        </div>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/pacientes')) ?>">Voltar à lista</a>
</div>

<?php if ($welcome): ?>
<div class="panel">
    <h3>Mensagem de boas-vindas (aparece só agora)</h3>
    <div class="copy-box" id="welcome"><?= e($welcome) ?></div>
    <div class="form-actions" style="margin-top:0">
        <button class="btn btn-sm" type="button" data-copy="#welcome">Copiar mensagem</button>
        <?php if ($waWelcome): ?><a class="btn btn-ghost btn-sm" href="<?= e($waWelcome) ?>" target="_blank" rel="noopener">Enviar pelo WhatsApp</a><?php endif; ?>
    </div>
    <p class="hint">A senha provisória não fica salva em lugar nenhum e não será mostrada de novo.</p>
</div>
<?php endif; ?>

<div class="kpis">
    <div class="kpi"><span class="big num"><?= (int) $streak ?></span><span class="lbl">dias seguidos</span></div>
    <div class="kpi"><span class="big num"><?= pts($balance) ?></span><span class="lbl">saldo de pontos</span></div>
    <div class="kpi"><span class="big num"><?= pts($monthPts) ?></span><span class="lbl">pontos no mês</span></div>
    <div class="kpi"><span class="big num"><?= $rank && $rank['pts'] > 0 ? '#' . (int) $rank['pos'] : '-' ?></span><span class="lbl">ranking do mês</span></div>
    <div class="kpi"><span class="big num"><?= (int) $workoutsMonth ?></span><span class="lbl">treinos no mês</span></div>
    <div class="kpi"><span class="big num" style="font-size:22px"><?= $lastCheckin ? e(fmt_date($lastCheckin)) : '-' ?></span><span class="lbl">último check-in</span></div>
</div>

<div class="cols">
<div>
    <form class="panel" method="post" action="<?= e(url('/admin/pacientes/' . $id)) ?>">
        <?= csrf_field() ?>
        <h3>Dados</h3>
        <div class="form-grid">
            <div class="field"><label for="name">Nome</label><input class="input" id="name" name="name" maxlength="120" value="<?= e($p['name']) ?>" required></div>
            <div class="field"><label for="email">E-mail</label><input class="input" type="email" id="email" name="email" value="<?= e($p['email']) ?>" required></div>
            <div class="field"><label for="phone">WhatsApp</label><input class="input" id="phone" name="phone" inputmode="tel" value="<?= e($p['phone'] ?? '') ?>"></div>
            <div class="field"><label for="goal">Objetivo</label><input class="input" id="goal" name="goal" maxlength="120" value="<?= e($p['goal'] ?? '') ?>"></div>
            <div class="field"><label for="renewal_date">Vence em</label><input class="input" type="date" id="renewal_date" name="renewal_date" value="<?= e(substr((string) $p['renewal_date'], 0, 10)) ?>"></div>
            <div class="field"><label for="status">Acesso</label>
                <select id="status" name="status">
                    <option value="active"<?= $p['status'] === 'active' ? ' selected' : '' ?>>Ativo</option>
                    <option value="inactive"<?= $p['status'] === 'inactive' ? ' selected' : '' ?>>Desativado (não consegue entrar)</option>
                </select></div>
        </div>
        <div class="form-actions"><button class="btn btn-sm" type="submit">Salvar dados</button></div>
    </form>

    <div class="panel">
        <h3>Renovar acompanhamento</h3>
        <p class="muted small">Soma os dias a partir do vencimento atual (ou de hoje, se já venceu) e lança o bônus de pontos.</p>
        <form class="search" method="post" action="<?= e(url('/admin/pacientes/' . $id . '/renovar')) ?>" data-confirm="Confirmar a renovação?">
            <?= csrf_field() ?>
            <select name="days" aria-label="Período">
                <?php foreach ([30 => '+30 dias', 60 => '+60 dias', 90 => '+90 dias', 180 => '+180 dias', 365 => '+365 dias'] as $d => $label): ?>
                    <option value="<?= $d ?>"<?= $d === 90 ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm" type="submit">Renovar</button>
        </form>
        <?php if ($renewals): ?>
            <div class="tbl-wrap"><table class="tbl" style="min-width:0">
                <thead><tr><th>Quando</th><th>De</th><th>Até</th></tr></thead>
                <tbody>
                <?php foreach ($renewals as $r): ?>
                    <tr><td><?= e(fmt_date($r['created_at'])) ?></td><td><?= e(fmt_date($r['previous_date'])) ?></td><td><?= e(fmt_date($r['new_date'])) ?> <span class="tag">+<?= (int) $r['days_added'] ?> d</span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>

    <form class="panel" method="post" action="<?= e(url('/admin/pacientes/' . $id . '/dieta')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <h3>Dieta</h3>
        <div class="field"><label for="dtitle">Título</label><input class="input" id="dtitle" name="title" maxlength="120" value="<?= e($diet['title'] ?? 'Plano alimentar') ?>"></div>
        <div class="field"><label for="dlink">Link do WebDiet (ou outro)</label><input class="input" type="url" id="dlink" name="link_url" maxlength="500" value="<?= e($diet['link_url'] ?? '') ?>" placeholder="https://..."></div>
        <div class="field"><label for="dpdf">PDF da dieta</label><input class="input" type="file" id="dpdf" name="pdf" accept="application/pdf">
            <?php if (!empty($diet['pdf_file'])): ?><p class="hint">Já existe um PDF enviado. Enviar outro substitui o atual.</p><?php endif; ?></div>
        <div class="field"><label for="dnotes">Recados para o paciente</label><textarea class="input" id="dnotes" name="notes" style="min-height:80px"><?= e($diet['notes'] ?? '') ?></textarea></div>
        <div class="form-actions">
            <button class="btn btn-sm" type="submit">Salvar dieta</button>
            <?php if (!empty($diet['pdf_file'])): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/media/dieta/' . $diet['pdf_file'])) ?>" target="_blank" rel="noopener">Ver PDF</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if (!empty($diet['pdf_file'])): ?>
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/dieta/pdf-remover')) ?>" data-confirm="Remover o PDF da dieta?" style="margin:-6px 0 18px">
            <?= csrf_field() ?><button class="link-btn danger small" type="submit">Remover PDF</button>
        </form>
    <?php endif; ?>
</div>

<div>
    <div class="panel">
        <h3>Pontos</h3>
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/pontos')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="ptype">O que lançar</label>
                <select id="ptype" name="type">
                    <option value="manual">Ajuste manual (informe os pontos)</option>
                    <option value="referral">Indicação que virou paciente (valor da regra)</option>
                </select>
            </div>
            <div class="form-grid">
                <div class="field"><label for="ppts">Pontos (use - para tirar)</label><input class="input" type="number" id="ppts" name="points" step="1" min="-5000" max="5000" placeholder="Ex.: 50"></div>
                <div class="field"><label for="pnote">Motivo</label><input class="input" id="pnote" name="note" maxlength="200" placeholder="Ex.: Desafio do mês"></div>
            </div>
            <label class="checkbox small mb"><input type="checkbox" name="counts_ranking" value="1"><span>Contar também no ranking do mês (só vale para ajustes positivos)</span></label>
            <button class="btn btn-sm" type="submit">Lançar no extrato</button>
        </form>
        <?php if ($ledger): ?>
        <div class="tbl-wrap mt"><table class="tbl" style="min-width:0">
            <thead><tr><th>Quando</th><th>Motivo</th><th class="r">Pontos</th></tr></thead>
            <tbody>
            <?php foreach ($ledger as $l): ?>
                <tr><td><?= e(fmt_date($l['created_at'])) ?></td><td><?= e($l['label']) ?><?= $l['note'] ? '<br><span class="small muted">' . e($l['note']) . '</span>' : '' ?></td>
                    <td class="r num <?= $l['points'] >= 0 ? 'pos' : 'neg' ?>"><?= $l['points'] >= 0 ? '+' : '−' ?><?= pts(abs($l['points'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="row-between"><h3 style="margin:0">Treinos deste paciente</h3>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/treinos/novo?user_id=' . $id)) ?>">Novo treino</a></div>
        <?php if ($workouts): ?>
            <div class="tbl-wrap mt"><table class="tbl" style="min-width:0"><tbody>
                <?php foreach ($workouts as $w): ?>
                    <tr><td><a href="<?= e(url('/admin/treinos/' . $w['id'])) ?>"><?= e($w['title']) ?></a></td>
                        <td><?= $w['weekday'] ? e($weekdays[(int) $w['weekday']]) : '-' ?></td>
                        <td><?= $w['active'] ? '<span class="tag ok">ativo</span>' : '<span class="tag">inativo</span>' ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><p class="muted small mt" style="margin-bottom:0">Nenhum treino individual. Treinos gerais (para todos) aparecem para ele também.</p><?php endif; ?>
    </div>

    <?php if ($measures): ?>
    <div class="panel">
        <h3>Medidas registradas</h3>
        <div class="tbl-wrap"><table class="tbl" style="min-width:0">
            <thead><tr><th>Data</th><th class="r">Peso</th><th class="r">Cintura</th></tr></thead>
            <tbody>
            <?php foreach ($measures as $m): ?>
                <tr><td><?= e(fmt_date($m['measured_on'])) ?></td><td class="r num"><?= e(number_format((float) $m['weight_kg'], 1, ',', '.')) ?> kg</td>
                    <td class="r num"><?= $m['waist_cm'] !== null ? e(number_format((float) $m['waist_cm'], 1, ',', '.')) . ' cm' : '-' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <div class="panel">
        <h3>Acesso e privacidade</h3>
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/senha')) ?>" data-confirm="Gerar uma nova senha provisória? A senha atual deixa de funcionar.">
            <?= csrf_field() ?>
            <p class="muted small">Esqueceu a senha? Gere uma provisória; o paciente escolhe uma nova ao entrar.</p>
            <button class="btn btn-ghost btn-sm" type="submit">Gerar nova senha provisória</button>
        </form>
        <hr style="border:0;border-top:1px solid var(--line);margin:20px 0">
        <form method="post" action="<?= e(url('/admin/pacientes/' . $id . '/excluir')) ?>" data-confirm="Excluir DEFINITIVAMENTE este paciente e todos os dados dele? Não dá para desfazer.">
            <?= csrf_field() ?>
            <p class="muted small">Exclusão definitiva (pedido de exclusão pela LGPD): apaga cadastro, pontos, publicações, medidas, dieta e fotos.</p>
            <label class="checkbox small mb"><input type="checkbox" name="confirm" value="1" required><span>Entendo que não dá para desfazer.</span></label>
            <button class="btn btn-ghost btn-danger btn-sm" type="submit">Excluir paciente e dados</button>
        </form>
        <p class="hint">Consentimento: <?= $p['consent_at'] ? 'aceito em ' . e(fmt_datetime($p['consent_at'])) . ' (versão ' . e($p['consent_version']) . ')' : 'ainda não aceito' ?>.</p>
    </div>
</div>
</div>
