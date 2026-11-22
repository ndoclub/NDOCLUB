<?php
/**
 * Campos de dados pessoais do paciente (Atualização 08.1): sexo, CPF, Instagram e o bloco "Endereço".
 * Usado no cadastro e na ficha. Tudo opcional e só o admin vê.
 * Recebe: $v (array com os valores atuais, chaves = PersonalData::COLUMNS).
 */

use App\Services\PersonalData;

$v = $v ?? [];
$val = fn (string $k): string => (string) ($v[$k] ?? '');
$cpfShown = PersonalData::fmtCpf($val('cpf'));
$zipShown = PersonalData::fmtZip($val('addr_zip'));
$addrOpen = PersonalData::hasAddress($v);
$igUser = $val('instagram');
?>
<div class="field"><label for="sex">Sexo</label>
    <select id="sex" name="sex">
        <option value="">Não informado</option>
        <?php foreach (PersonalData::sexChoices() as $code => $label): ?>
            <option value="<?= e($code) ?>"<?= $val('sex') === $code ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <p class="hint">Alimenta o gráfico da carteira no painel e ajuda na compra dos brindes. Só você vê.</p></div>
<div class="field"><label for="cpf">CPF</label><input class="input" id="cpf" name="cpf" inputmode="numeric" maxlength="14" autocomplete="off" value="<?= e($cpfShown) ?>" placeholder="000.000.000-00">
    <p class="hint">Opcional. Usado para emitir recibo e nota fiscal. Só você vê.</p></div>
<div class="field"><label for="instagram">Instagram</label><input class="input" id="instagram" name="instagram" maxlength="90" autocomplete="off" value="<?= e($igUser !== '' ? '@' . $igUser : '') ?>" placeholder="@usuario">
    <p class="hint">Cole o @ ou o link do perfil.<?php if ($igUser !== ''): ?> <a href="<?= e(PersonalData::instagramUrl($igUser)) ?>" target="_blank" rel="noopener noreferrer">Abrir perfil</a><?php endif; ?></p></div>
<details class="addr full"<?= $addrOpen ? ' open' : '' ?>>
    <summary>Endereço completo <span class="muted small">(para nota fiscal e envio de brindes)</span></summary>
    <div class="form-grid" style="margin-top:14px">
        <div class="field"><label for="addr_zip">CEP</label><input class="input" id="addr_zip" name="addr_zip" inputmode="numeric" maxlength="12" autocomplete="off" value="<?= e($zipShown) ?>" placeholder="01310-100"></div>
        <div class="field"><label for="addr_street">Rua / avenida</label><input class="input" id="addr_street" name="addr_street" maxlength="120" value="<?= e($val('addr_street')) ?>"></div>
        <div class="field"><label for="addr_number">Número</label><input class="input" id="addr_number" name="addr_number" maxlength="20" value="<?= e($val('addr_number')) ?>"></div>
        <div class="field"><label for="addr_complement">Complemento</label><input class="input" id="addr_complement" name="addr_complement" maxlength="80" value="<?= e($val('addr_complement')) ?>" placeholder="Apto, bloco..."></div>
        <div class="field"><label for="addr_district">Bairro</label><input class="input" id="addr_district" name="addr_district" maxlength="80" value="<?= e($val('addr_district')) ?>"></div>
        <div class="field"><label for="addr_city">Cidade</label><input class="input" id="addr_city" name="addr_city" maxlength="80" value="<?= e($val('addr_city')) ?>"></div>
        <div class="field"><label for="addr_state">Estado</label><input class="input" id="addr_state" name="addr_state" maxlength="40" list="ufs" autocomplete="off" value="<?= e($val('addr_state')) ?>" placeholder="SP">
            <datalist id="ufs"><?php foreach (PersonalData::states() as $uf): ?><option value="<?= e($uf) ?>"></option><?php endforeach; ?></datalist></div>
        <div class="field"><label for="addr_country">País</label><input class="input" id="addr_country" name="addr_country" maxlength="60" value="<?= e($val('addr_country')) ?>" placeholder="Brasil">
            <p class="hint">Em branco = Brasil. Para quem mora fora, escreva o país; CEP e estado ficam livres.</p></div>
    </div>
</details>
