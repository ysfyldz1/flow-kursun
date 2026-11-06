<?php
// Kurumun ana rengini CSS değişkenlerine uygular; açık tonu otomatik türetir.
$hex = (string) \App\Services\Settings::get('org.primary_color', '#1e3a8a');
if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)):
    [$r, $g, $b] = [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
    $soft = sprintf('rgb(%d,%d,%d)', $r + (255 - $r) * .9, $g + (255 - $g) * .9, $b + (255 - $b) * .9);
?>
<style>:root { --primary: <?= $hex ?>; --primary-soft: <?= $soft ?>; }</style>
<?php endif; ?>
