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
}
$orgName = Settings::get('org.name');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28mm 16mm 20mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; }
    header { position: fixed; top: -20mm; left: 0; right: 0; }
    footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #94a3b8; text-align: center; }
    .head td { vertical-align: top; }
    .logo { max-height: 14mm; max-width: 60mm; }
    .org { font-size: 13pt; font-weight: bold; }
    .muted { color: #64748b; }
    h1 { font-size: 15pt; margin: 0 0 2mm; color: <?= e(Settings::get('org.primary_color', '#1e3a8a')) ?>; }
    table { width: 100%; border-collapse: collapse; }
    .meta td { padding: 1mm 0; }
    .box { border: 1px solid #e2e8f0; border-radius: 2mm; padding: 3mm 4mm; }
    .lines th { text-align: left; font-size: 7.5pt; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 2mm 1.5mm; }
    .lines td { padding: 1.8mm 1.5mm; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .lines .group td { background: #f8fafc; font-weight: bold; padding-top: 2.5mm; }
    .lines .info td { color: #64748b; font-size: 8pt; }
    .num { text-align: right; white-space: nowrap; }
    .totals td { padding: 1.5mm 1.5mm; }
    .totals .grand td { font-size: 11pt; font-weight: bold; border-top: 1.5px solid #1e293b; padding-top: 2.5mm; }
    .bank { white-space: pre-line; }
</style>
</head>
<body>
<header>
    <table class="head">
        <tr>
            <td>
                <?php if ($logo): ?><img src="<?= $logo ?>" class="logo"><?php else: ?><span class="org"><?= e($orgName) ?></span><?php endif; ?>
            </td>
            <td class="num muted">
                <?= nl2br(e((string) Settings::get('org.address'))) ?>
                <?php if (Settings::get('org.tax_info')): ?><br><?= e(Settings::get('org.tax_info')) ?><?php endif; ?>
            </td>
        </tr>
    </table>
</header>
<footer><?= e($orgName) ?> · <?= e($s['number'] ?: __('billing.draft')) ?></footer>

<table>
    <tr>
        <td style="width:55%; vertical-align:top">
            <h1><?= e(__('billing.statement')) ?></h1>
            <table class="meta">
                <tr><td class="muted" style="width:32mm"><?= e(__('billing.number')) ?></td><td><strong><?= e($s['number'] ?: __('billing.draft')) ?></strong></td></tr>
                <tr><td class="muted"><?= e(__('billing.issue_date')) ?></td><td><?= e(format_date($s['issue_date'] ?? date('Y-m-d'))) ?></td></tr>
                <?php if ($s['due_date']): ?><tr><td class="muted"><?= e(__('billing.due_date')) ?></td><td><?= e(format_date($s['due_date'])) ?></td></tr><?php endif; ?>
                <tr><td class="muted"><?= e(__('billing.period')) ?></td><td><?= $s['period_start'] ? e(format_date($s['period_start'])) . ' – ' : '' ?><?= e(format_date($s['period_end'])) ?></td></tr>
            </table>
        </td>
        <td style="vertical-align:top">
            <div class="box">
                <div class="muted" style="font-size:7.5pt"><?= e(__('billing.bill_to')) ?></div>
                <strong><?= e($s['client_name']) ?></strong><br>
                <?php if ($s['client_address']): ?><?= nl2br(e($s['client_address'])) ?><br><?php endif; ?>
                <?= e(implode(' / ', array_filter([$s['client_city'], $s['client_country']]))) ?>
                <?php if ($s['client_tax_id']): ?><br><span class="muted"><?= e($s['client_tax_office'] ? $s['client_tax_office'] . ' · ' : '') ?><?= e($s['client_tax_id']) ?></span><?php endif; ?>
            </div>
        </td>
    </tr>
</table>

<table class="lines" style="margin-top:6mm">
    <thead>
        <tr>
            <th style="width:18mm"><?= e(__('time.date')) ?></th>
            <th><?= e(__('time.description')) ?></th>
            <th style="width:24mm"><?= e(__('billing.person')) ?></th>
            <th class="num" style="width:14mm"><?= e(__('billing.hours')) ?></th>
            <th class="num" style="width:22mm"><?= e(__('billing.rate')) ?></th>
            <th class="num" style="width:25mm"><?= e(__('time.amount')) ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
        <tr class="group"><td colspan="6"><?= e($g['title']) ?></td></tr>
        <?php foreach ($g['lines'] as $l): ?>
            <tr class="<?= $l['is_info'] ? 'info' : '' ?>">
                <td><?= e(format_date($l['work_date'])) ?></td>
                <td><?= $l['type'] !== 'time' ? '<strong>' . e(__('billing.type_' . $l['type'])) . ':</strong> ' : '' ?><?= nl2br(e($l['description'])) ?></td>
                <td><?= e($l['user_name'] ?? '') ?></td>
                <td class="num"><?= $l['hours'] !== null ? TimeService::hours((int) round($l['hours'] * 60)) : '' ?></td>
                <td class="num"><?= $l['rate'] !== null ? $money($l['rate']) : '' ?></td>
                <td class="num"><?= $l['is_info'] ? '<span class="muted">' . e(__('billing.included')) . '</span>' : $money($l['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>

<table style="margin-top:5mm">
    <tr>
        <td style="width:55%; vertical-align:top; padding-right:8mm">
            <?php if ($s['notes']): ?>
                <div class="muted" style="font-size:7.5pt"><?= e(__('billing.notes')) ?></div>
                <div style="margin-bottom:4mm"><?= nl2br(e($s['notes'])) ?></div>
            <?php endif; ?>
            <?php if ($banks): ?>
                <div class="muted" style="font-size:7.5pt"><?= e(__('billing.bank_accounts')) ?></div>
                <div class="bank"><?= e($banks) ?></div>
            <?php endif; ?>
        </td>
        <td style="vertical-align:top">
            <table class="totals">
                <tr><td><?= e(__('billing.subtotal')) ?></td><td class="num"><?= $money($s['subtotal']) ?></td></tr>
                <?php if ((float) $s['vat_rate'] > 0): ?>
                    <tr><td><?= e(__('billing.vat')) ?> (%<?= rtrim(rtrim($s['vat_rate'], '0'), '.') ?>)</td><td class="num"><?= $money($s['vat_amount']) ?></td></tr>
                <?php endif; ?>
                <?php if ((float) $s['withholding_rate'] > 0): ?>
                    <tr><td><?= e(__('billing.withholding')) ?> (%<?= rtrim(rtrim($s['withholding_rate'], '0'), '.') ?>)</td><td class="num">−<?= $money($s['withholding_amount']) ?></td></tr>
                <?php endif; ?>
                <tr class="grand"><td><?= e(__('billing.total_due')) ?></td><td class="num"><?= $money($s['total']) ?></td></tr>
                <?php if ((float) $s['paid_amount'] > 0): ?>
                    <tr><td class="muted"><?= e(__('billing.paid')) ?></td><td class="num muted">−<?= $money($s['paid_amount']) ?></td></tr>
                    <tr><td><strong><?= e(__('billing.balance')) ?></strong></td><td class="num"><strong><?= $money((float) $s['total'] - (float) $s['paid_amount']) ?></strong></td></tr>
                <?php endif; ?>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
