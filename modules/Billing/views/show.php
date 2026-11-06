<?php
use App\Core\View;
use App\Services\Settings;
use App\Services\StatementService;
use App\Services\TimeService;

$draft = $s['status'] === 'draft';
$issued = $s['status'] === 'issued';
$cur = $s['currency'];
$money = fn ($v) => $v === null ? '—' : format_money((float) $v, $cur);
$pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
$balance = StatementService::balance($s);
$canEdit = $draft && can('statements.manage');
$groups = [];
foreach ($s['lines'] as $l) {
    $key = $l['project_id'] ?: 0;
    $groups[$key]['title'] ??= $l['project_id'] ? $l['project_number'] . ' · ' . $l['project_name'] : __('billing.other_items');
    $groups[$key]['lines'][] = $l;
}
$generalErrors = array_intersect_key($GLOBALS['__errors'] ?? [], array_flip(['issue', 'statement', 'invoice', 'payment', 'notice']));
?>
<div class="page-head">
    <div>
        <a href="/billing<?= $draft ? '?status=draft' : '' ?>" class="back-link">← <?= e(__('billing.title')) ?></a>
        <div class="title-line">
            <h1><?= e($s['number'] ?: __('billing.draft')) ?></h1>
            <?= View::partial('Billing::_status', ['s' => $s]) ?>
        </div>
        <p class="muted">
            <a href="/clients/<?= $s['client_id'] ?>" class="link"><?= e($s['client_name']) ?></a>
            · <?= e(__('billing.period')) ?>: <?= $s['period_start'] ? e(format_date($s['period_start'])) . ' – ' : '' ?><?= e(format_date($s['period_end'])) ?>
            · <?= e(__('lang.' . $s['locale'])) ?>
        </p>
    </div>
    <div class="head-actions">
        <a href="/billing/<?= $s['id'] ?>/pdf" target="_blank" class="btn btn-outline"><?= e(__($draft ? 'billing.preview_pdf' : 'billing.view_pdf')) ?></a>
        <?php if (!$draft): ?>
            <a href="/billing/<?= $s['id'] ?>/pdf?download=1" class="btn btn-outline"><?= e(__('billing.download_pdf')) ?></a>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <form method="post" action="/billing/<?= $s['id'] ?>/issue" onsubmit="return confirm('<?= e(__('billing.issue_confirm')) ?>')">
                <?= csrf_field() ?>
                <button class="btn btn-primary"><?= e(__('billing.issue')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($generalErrors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>
<?php if ($draft): ?>
    <div class="alert alert-info"><?= e(__('billing.draft_info')) ?></div>
<?php endif; ?>

<div class="grid grid-3-1">
    <div class="stack">
        <form method="post" action="/billing/<?= $s['id'] ?>" id="statement-form" class="card">
            <?= csrf_field() ?>
            <table class="table st-lines">
                <thead>
                    <tr>
                        <th style="width:96px"><?= e(__('time.date')) ?></th>
                        <th><?= e(__('time.description')) ?></th>
                        <th class="num" style="width:70px"><?= e(__('billing.hours')) ?></th>
                        <th class="num" style="width:150px"><?= e(__('time.amount')) ?></th>
                        <?php if ($canEdit): ?><th style="width:40px"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $g): ?>
                    <tr class="group-row"><td colspan="<?= $canEdit ? 5 : 4 ?>"><?= e($g['title']) ?></td></tr>
                    <?php foreach ($g['lines'] as $l): ?>
                        <tr class="<?= $l['is_info'] ? 'info-row' : '' ?>">
                            <td class="muted nowrap"><?= e(format_date($l['work_date'])) ?></td>
                            <td>
                                <?php if ($l['type'] !== 'time'): ?><span class="badge badge-blue"><?= e(__('billing.type_' . $l['type'])) ?></span><?php endif; ?>
                                <?php if ($canEdit): ?>
                                    <textarea name="lines[<?= $l['id'] ?>][description]" rows="1" class="cell-input autosize"><?= e(old("lines.{$l['id']}.description", $l['description'])) ?></textarea>
                                    <?= error_for("lines.{$l['id']}.description") ?>
                                <?php else: ?>
                                    <?= nl2br(e($l['description'])) ?>
                                <?php endif; ?>
                                <?php if ($l['user_name']): ?><div class="muted small"><?= e($l['user_name']) ?><?= $l['rate'] !== null ? ' · ' . e($money($l['rate'])) . '/' . e(__('projects.hour')) : '' ?></div><?php endif; ?>
                            </td>
                            <td class="num"><?= $l['hours'] !== null ? TimeService::hours((int) round($l['hours'] * 60)) : '' ?></td>
                            <td class="num">
                                <?php if ($l['is_info']): ?>
                                    <span class="muted small"><?= e(__('billing.included')) ?></span>
                                <?php elseif ($canEdit): ?>
                                    <input name="lines[<?= $l['id'] ?>][amount]" value="<?= e(money_input($l['amount'])) ?>" inputmode="decimal" class="cell-input num <?= $l['amount'] === null ? 'cell-missing' : '' ?>">
                                    <?= error_for("lines.{$l['id']}.amount") ?>
                                <?php else: ?>
                                    <?= e($money($l['amount'])) ?>
                                <?php endif; ?>
                            </td>
                            <?php if ($canEdit): ?>
                                <td><button class="icon-btn icon-btn-sm" formaction="/billing/<?= $s['id'] ?>/lines/<?= $l['id'] ?>/delete" formnovalidate title="<?= e(__('billing.remove_line')) ?>">✕</button></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="st-footer">
                <div class="st-notes">
                    <?php if ($canEdit): ?>
                        <label class="field">
                            <span><?= e(__('billing.notes')) ?></span>
                            <textarea name="notes" rows="3" placeholder="<?= e(__('billing.notes_hint')) ?>"><?= e(old('notes', (string) $s['notes'])) ?></textarea>
                        </label>
                        <div class="form-grid">
                            <label class="field">
                                <span><?= e(__('billing.due_date')) ?></span>
                                <input type="date" name="due_date" value="<?= e(old('due_date', (string) $s['due_date'])) ?>">
                                <small class="muted"><?= e(__('billing.due_hint', ['days' => Settings::get('billing.due_days', 15)])) ?></small>
                            </label>
                        </div>
                        <label class="check">
                            <input type="checkbox" name="show_bank" value="1" <?= $s['show_bank'] ? 'checked' : '' ?> <?= Settings::get('billing.bank_accounts') ? '' : 'disabled' ?>>
                            <span><?= e(__('billing.show_bank')) ?><?= Settings::get('billing.bank_accounts') ? '' : ' (' . e(__('billing.no_bank')) . ')' ?></span>
                        </label>
                    <?php elseif ($s['notes']): ?>
                        <div class="muted small"><?= e(__('billing.notes')) ?></div>
                        <div><?= nl2br(e($s['notes'])) ?></div>
                    <?php endif; ?>
                </div>
                <table class="st-totals">
                    <tr><td><?= e(__('billing.subtotal')) ?></td><td class="num"><?= e($money($s['subtotal'])) ?></td></tr>
                    <tr>
                        <td><?= e(__('billing.vat')) ?>
                            <?php if ($canEdit): ?><span class="pct-input">% <input name="vat_rate" value="<?= e($pct($s['vat_rate'])) ?>" inputmode="decimal"></span><?php else: ?>(%<?= e($pct($s['vat_rate'])) ?>)<?php endif; ?>
                        </td>
                        <td class="num"><?= e($money($s['vat_amount'])) ?></td>
                    </tr>
                    <tr>
                        <td><?= e(__('billing.withholding')) ?>
                            <?php if ($canEdit): ?><span class="pct-input">% <input name="withholding_rate" value="<?= e($pct($s['withholding_rate'])) ?>" inputmode="decimal"></span><?php else: ?>(%<?= e($pct($s['withholding_rate'])) ?>)<?php endif; ?>
                        </td>
                        <td class="num">−<?= e($money($s['withholding_amount'])) ?></td>
                    </tr>
                    <tr class="grand"><td><?= e(__('billing.total_due')) ?></td><td class="num"><?= e($money($s['total'])) ?></td></tr>
                    <?php if ($issued): ?>
                        <tr><td class="muted"><?= e(__('billing.paid')) ?></td><td class="num muted">−<?= e($money($s['paid_amount'])) ?></td></tr>
                        <tr class="balance"><td><?= e(__('billing.balance')) ?></td><td class="num"><?= e($money($balance)) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php if ($canEdit): ?>
                <div class="form-actions st-actions">
                    <button class="btn btn-outline btn-danger" formaction="/billing/<?= $s['id'] ?>/cancel" formnovalidate onclick="return confirm('<?= e(__('billing.delete_draft_confirm')) ?>')"><?= e(__('billing.delete_draft')) ?></button>
                    <button class="btn btn-primary"><?= e(__('billing.save_recalc')) ?></button>
                </div>
            <?php endif; ?>
        </form>

        <?php if ($canEdit): ?>
            <form method="post" action="/billing/<?= $s['id'] ?>/lines" class="card form-card">
                <?= csrf_field() ?>
                <h3 class="card-title"><?= e(__('billing.add_line')) ?></h3>
                <div class="add-line">
                    <select name="type" class="select-plain">
                        <option value="expense"><?= e(__('billing.type_expense')) ?></option>
                        <option value="other"><?= e(__('billing.type_other')) ?></option>
                    </select>
                    <select name="project_id" class="select-plain">
                        <option value=""><?= e(__('billing.other_items')) ?></option>
                        <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['number'] . ' · ' . $p['name']) ?></option><?php endforeach; ?>
                    </select>
                    <input name="description" class="input-plain" placeholder="<?= e(__('time.description')) ?>" value="<?= e(old('description')) ?>">
                    <div class="input-group">
                        <input name="amount" inputmode="decimal" placeholder="0,00" value="<?= e(old('amount')) ?>">
                        <span class="input-addon"><?= e($cur) ?></span>
                    </div>
                    <button class="btn btn-outline"><?= icon('plus') ?><?= e(__('billing.add')) ?></button>
                </div>
                <?= error_for('new_description') ?> <?= error_for('new_amount') ?>
            </form>
        <?php endif; ?>
    </div>

    <div class="stack">
        <?php if (!$draft): ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('receipt') ?></span>
                    <h2><?= e(__('billing.official_invoice')) ?></h2>
                </header>
                <div class="card-body">
                    <?php if ($s['invoice_file']): ?>
                        <div class="invoice-file">
                            <div class="cell-main">
                                <strong><?= e($s['invoice_number'] ?: __('billing.uploaded')) ?></strong>
                                <span><?= e(format_date($s['invoice_uploaded_at'], true)) ?></span>
                            </div>
                            <a href="/billing/<?= $s['id'] ?>/invoice" target="_blank" class="btn btn-sm btn-outline"><?= e(__('billing.open')) ?></a>
                        </div>
                    <?php else: ?>
                        <p class="muted small" style="margin-top:0"><?= e(__('billing.invoice_hint')) ?></p>
                    <?php endif; ?>
                    <?php if ($issued && can('statements.manage')): ?>
                        <form method="post" action="/billing/<?= $s['id'] ?>/invoice" enctype="multipart/form-data" class="form" style="margin-top:12px">
                            <?= csrf_field() ?>
                            <input name="invoice_number" class="input-plain" placeholder="<?= e(__('billing.invoice_number')) ?>" value="<?= e((string) $s['invoice_number']) ?>">
                            <input type="file" name="invoice" accept=".pdf,.jpg,.jpeg,.png,.xml" required>
                            <button class="btn btn-outline"><?= e(__($s['invoice_file'] ? 'billing.replace_invoice' : 'billing.upload_invoice')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <?php $pendingNotices = array_filter($notices, fn ($n) => $n['status'] === 'pending'); ?>
            <?php if ($pendingNotices): ?>
                <section class="card widget widget-auto notice-card">
                    <header class="widget-head">
                        <span class="widget-icon"><?= icon('bell') ?></span>
                        <h2><?= e(__('portal.pending_notices')) ?></h2>
                    </header>
                    <?php foreach ($pendingNotices as $n): ?>
                        <div class="card-body notice">
                            <div class="notice-head">
                                <strong><?= e($money($n['amount'])) ?></strong>
                                <span class="muted small"><?= e(format_date($n['paid_at'])) ?></span>
                            </div>
                            <div class="muted small">
                                <?= e($n['portal_user_name'] ?? '—') ?> · <?= e(format_date($n['created_at'], true)) ?>
                                <?= $n['reference'] ? '<br>' . e(__('billing.reference')) . ': ' . e($n['reference']) : '' ?>
                                <?= $n['notes'] ? '<br>' . nl2br(e($n['notes'])) : '' ?>
                            </div>
                            <?php if ($n['receipt_file']): ?>
                                <a href="/billing/<?= $s['id'] ?>/notices/<?= $n['id'] ?>/receipt" target="_blank" class="link small"><?= e(__('portal.view_receipt')) ?> ↗</a>
                            <?php endif; ?>
                            <?php if (can('payments.manage') && $issued): ?>
                                <form method="post" action="/billing/<?= $s['id'] ?>/notices/<?= $n['id'] ?>/approve" class="notice-actions">
                                    <?= csrf_field() ?>
                                    <div class="input-group">
                                        <input name="amount" inputmode="decimal" value="<?= e(money_input($n['amount'])) ?>">
                                        <span class="input-addon"><?= e($cur) ?></span>
                                    </div>
                                    <button class="btn btn-primary"><?= e(__('portal.approve')) ?></button>
                                </form>
                                <details class="reject-box">
                                    <summary class="link small"><?= e(__('portal.reject')) ?></summary>
                                    <form method="post" action="/billing/<?= $s['id'] ?>/notices/<?= $n['id'] ?>/reject" class="form">
                                        <?= csrf_field() ?>
                                        <input name="reject_reason" class="input-plain" placeholder="<?= e(__('portal.reject_reason')) ?>" required>
                                        <button class="btn btn-sm btn-outline btn-danger"><?= e(__('portal.reject')) ?></button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?= error_for('pay_amount') ?> <?= error_for('reject_reason') ?>
                </section>
            <?php endif; ?>

            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('check') ?></span>
                    <h2><?= e(__('billing.payments')) ?></h2>
                </header>
                <?php if ($s['payments']): ?>
                    <dl class="dl">
                        <?php foreach ($s['payments'] as $p): ?>
                            <div class="dl-row">
                                <dt class="cell-main">
                                    <strong class="text-strong"><?= e(format_date($p['paid_at'])) ?></strong>
                                    <span><?= e(__('billing.method_' . $p['method'])) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?><?= $p['source'] === 'portal' ? ' · ' . e(__('billing.from_portal')) : '' ?></span>
                                </dt>
                                <dd class="payment-amount">
                                    <?= e($money($p['amount'])) ?>
                                    <?php if (can('payments.manage') && $issued): ?>
                                        <form method="post" action="/billing/<?= $s['id'] ?>/payments/<?= $p['id'] ?>/delete" onsubmit="return confirm('<?= e(__('app.confirm_delete')) ?>')">
                                            <?= csrf_field() ?><button class="icon-btn icon-btn-sm" title="<?= e(__('app.delete')) ?>">✕</button>
                                        </form>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php elseif (!$issued || $balance <= 0): ?>
                    <div class="empty"><?= e(__('dash.empty')) ?></div>
                <?php endif; ?>

                <?php if ($issued && $balance > 0 && can('payments.manage')): ?>
                    <form method="post" action="/billing/<?= $s['id'] ?>/payments" class="form card-body payment-form">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <label class="field">
                                <span><?= e(__('time.amount')) ?></span>
                                <div class="input-group">
                                    <input name="amount" inputmode="decimal" value="<?= e(old('amount', money_input($balance))) ?>">
                                    <span class="input-addon"><?= e($cur) ?></span>
                                </div>
                                <?= error_for('pay_amount') ?>
                            </label>
                            <label class="field">
                                <span><?= e(__('time.date')) ?></span>
                                <input type="date" name="paid_at" value="<?= e(old('paid_at', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>">
                                <?= error_for('paid_at') ?>
                            </label>
                            <label class="field">
                                <span><?= e(__('billing.method')) ?></span>
                                <select name="method">
                                    <?php foreach (StatementService::METHODS as $m): ?><option value="<?= $m ?>"><?= e(__('billing.method_' . $m)) ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span><?= e(__('billing.reference')) ?></span>
                                <input name="reference" placeholder="<?= e(__('billing.reference_hint')) ?>">
                            </label>
                        </div>
                        <button class="btn btn-primary"><?= e(__('billing.record_payment')) ?></button>
                        <small class="muted"><?= e(__('billing.partial_hint')) ?></small>
                    </form>
                <?php endif; ?>
            </section>

            <?php if ($issued && (float) $s['paid_amount'] == 0 && can('statements.manage')): ?>
                <form method="post" action="/billing/<?= $s['id'] ?>/cancel" onsubmit="return confirm('<?= e(__('billing.cancel_confirm')) ?>')">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-danger btn-block"><?= e(__('billing.cancel')) ?></button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <section class="card widget widget-auto">
                <header class="widget-head"><h2><?= e(__('billing.how_it_works')) ?></h2></header>
                <ol class="steps">
                    <li><?= e(__('billing.step1')) ?></li>
                    <li><?= e(__('billing.step2')) ?></li>
                    <li><?= e(__('billing.step3')) ?></li>
                    <li><?= e(__('billing.step4')) ?></li>
                </ol>
            </section>
        <?php endif; ?>
    </div>
</div>

<script>
// Açıklama alanları içeriğe göre uzasın
document.querySelectorAll('textarea.autosize').forEach(t => {
    const fit = () => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; };
    t.addEventListener('input', fit); fit();
});
</script>
