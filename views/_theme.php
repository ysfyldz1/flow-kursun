<?php
// Kurumun ana rengini CSS değişkenlerine uygular; açık tonu otomatik türetir.
$hex = (string) \App\Services\Settings::get('org.primary_color', '#1e3a8a');
if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)):
    [$r, $g, $b] = [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
    $soft = sprintf('rgb(%d,%d,%d)', $r + (255 - $r) * .9, $g + (255 - $g) * .9, $b + (255 - $b) * .9);
    // Kurum renginin üstündeki yazı: WCAG göreli parlaklığına göre beyazla mı koyuyla mı kontrast daha yüksek
    $lin = fn (int $c) => ($c /= 255) <= .03928 ? $c / 12.92 : (($c + .055) / 1.055) ** 2.4;
    $lum = .2126 * $lin($r) + .7152 * $lin($g) + .0722 * $lin($b);
    $onPrimary = (1.05 / ($lum + .05)) >= (($lum + .05) / (0.0137 + .05)) ? '#ffffff' : '#0f172a';
?>
<style>:root { --primary: <?= $hex ?>; --primary-soft: <?= $soft ?>; --on-primary: <?= $onPrimary ?>; }</style>
<?php endif; ?>
