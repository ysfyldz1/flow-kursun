<?php
/**
 * Dikey sütun grafiği (SVG). $rows: [x-etiketi => [seri => değer]], $series: [seri => etiket] (sıra = renk sırası),
 * $stacked: yığılmış mı (değilse yan yana), $fmt: değer biçimi (ipucu ve eksen), $caption: tablo başlığı.
 * Sütun ucu 4px yuvarlak, taban düz; yığında dilimler arası 2px boşluk; üzerine gelince ipucu.
 * $legend = false: yalnız SVG (telefon için ikinci, kısaltılmış kopya; açıklama ve tablo asıl grafikte).
 */
$legend ??= true;
$W = $width ?? 640; $H = 230; $padL = 52; $padR = 8; $padT = 10; $padB = 26;
$plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
$keys = array_keys($series);
$max = 0;
foreach ($rows as $v) {
    $max = max($max, $stacked ? array_sum($v) : max($v ?: [0]));
}
// Eksen üst sınırı: 1-2-5 adımlarıyla yuvarlak, 4 aralık
$step = 1;
if ($max > 0) {
    $raw = $max / 4;
    $mag = 10 ** floor(log10($raw));
    foreach ([1, 2, 2.5, 5, 10] as $m) {
        if ($m * $mag >= $raw) { $step = $m * $mag; break; }
    }
}
$top = $step * 4;
$y = fn (float $v) => $padT + $plotH - ($v / $top) * $plotH;
$band = $plotW / max(1, count($rows));
$barW = min(36, $band * ($stacked ? 0.56 : 0.34));
$tick = fn (float $v) => $v >= 1000 ? rtrim(rtrim(number_format($v / 1000, 1, ',', ''), '0'), ',') . __('dash.thousand') : rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
// Üstü yuvarlak, altı düz dikdörtgen
$bar = function (float $x, float $y0, float $w, float $h, bool $round) {
    if ($h <= 0) return '';
    $r = $round ? min(4, $h, $w / 2) : 0;
    return sprintf('M%.1f %.1fv%.1fq0 -%.1f %.1f -%.1fh%.1fq%.1f 0 %.1f %.1fv%.1fz',
        $x, $y0, -($h - $r), $r, $r, $r, $w - 2 * $r, $r, $r, $r, $h - $r);
};
?>
<figure class="viz">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= e($caption) ?>">
        <?php for ($i = 0; $i <= 4; $i++): $gy = $y($step * $i); ?>
            <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= $gy ?>" y2="<?= $gy ?>" class="<?= $i ? 'viz-grid' : 'viz-axis' ?>"/>
            <text x="<?= $padL - 8 ?>" y="<?= $gy + 4 ?>" class="viz-tick" text-anchor="end"><?= e($tick($step * $i)) ?></text>
        <?php endfor; ?>
        <?php $n = 0; foreach ($rows as $label => $v): $cx = $padL + $band * $n + $band / 2; ?>
            <?php
            $tip = '<strong>' . e($xLabel($label)) . '</strong>';
            foreach ($keys as $k) {
                $tip .= '<span><i class="viz-sw viz-s' . (array_search($k, $keys) + 1) . '"></i>' . e($series[$k]) . ': ' . e($fmt($v[$k] ?? 0)) . '</span>';
            }
            ?>
            <g class="viz-col" data-tip="<?= e($tip) ?>">
                <rect x="<?= $padL + $band * $n ?>" y="<?= $padT ?>" width="<?= $band ?>" height="<?= $plotH ?>" class="viz-hit"/>
                <?php if ($stacked): $base = $padT + $plotH; $last = null;
                    foreach ($keys as $si => $k) { if (($v[$k] ?? 0) > 0) $last = $si; }
                    foreach ($keys as $si => $k):
                        $h = ($v[$k] ?? 0) / $top * $plotH;
                        if ($h <= 0) continue;
                        $gap = $base < $padT + $plotH ? 2 : 0;   // dilimler arası 2px yüzey boşluğu
                ?>
                    <path d="<?= $bar($cx - $barW / 2, $base - $gap, $barW, max(0, $h - $gap), $si === $last) ?>" class="viz-s<?= $si + 1 ?>"/>
                <?php $base -= $h; endforeach; else:
                    $gw = ($barW * count($keys) + 2 * (count($keys) - 1));
                    foreach ($keys as $si => $k): $h = ($v[$k] ?? 0) / $top * $plotH; ?>
                    <path d="<?= $bar($cx - $gw / 2 + $si * ($barW + 2), $padT + $plotH, $barW, $h, true) ?>" class="viz-s<?= $si + 1 ?>"/>
                <?php endforeach; endif; ?>
                <text x="<?= $cx ?>" y="<?= $H - 8 ?>" class="viz-tick" text-anchor="middle"><?= e($xShort($label)) ?></text>
            </g>
        <?php $n++; endforeach; ?>
    </svg>
    <?php if ($legend): ?>
    <figcaption class="viz-legend">
        <?php foreach ($keys as $si => $k): ?><span><i class="viz-sw viz-s<?= $si + 1 ?>"></i><?= e($series[$k]) ?></span><?php endforeach; ?>
        <details class="viz-table">
            <summary><?= e(__('dash.as_table')) ?></summary>
            <table class="table">
                <thead><tr><th></th><?php foreach ($keys as $k): ?><th class="num"><?= e($series[$k]) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($rows as $label => $v): ?>
                    <tr><td><?= e($xLabel($label)) ?></td><?php foreach ($keys as $k): ?><td class="num"><?= e($fmt($v[$k] ?? 0)) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    </figcaption>
    <?php endif; ?>
</figure>
