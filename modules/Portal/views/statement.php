<?php
use App\Core\View;
use App\Services\Settings;
use App\Services\StatementService;

$balance = StatementService::balance($s);
$pending = array_sum(array_map(fn ($n) => $n['status'] === 'pending' ? (float) $n['amount'] : 0, $s['notices']));
$canNotify = $balance - $pending > 0.005;
$money = fn ($v) => format_money((float) $v, $s['currency']);
$banks = trim((string) Settings::get('billing.bank_accounts'));
?>
<div class="page-head">
    <div>
        <a href="/portal" class="back-link">← <?= e(__('portal.title')) ?></a>
        <div class="title-line">
            <h1><?= e($s['number']) ?></h1>
            <?= View::partial('Billing::_status', ['s' => $s]) ?>
        </div>
        <p class="muted"><?= e(__('billing.issue_date')) ?> <?= e(format_date($s['issue_date'])) ?> · <?= e(__('billing.due_date')) ?> <?= e(format_date($s['due_date'])) ?></p>
    </div>
    <div class="head-actions">
        <a href="/portal/statements/<?= $s['id'] ?>/pdf" target="_blank" class="btn btn-outline"><?= e(__('portal.statement_pdf')) ?></a>
        <?php if ($s['invoice_file']): ?>
            <a href="/portal/statements/<?= $s['id'] ?>/invoice" target="_blank" class="btn btn-primary"><?= e(__('portal.invoice_download')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-3-1">
    <div class="stack">
        <section class="card">
            <table class="st-totals portal-summary">
                <tr><td><?= e(__('billing.subtotal')) ?></td><td class="num"><?= e($money($s['subtotal'])) ?></td></tr>
                <?php if ((float) $s['discount_amount'] > 0): ?>
                    <tr><td><?= e(__('billing.discount')) ?></td><td class="num">−<?= e($money($s['discount_amount'])) ?></td></tr>
                    <tr><td><?= e(__('billing.after_discount')) ?></td><td class="num"><?= e($money((float) $s['subtotal'] - (float) $s['discount_amount'])) ?></td></tr>
                <?php endif; ?>
                <?php if ((float) $s['vat_rate'] > 0): ?><tr><td><?= e(__('billing.vat')) ?></td><td class="num"><?= e($money($s['vat_amount'])) ?></td></tr><?php endif; ?>
                <?php if ((float) $s['withholding_rate'] > 0): ?><tr><td><?= e(__('billing.withholding')) ?></td><td class="num">−<?= e($money($s['withholding_amount'])) ?></td></tr><?php endif; ?>
                <tr class="grand"><td><?= e(__('billing.total_due')) ?></td><td class="num"><?= e($money($s['total'])) ?></td></tr>
                <tr><td class="muted"><?= e(__('billing.paid')) ?></td><td class="num muted">−<?= e($money($s['paid_amount'])) ?></td></tr>
                <tr class="balance"><td><?= e(__('billing.balance')) ?></td><td class="num"><?= e($money($balance)) ?></td></tr>
            </table>
        </section>

        <?php if (!$s['invoice_file']): ?>
            <div class="alert alert-info"><?= e(__('portal.invoice_pending')) ?></div>
        <?php endif; ?>

        <?php if ($s['notices']): ?>
            <section class="card widget widget-auto">
                <header class="widget-head"><h2><?= e(__('portal.my_notices')) ?></h2></header>
                <dl class="dl">
                    <?php foreach ($s['notices'] as $n): ?>
                        <div class="dl-row">
                            <dt class="cell-main">
                                <strong class="text-strong"><?= e($money($n['amount'])) ?> · <?= e(format_date($n['paid_at'])) ?></strong>
                                <span><?= e(__('portal.sent_at', ['date' => format_date($n['created_at'], true)])) ?><?= $n['reference'] ? ' · ' . e($n['reference']) : '' ?></span>
                                <?php if ($n['status'] === 'rejected' && $n['reject_reason']): ?><span class="text-danger"><?= e($n['reject_reason']) ?></span><?php endif; ?>
                            </dt>
                            <dd><span class="badge <?= ['pending' => 'badge-blue-dot', 'approved' => 'badge-green', 'rejected' => 'badge-red'][$n['status']] ?>"><?= e(__('portal.notice_' . $n['status'])) ?></span></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <?php if ($canNotify): ?>
            <section class="card form-card">
                <h3 class="card-title"><?= e(__('portal.notify_payment')) ?></h3>
                <p class="muted small" style="margin-top:-8px"><?= e(__('portal.notify_hint')) ?></p>
                <form method="post" action="/portal/statements/<?= $s['id'] ?>/notice" enctype="multipart/form-data" class="form">
                    <?= csrf_field() ?>
                    <label class="field">
                        <span><?= e(__('time.amount')) ?> *</span>
                        <div class="input-group">
                            <input name="amount" inputmode="decimal" value="<?= e(old('amount', money_input($balance - $pending))) ?>" required>
                            <span class="input-addon"><?= e($s['currency']) ?></span>
                        </div>
                        <?= error_for('amount') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('portal.paid_on')) ?> *</span>
                        <input type="date" name="paid_at" value="<?= e(old('paid_at', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>" required>
                        <?= error_for('paid_at') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('billing.reference')) ?></span>
                        <input name="reference" value="<?= e(old('reference')) ?>" placeholder="<?= e(__('billing.reference_hint')) ?>">
                    </label>
                    <label class="field">
                        <span><?= e(__('portal.receipt')) ?></span>
                        <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="muted"><?= e(__('portal.receipt_hint')) ?></small>
                        <?= error_for('receipt') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('billing.notes')) ?></span>
                        <textarea name="notes" rows="2"><?= e(old('notes')) ?></textarea>
                    </label>
                    <button class="btn btn-primary"><?= e(__('portal.send_notice')) ?></button>
                </form>
            </section>
        <?php elseif ($balance > 0.005): ?>
            <div class="alert alert-info"><?= e(__('portal.awaiting_approval')) ?></div>
        <?php endif; ?>

        <?php if ($banks && $balance > 0.005): ?>
            <section class="card widget widget-auto">
                <header class="widget-head"><h2><?= e(__('billing.bank_accounts')) ?></h2></header>
                <div class="card-body bank-text"><?= nl2br(e($banks)) ?></div>
            </section>
        <?php endif; ?>
    </div>
</div>
