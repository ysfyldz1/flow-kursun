<?php
use App\Core\View;

$fmtDays = fn (float $d) => rtrim(rtrim(number_format($d, 1, ',', '.'), '0'), ',');
$row = fn (string $label, ?string $value) => $value !== null && $value !== ''
    ? '<div class="dl-row"><dt>' . e(__($label)) . '</dt><dd>' . $value . '</dd></div>'
    : '';
$range = format_date($l['start_date']) . ($l['half_start'] ? ' (' . __('leaves.afternoon') . ')' : '')
    . ($l['end_date'] !== $l['start_date'] ? ' – ' . format_date($l['end_date']) . ($l['half_end'] ? ' (' . __('leaves.morning') . ')' : '') : '');
$canCancel = ($l['status'] === 'pending' && ($own || $canReview)) || ($l['status'] === 'approved' && $canReview);
?>
<div class="page-head">
    <div>
        <a href="/leaves" class="back-link">← <?= e(__('leaves.title')) ?></a>
        <div class="title-line">
            <h1><?= e(__('leaves.type_' . $l['type'])) ?> · <?= e($l['user_name']) ?></h1>
            <?= View::partial('Leaves::_status', ['l' => $l]) ?>
        </div>
        <p class="muted"><?= e($range) ?> · <?= e($fmtDays((float) $l['days'])) ?> <?= e(__('leaves.workdays')) ?></p>
    </div>
    <?php if ($l['status'] === 'approved'): ?>
        <a href="/leaves/<?= $l['id'] ?>/print" target="_blank" class="btn btn-outline"><?= e(__('leaves.print')) ?> ↗</a>
    <?php endif; ?>
</div>

<div class="grid grid-3-1">
    <div class="stack">
        <section class="card widget widget-auto">
            <dl class="dl">
                <?= $row('leaves.employee', e($l['user_name']) . ($l['user_title'] ? ' · ' . e($l['user_title']) : '')) ?>
                <?= $row('leaves.dates', e($range)) ?>
                <?= $row('leaves.days', e($fmtDays((float) $l['days']))) ?>
                <?= $row('leaves.reason', $l['reason'] ? nl2br(e($l['reason'])) : null) ?>
                <?= $row('leaves.report', $l['report_file'] ? '<a href="/leaves/' . $l['id'] . '/file/report" target="_blank" class="link">' . e(__('leaves.view_file')) . ' ↗</a>' : null) ?>
                <?= $row('leaves.requested_at', e(format_date($l['created_at'], true))) ?>
                <?php if ($l['reviewed_at']): ?>
                    <?= $row('leaves.status_' . $l['status'] . '_by', e((string) $l['reviewer_name']) . ' · ' . e(format_date($l['reviewed_at'], true))) ?>
                    <?= $row('leaves.review_note', $l['review_note'] ? nl2br(e($l['review_note'])) : null) ?>
                <?php endif; ?>
                <?php if ($l['status'] === 'approved'): ?>
                    <?= $row('leaves.signed', $l['signed_file']
                        ? '<a href="/leaves/' . $l['id'] . '/file/signed" target="_blank" class="link">' . e(__('leaves.view_file')) . ' ↗</a> <span class="muted small">' . e(format_date($l['signed_at'], true)) . '</span>'
                        : '<span class="text-warn">' . e(__('leaves.signed_missing')) . '</span>') ?>
                <?php endif; ?>
            </dl>
        </section>

        <?php if ($l['status'] === 'pending' && $canReview): ?>
            <section class="card form-card">
                <h3 class="card-title"><?= e(__('leaves.review')) ?></h3>
                <form method="post" action="/leaves/<?= $l['id'] ?>/review" class="form">
                    <?= csrf_field() ?>
                    <?= error_for('leave') ?>
                    <label class="field">
                        <span><?= e(__('leaves.review_note')) ?></span>
                        <textarea name="review_note" rows="2" placeholder="<?= e(__('leaves.review_note_hint')) ?>"><?= e(old('review_note')) ?></textarea>
                        <?= error_for('review_note') ?>
                    </label>
                    <div class="form-actions form-actions-tight">
                        <button name="decision" value="rejected" class="btn btn-outline btn-danger"><?= e(__('leaves.reject')) ?></button>
                        <button name="decision" value="approved" class="btn btn-primary"><?= e(__('leaves.approve')) ?></button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($l['status'] === 'approved' && ($own || $canReview)): ?>
            <section class="card form-card">
                <h3 class="card-title"><?= e(__('leaves.signed_upload')) ?></h3>
                <form method="post" action="/leaves/<?= $l['id'] ?>/signed" enctype="multipart/form-data" class="form">
                    <?= csrf_field() ?>
                    <p class="muted small"><?= e(__('leaves.signed_hint')) ?></p>
                    <input type="file" name="signed" accept=".pdf,.jpg,.jpeg,.png" required>
                    <?= error_for('signed') ?>
                    <div class="form-actions form-actions-tight">
                        <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($canCancel): ?>
            <section class="card form-card">
                <h3 class="card-title"><?= e(__($own && $l['status'] === 'pending' ? 'leaves.withdraw' : 'leaves.cancel')) ?></h3>
                <form method="post" action="/leaves/<?= $l['id'] ?>/cancel" class="form" onsubmit="return confirm('<?= e(__('leaves.confirm_cancel')) ?>')">
                    <?= csrf_field() ?>
                    <?php if (!$own): ?>
                        <label class="field">
                            <span><?= e(__('leaves.review_note')) ?> *</span>
                            <textarea name="review_note" rows="2"></textarea>
                            <?= error_for('review_note') ?>
                        </label>
                    <?php endif; ?>
                    <div class="form-actions form-actions-tight">
                        <button class="btn btn-outline btn-danger"><?= e(__($own && $l['status'] === 'pending' ? 'leaves.withdraw' : 'leaves.cancel')) ?></button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('calendar') ?></span>
                <h2><?= e(__('leaves.balance_of', ['name' => $l['user_name']])) ?></h2>
            </header>
            <dl class="dl">
                <div class="dl-row"><dt><?= e(__('leaves.remaining')) ?></dt><dd class="text-strong"><?= e($fmtDays($balance['remaining'])) ?></dd></div>
                <div class="dl-row"><dt><?= e(__('leaves.entitlement')) ?></dt><dd><?= e($fmtDays($balance['entitlement'])) ?></dd></div>
                <div class="dl-row"><dt><?= e(__('leaves.used')) ?></dt><dd><?= e($fmtDays($balance['used'])) ?></dd></div>
                <div class="dl-row"><dt><?= e(__('leaves.excuse_year')) ?></dt><dd><?= e($fmtDays($balance['excuse_year'])) ?></dd></div>
            </dl>
            <?php if ($l['status'] === 'pending' && $l['type'] === 'annual' && $balance['remaining'] < (float) $l['days']): ?>
                <p class="text-warn small" style="padding:0 16px 12px"><?= e(__('leaves.exceeds_balance')) ?></p>
            <?php endif; ?>
        </section>
        <?php if ($l['status'] === 'pending'): ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('users') ?></span>
                    <h2><?= e(__('leaves.approvers')) ?></h2>
                </header>
                <dl class="dl">
                    <?php foreach ($approvers as $a): ?>
                        <div class="dl-row"><dt class="text-strong"><?= e($a['name']) ?></dt></div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endif; ?>
    </div>
</div>
