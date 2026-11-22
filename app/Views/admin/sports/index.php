<div class="page-head">
    <h2>Modalidades</h2>
    <a class="btn btn-sm" href="<?= e(url('/admin/modalidades/novo')) ?>"><?= icon('plus', 18) ?> Nova modalidade</a>
</div>
<p class="muted small">São as etiquetas esportivas que aparecem na ficha dos pacientes atletas (Corrida, Musculação, Hyrox...). Uma lista fixa evita "corrida" e "Corrida" virarem coisas diferentes. Modalidades não são apagadas: desative para esconder das novas escolhas, e quem já tem continua normal.</p>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Modalidade</th><th class="r">Atletas</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sports as $s): ?>
        <tr<?= $s['active'] ? '' : ' class="tag-soft"' ?>>
            <td><strong><?= e($s['name']) ?></strong><?= $s['active'] ? '' : ' <span class="muted small">(desativada)</span>' ?></td>
            <td class="r num"><?= (int) $s['athletes'] ?></td>
            <td class="r"><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/modalidades/' . $s['id'])) ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
