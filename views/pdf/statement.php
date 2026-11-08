<?php
use App\Services\Settings;
use App\Services\TimeService;

$cur = $s['currency'];
$money = fn ($v) => $v === null ? '—' : format_money((float) $v, $cur);
// Satırları projeye göre grupla
$groups = [];
foreach ($s['lines'] as $l) {
    $key = $l['project_id'] ?: 0;
    $groups[$key]['title'] ??= $l['project_id'] ? $l['project_number'] . ' · ' . $l['project_name'] : __('billing.other_items');
    $groups[$key]['lines'][] = $l;
    $groups[$key]['total'] = ($groups[$key]['total'] ?? 0) + ($l['is_info'] ? 0 : (float) $l['amount']);
}
$orgName = (string) Settings::get('org.name');
$hex = (string) Settings::get('org.primary_color', '#1e3a8a');
$primary = e($hex);
// Kurum renginin pastelleri: $amount kadar beyaza yaklaşır
$tint = function (float $amount) use ($hex): string {
    if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
        return '#f1f5f9';
    }
    return sprintf('#%02x%02x%02x', ...array_map(fn ($c) => (int) round(hexdec($c) + (255 - hexdec($c)) * $amount), array_slice($m, 1)));
};
$band = $tint(.78);     // proje şeridi
$soft = $tint(.9);      // tablo başlığı, etiket hücreleri
$zebra = $tint(.96);
$address = trim((string) Settings::get('org.address'));
$taxInfo = trim((string) Settings::get('org.tax_info'));
$number = $s['number'] ?: __('billing.draft');
$rate = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
$clientPlace = implode(' / ', array_filter([$s['client_city'], $s['client_country']]));
$line = '#cbd5e1';
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
    .accent { color: <?= $primary ?>; font-weight: bold; }

    /* Her sayfada: üstte logo ve belge adı, altta kurum bilgisi ve sayfa no */
    header { position: fixed; top: -21mm; left: 0; right: 0; height: 15mm; border-bottom: 1.2pt solid <?= $primary ?>; }
    header td { vertical-align: bottom; padding-bottom: 2.5mm; }
    .logo { max-height: 11mm; max-width: 55mm; }
    .org { font-size: 12pt; font-weight: bold; color: <?= $primary ?>; }
    .doc-title { font-size: 13pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; color: #334155; }
    footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; border-top: .5pt solid <?= $line ?>; padding-top: 1.5mm; font-size: 6.5pt; color: #64748b; }

    /* Taraf ve belge bilgileri */
    .parties { margin-bottom: 4mm; }
    .client { width: 54%; background: <?= $soft ?>; border: .5pt solid <?= $line ?>; padding: 2.5mm 3mm; }
    .client .label { font-size: 7pt; font-weight: bold; text-transform: uppercase; color: <?= $primary ?>; }
    .client .name { font-size: 9pt; font-weight: bold; margin: .8mm 0; }
    .gap { width: 4mm; }
    .meta td { padding: .55mm 0; }
    .meta .k { color: #64748b; width: 30mm; }

    /* Kalemler */
    .lines { margin-bottom: 3.5mm; }
    .lines tr { page-break-inside: avoid; }
    .lines th, .lines td { border: .5pt solid <?= $line ?>; padding: 1.1mm 1.4mm; }
    .lines thead th { background: <?= $soft ?>; font-size: 7pt; font-weight: bold; color: #334155; text-align: left; }
    .lines thead th.num { text-align: right; }
    .lines .group td { background: <?= $band ?>; color: <?= $primary ?>; font-weight: bold; font-size: 8pt; padding: 1.5mm 1.4mm; }
    .lines .odd td { background: <?= $zebra ?>; }
    .lines .info td { color: #64748b; font-size: 7pt; }

    /* Toplamlar */
    .summary { margin-top: 1mm; page-break-inside: avoid; }
    .totals td { border: .5pt solid <?= $line ?>; padding: 1.3mm 2mm; }
    .totals .k { background: <?= $soft ?>; font-weight: bold; font-size: 7.5pt; }
    .totals .grand td { font-size: 10pt; font-weight: bold; color: <?= $primary ?>; padding: 2mm; }
    .totals .grand .k { font-size: 8.5pt; }

    .section { margin-top: 4mm; border: .5pt solid <?= $line ?>; padding: 2mm 3mm; page-break-inside: avoid; }
    .section .label { font-size: 7pt; font-weight: bold; color: <?= $primary ?>; margin-bottom: .8mm; }
    .bank { white-space: pre-line; }
</style>
</head>
<body>
<header>
    <table>
        <tr>
            <td>
                <?php if ($logo): ?><img src="<?= $logo ?>" class="logo"><?php else: ?><span class="org"><?= e($orgName) ?></span><?php endif; ?>
            </td>
            <td class="num"><div class="doc-title"><?= e(__('billing.statement')) ?></div></td>
        </tr>
    </table>
</header>
<footer>
    <table>
        <tr>
            <td><?= e(implode(' · ', array_filter([$orgName, str_replace(["\r\n", "\n"], ', ', $address), $taxInfo]))) ?></td>
            <td style="width:26mm"></td><?php // sayfa numarası StatementPdf'te basılır ?>
        </tr>
    </table>
</footer>

<table class="parties">
    <tr>
        <td class="client">
            <div class="label"><?= e(__('billing.bill_to')) ?></div>
            <div class="name"><?= e($s['client_name']) ?></div>
            <?php if ($s['client_address']): ?><?= nl2br(e($s['client_address'])) ?><br><?php endif; ?>
            <?php if ($clientPlace !== ''): ?><?= e($clientPlace) ?><br><?php endif; ?>
            <?php if ($s['client_tax_id']): ?><span class="muted"><?= e($s['client_tax_office'] ? $s['client_tax_office'] . ' · ' : '') ?><?= e($s['client_tax_id']) ?></span><?php endif; ?>
        </td>
        <td class="gap"></td>
        <td>
            <table class="meta">
                <tr><td class="k"><?= e(__('billing.number')) ?></td><td class="accent"><?= e($number) ?></td></tr>
                <tr><td class="k"><?= e(__('billing.issue_date')) ?></td><td><?= e(format_date($s['issue_date'] ?? date('Y-m-d'))) ?></td></tr>
                <?php if ($s['due_date']): ?><tr><td class="k"><?= e(__('billing.due_date')) ?></td><td><?= e(format_date($s['due_date'])) ?></td></tr><?php endif; ?>
                <tr><td class="k"><?= e(__('billing.period')) ?></td><td><?= $s['period_start'] ? e(format_date($s['period_start'])) . ' – ' : '' ?><?= e(format_date($s['period_end'])) ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<?php foreach ($groups as $g): ?>
<table class="lines">
    <thead>
        <tr class="group"><td colspan="5"><?= e($g['title']) ?></td><td class="num"><?= $money($g['total']) ?></td></tr>
        <tr>
            <th style="width:18mm"><?= e(__('time.date')) ?></th>
            <th><?= e(__('time.description')) ?></th>
            <th style="width:27mm"><?= e(__('billing.person')) ?></th>
            <th class="num" style="width:11mm"><?= e(__('billing.hours')) ?></th>
            <th class="num" style="width:21mm"><?= e(__('billing.rate')) ?></th>
            <th class="num" style="width:24mm"><?= e(__('time.amount')) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($g['lines'] as $i => $l): ?>
            <tr class="<?= trim(($i % 2 ? 'odd ' : '') . ($l['is_info'] ? 'info' : '')) ?>">
                <td class="nowrap"><?= e(format_date($l['work_date'])) ?></td>
                <td><?= $l['type'] !== 'time' ? '<strong>' . e(__('billing.type_' . $l['type'])) . ':</strong> ' : '' ?><?= nl2br(e($l['description'])) ?></td>
                <td><?= e($l['user_name'] ?? '') ?></td>
                <td class="num"><?= $l['hours'] !== null ? TimeService::hours((int) round($l['hours'] * 60)) : '' ?></td>
                <td class="num"><?= $l['rate'] !== null ? $money($l['rate']) : '' ?></td>
                <td class="num"><?= $l['is_info'] ? '<span class="muted">' . e(__('billing.included')) . '</span>' : $money($l['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endforeach; ?>

<table class="summary">
    <tr>
        <td style="width:55%"></td>
        <td>
            <table class="totals">
                <tr><td class="k"><?= e(__('billing.subtotal')) ?></td><td class="num"><?= $money($s['subtotal']) ?></td></tr>
                <?php if ((float) $s['discount_amount'] > 0): ?>
                    <tr><td class="k"><?= e(__('billing.discount')) ?><?= $s['discount_type'] === 'percent' ? ' (%' . $rate($s['discount_value']) . ')' : '' ?></td><td class="num">−<?= $money($s['discount_amount']) ?></td></tr>
                    <tr><td class="k"><?= e(__('billing.after_discount')) ?></td><td class="num"><?= $money((float) $s['subtotal'] - (float) $s['discount_amount']) ?></td></tr>
                <?php endif; ?>
                <?php if ((float) $s['vat_rate'] > 0): ?>
                    <tr><td class="k"><?= e(__('billing.vat')) ?> (%<?= $rate($s['vat_rate']) ?>)</td><td class="num"><?= $money($s['vat_amount']) ?></td></tr>
                <?php endif; ?>
                <?php if ((float) $s['withholding_rate'] > 0): ?>
                    <tr><td class="k"><?= e(__('billing.withholding')) ?> (%<?= $rate($s['withholding_rate']) ?>)</td><td class="num">−<?= $money($s['withholding_amount']) ?></td></tr>
                <?php endif; ?>
                <tr class="grand"><td class="k"><?= e(__('billing.total_due')) ?></td><td class="num"><?= $money($s['total']) ?></td></tr>
                <?php if ((float) $s['paid_amount'] > 0): ?>
                    <tr><td class="k"><?= e(__('billing.paid')) ?></td><td class="num">−<?= $money($s['paid_amount']) ?></td></tr>
                    <tr class="grand"><td class="k"><?= e(__('billing.balance')) ?></td><td class="num"><?= $money((float) $s['total'] - (float) $s['paid_amount']) ?></td></tr>
                <?php endif; ?>
            </table>
        </td>
    </tr>
</table>

<?php if ($banks): ?>
    <div class="section">
        <div class="label"><?= e(__('billing.bank_accounts')) ?></div>
        <div class="bank"><?= e($banks) ?></div>
    </div>
<?php endif; ?>
<?php if ($s['notes']): ?>
    <div class="section">
        <div class="label"><?= e(__('billing.notes')) ?></div>
        <div><?= nl2br(e($s['notes'])) ?></div>
    </div>
<?php endif; ?>
</body>
</html>
