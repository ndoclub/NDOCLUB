<?php
/** Ajustes da Jornada do paciente (Atualização 11). Os números padrão são os do documento do Gabriel. */
$v = fn(string $k, $d) => old($k, (string) $d);
?>
<div class="page-head"><h2>Ajustes da Jornada</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/jornada')) ?>">Voltar à Jornada</a></div>
<form method="post" action="<?= e(url('/admin/jornada/ajustes')) ?>" style="max-width:760px">
    <?= csrf_field() ?>

    <div class="panel">
        <h3>Quando a Jornada começa a valer</h3>
        <div class="form-grid">
            <div class="field"><label for="start">Início da Jornada</label><input class="input" type="date" id="start" name="start" value="<?= e($v('start', $cfg['start'])) ?>" required>
                <p class="hint">O que venceu <strong>antes</strong> deste dia vira "anterior ao sistema" e não conta como tarefa atrasada. Para cobrar renovações que já estavam em andamento, escolha uma data mais antiga.</p></div>
            <div class="field"><label for="window">Aba Renovações: mostrar quem vence em até (dias)</label><input class="input" type="number" id="window" name="window" min="15" max="365" value="<?= e($v('window', $cfg['window'])) ?>" required>
                <p class="hint">Só muda o que aparece na lista; não muda as tarefas.</p></div>
        </div>
    </div>

    <div class="panel">
        <h3>Prazos (do documento do Gabriel)</h3>
        <div class="form-grid">
            <div class="field"><label for="d_dossie">Dossiê pronto, dias antes da última consulta</label><input class="input" type="number" id="d_dossie" name="d_dossie" min="0" max="60" value="<?= e($v('d_dossie', $cfg['d_dossie'])) ?>" required></div>
            <div class="field"><label for="d_contato">Contato de renovação, dias antes do prazo</label><input class="input" type="number" id="d_contato" name="d_contato" min="0" max="90" value="<?= e($v('d_contato', $cfg['d_contato'])) ?>" required>
                <p class="hint">Vale para "Vai pensar" (antes da consulta seguinte), Escalável e Mensal (antes do fim do plano).</p></div>
            <div class="field"><label for="d_pausa">Recontato da pausa, dias depois de pausar</label><input class="input" type="number" id="d_pausa" name="d_pausa" min="1" max="365" value="<?= e($v('d_pausa', $cfg['d_pausa'])) ?>" required></div>
        </div>
        <div class="form-grid">
            <div class="field"><label for="raise_min">Reajuste de referência: de (%)</label><input class="input" type="number" id="raise_min" name="raise_min" min="0" max="100" value="<?= e($v('raise_min', $cfg['raise_min'])) ?>" required></div>
            <div class="field"><label for="raise_max">até (%)</label><input class="input" type="number" id="raise_max" name="raise_max" min="0" max="100" value="<?= e($v('raise_max', $cfg['raise_max'])) ?>" required>
                <p class="hint">Aparece na ficha de quem paga abaixo da tabela. É só uma referência: o valor final é você quem define.</p></div>
        </div>
    </div>

    <div class="panel">
        <h3>Quem faz cada tarefa</h3>
        <p class="muted small" style="margin-top:0">É só o nome que aparece na tarefa (por enquanto a equipe não entra no sistema com login próprio). Mudar aqui muda também as tarefas que já estão abertas.</p>
        <div class="form-grid">
            <div class="field"><label for="who_dossie">Dossiê</label><input class="input" id="who_dossie" name="who_dossie" maxlength="40" value="<?= e($v('who_dossie', $cfg['who']['dossie'])) ?>" required></div>
            <div class="field"><label for="who_ultima">Última consulta</label><input class="input" id="who_ultima" name="who_ultima" maxlength="40" value="<?= e($v('who_ultima', $cfg['who']['ultima'])) ?>" required></div>
            <div class="field"><label for="who_contato">Contatos de renovação, registro da renovação e recontato</label><input class="input" id="who_contato" name="who_contato" maxlength="40" value="<?= e($v('who_contato', $cfg['who']['contato'])) ?>" required></div>
            <div class="field"><label for="who_suporte">Sair do grupo</label><input class="input" id="who_suporte" name="who_suporte" maxlength="40" value="<?= e($v('who_suporte', $cfg['who']['suporte'])) ?>" required></div>
        </div>
    </div>

    <div class="form-actions"><button class="btn" type="submit">Salvar ajustes</button></div>
</form>
