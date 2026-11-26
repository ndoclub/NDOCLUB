<?php
/** Ajustes da Jornada do paciente (Atualizações 11 e 12). Os números padrão são os do documento do Gabriel. */
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
        <h3>Prazos da Entrada</h3>
        <div class="form-grid">
            <div class="field"><label for="d_entry">Grupo, Welts e cadastro de atleta: dias depois da Entrada aberta</label><input class="input" type="number" id="d_entry" name="d_entry" min="0" max="7" value="<?= e($v('d_entry', $cfg['d_entry'])) ?>" required>
                <p class="hint">Documento do Gabriel: até 24h depois do cadastro (1 dia).</p></div>
            <div class="field"><label for="d_agenda">Agendar a 1ª consulta: dias depois da Entrada aberta</label><input class="input" type="number" id="d_agenda" name="d_agenda" min="0" max="14" value="<?= e($v('d_agenda', $cfg['d_agenda'])) ?>" required>
                <p class="hint">O documento diz "a definir"; 2 é só um ponto de partida. O plano (1 dia útil) e o treino (2 dias úteis) contam a partir da consulta; fim de semana não conta, feriado não é descontado.</p></div>
        </div>
    </div>

    <div class="panel">
        <h3>Quem faz cada tarefa</h3>
        <p class="muted small" style="margin-top:0">É o nome que aparece na tarefa. Escolha alguém da lista (os Responsáveis cadastrados) ou escreva outro nome. Mudar aqui muda também as tarefas abertas que ninguém passou para outra pessoa. Por enquanto a equipe não entra no sistema com login próprio: cada um filtra pelo seu nome na Jornada.</p>
        <datalist id="team-names"><?php foreach ($team as $n): ?><option value="<?= e($n) ?>"><?php endforeach; ?></datalist>
        <div class="form-grid">
            <div class="field"><label for="who_dossie">Dossiê</label><input class="input" id="who_dossie" name="who_dossie" list="team-names" maxlength="40" value="<?= e($v('who_dossie', $cfg['who']['dossie'])) ?>" required></div>
            <div class="field"><label for="who_ultima">Última consulta</label><input class="input" id="who_ultima" name="who_ultima" list="team-names" maxlength="40" value="<?= e($v('who_ultima', $cfg['who']['ultima'])) ?>" required></div>
            <div class="field"><label for="who_contato">Contatos de renovação, agendar a 1ª consulta e recontato</label><input class="input" id="who_contato" name="who_contato" list="team-names" maxlength="40" value="<?= e($v('who_contato', $cfg['who']['contato'])) ?>" required></div>
            <div class="field"><label for="who_suporte">Sair do grupo</label><input class="input" id="who_suporte" name="who_suporte" list="team-names" maxlength="40" value="<?= e($v('who_suporte', $cfg['who']['suporte'])) ?>" required></div>
            <div class="field"><label for="who_grupo">Grupo no WhatsApp e Welts (Entrada)</label><input class="input" id="who_grupo" name="who_grupo" list="team-names" maxlength="40" value="<?= e($v('who_grupo', $cfg['who']['grupo'])) ?>" required></div>
            <div class="field"><label for="who_consulta">Consultas, treino e liberar o plano (Entrada)</label><input class="input" id="who_consulta" name="who_consulta" list="team-names" maxlength="40" value="<?= e($v('who_consulta', $cfg['who']['consulta'])) ?>" required></div>
            <div class="field"><label for="who_plano">Montar o plano e a dieta (Entrada)</label><input class="input" id="who_plano" name="who_plano" list="team-names" maxlength="40" value="<?= e($v('who_plano', $cfg['who']['plano'])) ?>" required>
                <p class="hint">Quem estiver disponível pode pegar: use "Passar…" na tarefa.</p></div>
            <div class="field"><label for="who_atleta">Cadastro de atleta (Entrada)</label><input class="input" id="who_atleta" name="who_atleta" list="team-names" maxlength="40" value="<?= e($v('who_atleta', $cfg['who']['atleta'])) ?>" required></div>
            <div class="field full"><label class="checkbox"><input type="checkbox" name="athlete_on" value="1"<?= (!empty($GLOBALS['NDO_OLD']) ? !empty(old('athlete_on')) : $cfg['athlete_on']) ? ' checked' : '' ?>><span>Atletas com a pessoa acima (ligado)</span></label>
                <p class="hint">Desligado: o cadastro de atleta segue a regra de cuidado (o Responsável da ficha, ou o plantão), como as outras tarefas de cuidado.</p></div>
        </div>
        <p class="hint">A <strong>confirmação D+1</strong> (e, depois, os contatos do Modo Babá) não têm nome fixo: vão para o Responsável da ficha do paciente; sem ele, para o plantão da semana (definido na própria Jornada); sem plantão, ficam sem responsável e aparecem para todos.</p>
    </div>

    <div class="form-actions"><button class="btn" type="submit">Salvar ajustes</button></div>
</form>
