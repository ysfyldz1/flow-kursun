<?php
/**
 * Yatay çubuk sıralaması (HTML). $items: [['label', 'value', 'url'?, 'sub'?]], $fmt: değer biçimi.
 * Tek seri: renk tek, değer çubuğun ucunda metin renginde; ölçek en büyük değere göre.
 */
$max = max(array_column($items, 'value') ?: [0]) ?: 1;
?>
<?php if (!$items || !array_filter(array_column($items, 'value'))): ?>
    <div class="empty"><?= e(__('dash.no_data')) ?></div>
<?php else: ?>
    <div class="viz-bars">
        <?php foreach ($items as $it): $tag = !empty($it['url']) ? 'a' : 'div'; ?>
            <<?= $tag ?> class="viz-bar-row"<?= $tag === 'a' ? ' href="' . e($it['url']) . '"' : '' ?> data-tip="<?= e('<strong>' . e($it['label']) . '</strong><span>' . e($fmt($it['value'])) . (isset($it['sub']) ? ' · ' . e($it['sub']) : '') . '</span>') ?>">
                <span class="viz-bar-label"><?= e($it['label']) ?></span>
                <span class="viz-bar-track">
                    <span class="viz-bar-fill viz-s1" style="width: calc((100% - 72px) * <?= round(max(0.005, $it['value'] / $max), 4) ?>)"></span>
                    <span class="viz-bar-value"><?= e($fmt($it['value'])) ?></span>
                </span>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
