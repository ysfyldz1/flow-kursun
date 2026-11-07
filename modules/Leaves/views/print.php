<?php
use App\Core\Lang;

$fmtDays = fn (float $d) => rtrim(rtrim(number_format($d, 1, ',', '.'), '0'), ',');
?>
<!doctype html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <title><?= e(__('leaves.form_title')) ?> · <?= e($l['user_name']) ?></title>
    <style>
        body { font: 13px/1.5 Arial, Helvetica, sans-serif; color: #111; max-width: 720px; margin: 32px auto; padding: 0 24px; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #111; padding-bottom: 12px; }
        header img { max-height: 48px; }
        h1 { font-size: 18px; text-align: center; margin: 28px 0 20px; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #999; padding: 8px 10px; vertical-align: top; }
        td:first-child { width: 38%; background: #f4f4f4; font-weight: bold; }
        .signs { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 56px; }
        .signs div { border-top: 1px solid #111; padding-top: 6px; text-align: center; }
        .note { margin-top: 20px; font-size: 12px; color: #444; }
        .no-print { text-align: center; margin-bottom: 20px; }
        @media print { .no-print { display: none; } body { margin: 0 auto; } }
    </style>
</head>
<body>
<div class="no-print"><button onclick="print()"><?= e(__('leaves.print')) ?></button></div>
<header>
    <?php if ($logo): ?><img src="/logo" alt="<?= e($org) ?>"><?php else: ?><strong><?= e($org) ?></strong><?php endif; ?>
    <span><?= e(format_date(date('Y-m-d'))) ?></span>
</header>
<h1><?= e(mb_strtoupper(__('leaves.form_title'))) ?></h1>
<table>
    <tr><td><?= e(__('leaves.employee')) ?></td><td><?= e($l['user_name']) ?><?= $l['user_title'] ? ' — ' . e($l['user_title']) : '' ?></td></tr>
    <?php if ($balance['hire_date']): ?><tr><td><?= e(__('users.hire_date')) ?></td><td><?= e(format_date($balance['hire_date'])) ?></td></tr><?php endif; ?>
    <tr><td><?= e(__('leaves.type')) ?></td><td><?= e(__('leaves.type_' . $l['type'])) ?></td></tr>
    <tr><td><?= e(__('leaves.start')) ?></td><td><?= e(format_date($l['start_date'])) ?><?= $l['half_start'] ? ' (' . e(__('leaves.afternoon')) . ')' : '' ?></td></tr>
    <tr><td><?= e(__('leaves.end')) ?></td><td><?= e(format_date($l['end_date'])) ?><?= $l['half_end'] ? ' (' . e(__('leaves.morning')) . ')' : '' ?></td></tr>
    <tr><td><?= e(__('leaves.days')) ?></td><td><?= e($fmtDays((float) $l['days'])) ?> <?= e(__('leaves.workdays')) ?></td></tr>
    <?php if ($l['type'] === 'annual'): ?>
        <tr><td><?= e(__('leaves.remaining_after')) ?></td><td><?= e($fmtDays($balance['remaining'])) ?> <?= e(__('leaves.days_unit')) ?></td></tr>
    <?php endif; ?>
    <?php if ($l['reason']): ?><tr><td><?= e(__('leaves.reason')) ?></td><td><?= nl2br(e($l['reason'])) ?></td></tr><?php endif; ?>
    <tr><td><?= e(__('leaves.status_approved_by')) ?></td><td><?= e((string) $l['reviewer_name']) ?> · <?= e(format_date($l['reviewed_at'], true)) ?></td></tr>
</table>
<p class="note"><?= e(__('leaves.form_note')) ?></p>
<div class="signs">
    <div><?= e(__('leaves.sign_employee')) ?><br><?= e($l['user_name']) ?></div>
    <div><?= e(__('leaves.sign_approver')) ?><br><?= e((string) $l['reviewer_name']) ?></div>
</div>
</body>
</html>
