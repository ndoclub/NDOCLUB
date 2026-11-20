<?php
$bands = \App\Services\Payments::bands();
$fmt = fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
?>
<div class="page-head">
    <h2>Taxas do cartão</h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/financeiro')) ?>">← Voltar ao Financeiro</a>
</div>
<p class="muted small">Percentual que a maquininha ou operadora desconta de <strong>cada parcela</strong>, por bandeira e faixa de parcelas (use os números do contrato do Gabriel). Ao vender no cartão, o sistema preenche a taxa sozinho (dá para corrigir na venda) e as parcelas entram no Financeiro pelo <strong>valor líquido</strong>. Campo em branco = sem taxa. Mudar aqui vale para as próximas vendas; as já registradas não mudam. Só o admin vê.</p>

<form class="panel" method="post" action="<?= e(url('/admin/financeiro/taxas')) ?>" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="tbl-wrap">
        <table class="tbl" style="min-width:0">
            <thead><tr><th>Bandeira</th><?php foreach ($bands as $label): ?><th class="r"><?= e($label) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach (\App\Services\Payments::brands() as $brand => $name): ?>
                <tr>
                    <td><strong><?= e($name) ?></strong></td>
                    <?php foreach ($bands as $band => $label): ?>
                        <td class="r"><input class="input fee-input" name="fee[<?= e($brand) ?>][<?= (int) $band ?>]" inputmode="decimal" value="<?= e($fmt($table[$brand][$band] ?? null)) ?>" placeholder="0" aria-label="<?= e($name . ' ' . $label . ' (%)') ?>"> <span class="muted small">%</span></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="hint">Exemplo: 3,5 significa que de cada R$ 100,00 de parcela, R$ 3,50 ficam com a operadora e caem R$ 96,50.</p>
    <div class="form-actions"><button class="btn" type="submit">Salvar taxas</button></div>
</form>
