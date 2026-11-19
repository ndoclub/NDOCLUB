<div class="page-head"><h2>Ranking do mês</h2>
    <form method="get" action="<?= e(url('/admin/ranking')) ?>" class="row">
        <label class="sr-only" for="m">Mês</label>
        <select id="m" name="m" data-autosubmit style="width:auto">
            <?php foreach ($months as $m): ?><option value="<?= e($m) ?>"<?= $m === $ym ? ' selected' : '' ?>><?= e(month_name((int) substr($m, 5, 2)) . ' de ' . substr($m, 0, 4)) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-ghost btn-sm" type="submit">Ver</button></noscript>
    </form>
</div>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>#</th><th>Paciente</th><th class="r">Pontos no mês</th><th class="r">Saldo da loja</th><th>Nível</th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): if ($r['pts'] <= 0) { continue; } ?>
        <tr>
            <td class="num"><strong><?= (int) $r['pos'] ?></strong></td>
            <td><a class="who" href="<?= e(url('/admin/pacientes/' . $r['id'])) ?>"><?= avatar($r, 34) ?><?= e($r['name']) ?></a></td>
            <td class="r num"><?= pts($r['pts']) ?></td>
            <td class="r num"><?= pts($r['balance']) ?></td>
            <td><?= e($r['level']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
