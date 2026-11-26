<?php
$isEdit = (bool) $plan;
$action = $isEdit ? '/admin/planos/' . (int) $plan['id'] : '/admin/planos';
$val = fn($k, $d = '') => old($k, $plan[$k] ?? $d);
$price = old('price', ($plan['price'] ?? null) !== null ? number_format((float) $plan['price'], 2, ',', '') : '');
?>
<div class="page-head"><h2><?= $isEdit ? 'Editar plano' : 'Novo plano' ?></h2></div>
<form class="panel" method="post" action="<?= e(url($action)) ?>" style="max-width:640px">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field full"><label for="name">Nome do plano</label><input class="input" id="name" name="name" maxlength="60" value="<?= e($val('name')) ?>" placeholder="Ex.: Trimestral" required></div>
        <div class="field"><label for="duration_days">Duração (dias)</label><input class="input" type="number" id="duration_days" name="duration_days" min="7" max="1100" value="<?= e($val('duration_days', '90')) ?>" required>
            <p class="hint">Trimestral 90, semestral 180, anual 365. É o que a renovação soma.</p></div>
        <div class="field"><label for="price">Valor (R$)</label><input class="input" id="price" name="price" inputmode="decimal" value="<?= e($price) ?>" placeholder="Ex.: 450,00">
            <p class="hint">Só o admin vê. Deixe em branco se ainda não definiu.<?= $isEdit ? ' Se você mudar o valor, quem já está no plano mantém o valor antigo até a próxima renovação.' : '' ?></p></div>
        <div class="field"><label for="consultations_included">Consultas presenciais incluídas</label><input class="input" type="number" id="consultations_included" name="consultations_included" min="0" max="60" inputmode="numeric" value="<?= e(old('consultations_included', ($plan['consultations_included'] ?? null) !== null ? (string) (int) $plan['consultations_included'] : '')) ?>" placeholder="Ex.: 4">
            <p class="hint">Quantas consultas presenciais o plano dá. A lista de pacientes mostra "2 de 4". Em branco = ainda não definido.</p></div>
        <div class="field full"><label for="journey_type">Jornada do paciente</label>
            <select id="journey_type" name="journey_type">
                <?php $jt = \App\Services\Journey::typeOf(old('journey_type', $plan['journey_type'] ?? 'premium')); foreach (\App\Services\Journey::TYPES as $k => $lab): ?>
                    <option value="<?= e($k) ?>"<?= $jt === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Define as tarefas de renovação deste plano. <strong>Premium e Smart</strong>: dossiê 7 dias antes da última consulta e resposta na última consulta (o intervalo entre consultas sai de duração ÷ consultas; preencha as consultas incluídas). <strong>Escalável</strong>: sem consultas, contato de renovação 15 dias antes do fim. <strong>Mensal</strong>: sem dossiê, mensagem perguntando se quer entrar em um plano 15 dias antes do fim.</p></div>
        <div class="field full"><label class="checkbox"><input type="checkbox" name="with_training" value="1"<?= !empty(old('with_training', $plan['with_training'] ?? 0)) ? ' checked' : '' ?>><span>Plano com treino (o Gabriel monta o treino)</span></label>
            <p class="hint">Na Entrada do paciente novo, cria a tarefa "Treino montado" para o Gabriel, em até 2 dias úteis depois da 1ª consulta (fim de semana não conta).</p></div>
        <div class="field full"><label class="checkbox"><input type="checkbox" name="active" value="1"<?= (!$isEdit || $plan['active']) ? ' checked' : '' ?>><span>Ativo (aparece ao escolher o plano de um paciente)</span></label>
            <p class="hint">Planos não são apagados: desative para esconder, e os pacientes que já estão nele continuam normais.</p></div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Salvar plano</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/planos')) ?>">Cancelar</a>
    </div>
</form>
