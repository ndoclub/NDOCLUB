<?php /* Selo de renovação: $renew = Renewal::info(); $word = true mostra só a palavra (Em dia / Atenção / Urgente) */ ?>
<span class="seal seal-<?= e($renew['seal']) ?>"><i class="dot"></i><?= e(!empty($word) ? $renew['word'] : $renew['label']) ?></span>
