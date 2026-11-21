<?php
/** Tabela de aniversariantes. Recebe: $rows (de Birthdays::between, com 'wa'). Só o admin vê. */
?>
<div class="tbl-wrap">
    <table class="tbl bd-tbl" style="min-width:0">
        <thead><tr><th>Paciente</th><th>Aniversário</th><th class="r">Ação</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $d = (int) $r['days'];
            $when = $d === 0 ? 'hoje' : ($d === 1 ? 'amanhã' : ($d > 0 ? 'em ' . $d . ' dias' : 'há ' . abs($d) . ($d === -1 ? ' dia' : ' dias')));
            ?>
            <tr>
                <td><a class="who" href="<?= e(url('/admin/pacientes/' . (int) $r['id'])) ?>"><?= avatar($r, 34) ?><span><?= e($r['name']) ?></span></a>
                    <span class="bd-sub small muted">faz <?= (int) $r['age'] ?> anos · <?= !empty($r['msg_optin']) ? '<span class="tag ok">autorizou mensagens</span>' : 'autorização não registrada' ?></span></td>
                <td><strong><?= e(substr((string) $r['date'], 8, 2) . '/' . substr((string) $r['date'], 5, 2)) ?></strong><br>
                    <span class="tag<?= $d === 0 ? ' ok' : '' ?>"><?= e($when) ?></span></td>
                <td class="r">
                    <?php if (!empty($r['wa'])): ?>
                        <a class="btn btn-sm" href="<?= e($r['wa']) ?>" target="_blank" rel="noopener">Dar parabéns</a>
                    <?php else: ?>
                        <span class="small muted">sem WhatsApp</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
