<?php
use App\Services\Settings;

// Döküm PDF'iyle (views/pdf/statement.php) aynı görünüm: kurum rengi, üst/alt bilgi, taraf kutusu.
$money = fn (float $v) => format_money($v, $currency);
$orgName = (string) Settings::get('org.name');
$hex = (string) Settings::get('org.primary_color', '#1e3a8a');
$primary = e($hex);
$tint = function (float $amount) use ($hex): string {
    if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
        return '#f1f5f9';
    }
    return sprintf('#%02x%02x%02x', ...array_map(fn ($c) => (int) round(hexdec($c) + (255 - hexdec($c)) * $amount), array_slice($m, 1)));
};
$band = $tint(.78);
$soft = $tint(.9);
$zebra = $tint(.96);
$address = trim((string) Settings::get('org.address'));
$taxInfo = trim((string) Settings::get('org.tax_info'));
$clientPlace = implode(' / ', array_filter([$c['city'] ?? null, $c['country'] ?? null]));
$line = '#cbd5e1';
// CSS uppercase Türkçe i'yi I yapar; büyük harf burada çevrilir
$upper = fn (string $s) => mb_strtoupper(\App\Core\Lang::locale() === 'tr' ? str_replace('i', 'İ', $s) : $s);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 27mm 14mm 18mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; line-height: 1.25; color: #1e293b; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .muted { color: #64748b; }
    .neg { color: #b91c1c; }

    header { position: fixed; top: -21mm; left: 0; right: 0; height: 15mm; border-bottom: 1.2pt solid <?= $primary ?>; }
    header td { vertical-align: bottom; padding-bottom: 2.5mm; }
    .logo { max-height: 11mm; max-width: 55mm; }
    .org { font-size: 12pt; font-weight: bold; color: <?= $primary ?>; }
    .doc-title { font-size: 13pt; font-weight: bold; letter-spacing: 1pt; color: #334155; }
    footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; border-top: .5pt solid <?= $line ?>; padding-top: 1.5mm; font-size: 6.5pt; color: #64748b; }

    .parties { margin-bottom: 4mm; }
    .client { width: 54%; background: <?= $soft ?>; border: .5pt solid <?= $line ?>; padding: 2.5mm 3mm; }
    .client .label { font-size: 7pt; font-weight: bold; color: <?= $primary ?>; }
    .client .name { font-size: 9pt; font-weight: bold; margin: .8mm 0; }
    .gap { width: 4mm; }
    .meta td { padding: .55mm 0; }
    .meta .k { color: #64748b; width: 30mm; }

    .lines { margin-bottom: 3.5mm; }
    .lines tr { page-break-inside: avoid; }
    .lines td:first-child { white-space: nowrap; }
    .lines th, .lines td { border: .5pt solid <?= $line ?>; padding: 1.1mm 1.4mm; }
    .lines thead th { background: <?= $soft ?>; font-size: 7pt; font-weight: bold; color: #334155; text-align: left; }
    .lines thead th.num { text-align: right; }
    .lines .band td { background: <?= $band ?>; color: <?= $primary ?>; font-weight: bold; }
    .lines .odd td { background: <?= $zebra ?>; }
    .lines .sub { color: #64748b; font-size: 7pt; }

    .summary { margin-top: 1mm; page-break-inside: avoid; }
    .totals td { border: .5pt solid <?= $line ?>; padding: 1.3mm 2mm; }
    .totals .k { background: <?= $soft ?>; font-weight: bold; font-size: 7.5pt; }
    .totals .grand td { font-size: 10pt; font-weight: bold; color: <?= $primary ?>; padding: 2mm; }
    .totals .grand .k { font-size: 8.5pt; }
    .totals .grand td.neg { color: #b91c1c; }
    .note { margin-top: 4mm; border: .5pt solid <?= $line ?>; padding: 2mm 3mm; color: #b91c1c; page-break-inside: avoid; }
</style>
</head>
<body>
<header>
    <table>
        <tr>
            <td><?php if ($logo): ?><img src="<?= $logo ?>" class="logo"><?php else: ?><span class="org"><?= e($orgName) ?></span><?php endif; ?></td>
            <td class="num"><div class="doc-title"><?= e($upper(__('adv.pdf_title'))) ?></div></td>
        </tr>
    </table>
</header>
<footer>
    <table>
        <tr>
            <td><?= e(implode(' · ', array_filter([$orgName, str_replace(["\r\n", "\n"], ', ', $address), $taxInfo]))) ?></td>
            <td style="width:26mm"></td>
        </tr>
    </table>
</footer>

<table class="parties">
    <tr>
        <td class="client">
            <div class="label"><?= e($upper(__('adv.pdf_client'))) ?></div>
            <div class="name"><?= e($c['name']) ?></div>
            <?php if (!empty($c['address'])): ?><?= nl2br(e($c['address'])) ?><br><?php endif; ?>
            <?php if ($clientPlace !== ''): ?><?= e($clientPlace) ?><br><?php endif; ?>
            <?php if (!empty($c['tax_id'])): ?><span class="muted"><?= e($c['tax_office'] ? $c['tax_office'] . ' · ' : '') ?><?= e($c['tax_id']) ?></span><?php endif; ?>
        </td>
        <td class="gap"></td>
        <td>
            <table class="meta">
                <tr><td class="k"><?= e(__('billing.period')) ?></td><td><?= e(format_date($from)) ?> – <?= e(format_date($to)) ?></td></tr>
                <tr><td class="k"><?= e(__('clients.currency')) ?></td><td><?= e($currency) ?></td></tr>
                <tr><td class="k"><?= e(__('adv.pdf_date')) ?></td><td><?= e(format_date(date('Y-m-d'))) ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th style="width:21mm"><?= e(__('adv.date')) ?></th>
            <th><?= e(__('adv.description')) ?></th>
            <th class="num" style="width:24mm"><?= e(__('adv.in')) ?></th>
            <th class="num" style="width:24mm"><?= e(__('adv.out')) ?></th>
            <th class="num" style="width:25mm"><?= e(__('adv.balance')) ?></th>
        </tr>
    </thead>
    <tbody>
        <tr class="band">
            <td><?= e(format_date($from)) ?></td>
            <td><?= e(__('adv.opening')) ?></td>
            <td></td><td></td>
            <td class="num"><?= $money($ledger['opening']) ?></td>
        </tr>
        <?php if (!$ledger['rows']): ?>
            <tr><td colspan="5" class="muted"><?= e(__('adv.empty')) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($ledger['rows'] as $i => $r): ?>
            <?php $amount = (float) $r['amount']; ?>
            <tr class="<?= $i % 2 ? 'odd' : '' ?>">
                <td><?= e(format_date($r['d'])) ?></td>
                <td>
                    <?php if ($r['kind'] === 'expense'): ?>
                        <strong><?= e(__('expenses.cat_' . $r['category'])) ?>:</strong> <?= nl2br(e($r['description'])) ?>
                        <div class="sub"><?= e($r['project_number']) ?> · <?= e($r['project_name']) ?></div>
                    <?php else: ?>
                        <strong><?= e(__('adv.type_' . $r['type'])) ?></strong>
                        <?php $sub = implode(' · ', array_filter([$r['method'] ? __('adv.method_' . $r['method']) : null, $r['reference'], $r['description']])); ?>
                        <?php if ($sub !== ''): ?><div class="sub"><?= e($sub) ?></div><?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="num"><?= $amount > 0 ? $money($amount) : '' ?></td>
                <td class="num"><?= $amount < 0 ? $money(-$amount) : '' ?></td>
                <td class="num <?= $r['balance'] < 0 ? 'neg' : '' ?>"><?= $money((float) $r['balance']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<table class="summary">
    <tr>
        <td style="width:55%"></td>
        <td>
            <table class="totals">
                <tr><td class="k"><?= e(__('adv.opening')) ?></td><td class="num"><?= $money($ledger['opening']) ?></td></tr>
                <tr><td class="k"><?= e(__('adv.pdf_in')) ?></td><td class="num">+<?= $money($ledger['in']) ?></td></tr>
                <tr><td class="k"><?= e(__('adv.pdf_out')) ?></td><td class="num">−<?= $money($ledger['out']) ?></td></tr>
                <tr class="grand"><td class="k"><?= e(__('adv.closing')) ?></td><td class="num <?= $ledger['closing'] < 0 ? 'neg' : '' ?>"><?= $money($ledger['closing']) ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<?php if ($ledger['closing'] < 0): ?>
    <div class="note"><?= e(__('adv.pdf_deficit', ['amount' => $money(-$ledger['closing'])])) ?></div>
<?php endif; ?>
</body>
</html>
