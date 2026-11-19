<div class="page-head"><h2>Regras de pontos</h2></div>
<p class="muted small" style="max-width:640px">Aqui você decide quanto vale cada ação. O <strong>limite por dia</strong> é o máximo de vezes que a mesma pessoa pode ganhar pontos naquela ação em um dia (0 = sem limite). Mudar uma regra vale daqui para a frente: o extrato antigo não muda.</p>
<form method="post" action="<?= e(url('/admin/pontos')) ?>">
    <?= csrf_field() ?>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Ação</th><th>Pontos</th><th>Limite por dia</th><th>Ativa</th></tr></thead>
        <tbody>
        <?php foreach ($rules as $r): $k = $r['rkey']; ?>
            <tr>
                <td><strong><?= e($r['label']) ?></strong></td>
                <td><input class="input" type="number" min="0" max="100000" name="points[<?= e($k) ?>]" value="<?= (int) $r['points'] ?>" aria-label="Pontos de <?= e($r['label']) ?>"></td>
                <td><input class="input" type="number" min="0" max="1000" name="limit[<?= e($k) ?>]" value="<?= (int) $r['daily_limit'] ?>" aria-label="Limite diário de <?= e($r['label']) ?>"></td>
                <td><label class="checkbox"><input type="checkbox" name="active[<?= e($k) ?>]" value="1"<?= $r['active'] ? ' checked' : '' ?> aria-label="Ativa"><span class="sr-only">Ativa</span></label></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="form-actions"><button class="btn" type="submit">Salvar regras</button></div>
</form>
