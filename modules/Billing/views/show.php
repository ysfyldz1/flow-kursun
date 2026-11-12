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
$preview ??= null;   // Kurşun: kaydedilmemiş önizleme (BillingController::preview)
$canEdit = $draft && can('statements.manage') && !$preview;
$groups = StatementService::groupLines($s['lines']);
$cols = $canEdit ? 5 : 4;
$generalErrors = array_intersect_key($GLOBALS['__errors'] ?? [], array_flip(['issue', 'statement', 'invoice', 'payment', 'notice', 'split']));
?>
<div class="page-head">
    <div>
        <?php if ($preview): ?>
            <a href="<?= e($preview['back']) ?>" class="back-link">← <?= e(__('app.cancel')) ?></a>
        <?php else: ?>
            <a href="/billing<?= $draft ? '?status=draft' : '' ?>" class="back-link">← <?= e(__('billing.title')) ?></a>
        <?php endif; ?>
        <div class="title-line">
            <h1><?= e($preview ? __('billing.preview_title') : ($s['number'] ?: __('billing.draft'))) ?></h1>
            <?php if (!$preview): ?><?= View::partial('Billing::_status', ['s' => $s]) ?><?php endif; ?>
        </div>
        <p class="muted">
            <a href="/clients/<?= $s['client_id'] ?>" class="link"><?= e($s['client_name']) ?></a>
            · <?= e(__('billing.period')) ?>: <?= $s['period_start'] ? e(format_date($s['period_start'])) . ' – ' : '' ?><?= e(format_date($s['period_end'])) ?>
            · <?= e(__('lang.' . $s['locale'])) ?>
            <?php if ($s['project_id']): ?>
                <br><?= e(__('billing.scope')) ?>: <a href="/projects/<?= $s['project_id'] ?>" class="link"><?= e($s['scope_project_name']) ?></a>
                <?php if ($s['phase_id']): ?> · <strong><?= e($s['scope_phase_name']) ?></strong><?php endif; ?>
            <?php endif; ?>
        </p>
    </div>
    <?php if ($preview): ?>
        <div class="head-actions">
            <a href="<?= e($preview['back']) ?>" class="btn btn-outline"><?= e(__('app.cancel')) ?></a>
            <form method="post" action="/billing">
                <?= csrf_field() ?>
                <?php foreach ($preview['fields'] as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?>
                <button class="btn btn-primary"><?= e(__('billing.save_as_draft')) ?></button>
            </form>
        </div>
    <?php else: ?>
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
    <?php endif; ?>
</div>

<?php foreach ($generalErrors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>
<?php if ($preview): ?>
    <div class="alert alert-warning-soft"><?= e(__('billing.preview_info')) ?></div>
<?php elseif ($draft): ?>
    <div class="alert alert-info"><?= e(__('billing.draft_info')) ?></div>
<?php endif; ?>
<?php if (!empty($pending) && $canEdit): ?>
    <form method="post" action="/billing/<?= $s['id'] ?>/pending" class="alert alert-warning-soft pending-strip">
        <?= csrf_field() ?>
        <span><?= e(__('billing.pending_note', ['count' => count($pending)])) ?></span>
        <button class="btn btn-sm btn-primary"><?= e(__('billing.add_pending', ['count' => count($pending)])) ?></button>
    </form>
<?php endif; ?>

<div class="grid grid-3-1">
    <div class="stack">
        <form method="post" action="/billing/<?= $s['id'] ?>" id="statement-form" class="card">
            <?= csrf_field() ?>
            <?php // Enter her zaman "Kaydet ve hesapla" ile gönderilsin (ilk düğme bir satırın "Sil"i olmasın) ?>
            <?php if ($canEdit): ?><button type="submit" class="sr-default-submit" tabindex="-1" aria-hidden="true"></button><?php endif; ?>
            <table class="table st-lines">
                <thead>
                    <tr>
                        <th style="width:96px"><?= e(__('time.date')) ?></th>
                        <th><?= e(__('time.description')) ?></th>
                        <th class="num" style="width:80px"><?= e(__('billing.hours')) ?></th>
                        <th class="num" style="width:150px"><?= e(__('time.amount')) ?></th>
                        <?php if ($canEdit): ?><th style="width:40px"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $g): $pg = $g['phase_id'] ? ($phaseGroups[$g['phase_id']] ?? null) : null; $hasFeeInfo = $pg && !$pg['oos'] && $pg['fee'] !== null; ?>
                    <tr class="group-row"><td colspan="<?= $cols ?>">
                        <div class="grp">
                            <div class="grp-title">
                                <?php // Tek projelik dökümde proje adı başta zaten yazıyor; grupta yalnız aşama
                                if (!($s['project_id'] && $g['phase'])): ?><span><?= e($g['title']) ?></span><?php endif; ?>
                                <?php if ($g['phase']): ?><span class="grp-phase <?= $s['project_id'] ? 'grp-phase-only' : '' ?>"><?= e($g['phase']) ?></span><?php endif; ?>
                            </div>
                            <div class="grp-meta">
                                <?php if ($hasFeeInfo): ?>
                                    <span class="chip"><?= e(__('billing.tag_phase_fee')) ?> <b><?= e($money($pg['fee'])) ?></b></span>
                                    <?php if ($pg['statements_before']): ?>
                                        <span class="chip" title="<?= e($money($pg['billed_before'])) ?>"><?= e(__('billing.phase_billed_before', ['count' => $pg['statements_before']])) ?> <b><?= e($money($pg['billed_before'])) ?></b></span>
                                    <?php endif; ?>
                                    <span class="chip <?= $pg['remaining'] <= 0 ? 'chip-red' : '' ?>"><?= e(__('phases.remaining')) ?> <b><?= e($money($pg['remaining'])) ?></b></span>
                                    <?php if ($canEdit && !$pg['has_fee'] && $pg['remaining'] > 0): ?>
                                        <button class="chip chip-btn" formaction="/billing/<?= $s['id'] ?>/phases/<?= $pg['id'] ?>/fee">+ <?= e(__('billing.type_phase_fee')) ?></button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <span class="grp-total"><?= e($money($g['total'])) ?></span>
                            </div>
                        </div>
                    </td></tr>
                    <?php if ($canEdit && $hasFeeInfo && $pg['has_fee'] && $pg['over'] > 0): ?>
                        <tr class="grp-alert-row"><td colspan="<?= $cols ?>">
                            <div class="grp-alert">
                                <span><?= icon('alert', 'icon icon-sm') ?> <?= e(__('billing.phase_over_short', ['amount' => $money($pg['over'])])) ?></span>
                                <span class="grp-alert-actions">
                                    <?php if (!$pg['has_extra']): ?>
                                        <button class="btn btn-sm btn-outline" formaction="/billing/<?= $s['id'] ?>/phases/<?= $pg['id'] ?>/extra"><?= e(__('billing.add_extra_short')) ?></button>
                                    <?php endif; ?>
                                    <span class="muted small"><?= e(__('billing.or_move_hint')) ?></span>
                                </span>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($g['lines'] as $l): $isTime = $l['type'] === 'time'; $phaseOpts = $isTime ? ($projectPhases[(int) $l['project_id']] ?? []) : []; ?>
                        <tr class="<?= $l['is_info'] ? 'info-row' : '' ?> <?= $isTime ? '' : 'flat-row' ?>">
                            <td class="muted nowrap">
                                <?php if ($isTime): ?><?= e(format_date($l['work_date'])) ?>
                                <?php else: ?><span class="line-tag line-tag-<?= e($l['type']) ?>"><?= e(__('billing.tag_' . $l['type'])) ?></span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canEdit): ?>
                                    <textarea name="lines[<?= $l['id'] ?>][description]" rows="1" class="cell-input autosize"><?= e(old("lines.{$l['id']}.description", $l['description'])) ?></textarea>
                                    <?= error_for("lines.{$l['id']}.description") ?>
                                <?php else: ?>
                                    <div class="line-desc"><?= nl2br(e($l['description'])) ?></div>
                                <?php endif; ?>
                                <?php if ($l['user_name']): ?>
                                    <div class="line-who">
                                        <?= e($l['user_name']) ?>
                                        <?php if ($canEdit && $isTime && !$l['is_info']): ?>
                                            · <input name="lines[<?= $l['id'] ?>][rate]" value="<?= e(old("lines.{$l['id']}.rate", money_input($l['rate']))) ?>" inputmode="decimal" class="cell-input num rate-input <?= $l['rate'] === null ? 'cell-missing' : '' ?>" placeholder="<?= e(__('billing.rate')) ?>">/<?= e(__('projects.hour')) ?>
                                        <?php elseif ($l['rate'] !== null): ?>
                                            · <?= e($money($l['rate'])) ?>/<?= e(__('projects.hour')) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?= error_for("lines.{$l['id']}.rate") ?>
                                <?php endif; ?>
                                <?php if ($canEdit && $phaseOpts): ?>
                                    <input type="hidden" name="lines[<?= $l['id'] ?>][phase_id]" value="<?= (int) $l['phase_id'] ?: '' ?>" data-phase-input>
                                <?php endif; ?>
                            </td>
                            <td class="num st-hours">
                                <?php if ($l['hours'] !== null || ($canEdit && $l['type'] === 'time')): ?><span class="m-label"><?= e(__('billing.hours')) ?></span><?php endif; ?>
                                <?php if ($canEdit && $l['type'] === 'time'): ?>
                                    <input name="lines[<?= $l['id'] ?>][hours]" value="<?= e(old("lines.{$l['id']}.hours", TimeService::hours((int) round($l['hours'] * 60)))) ?>" inputmode="decimal" class="cell-input num hours-input">
                                    <?= error_for("lines.{$l['id']}.hours") ?>
                                <?php else: ?>
                                    <?= $l['hours'] !== null ? TimeService::hours((int) round($l['hours'] * 60)) : '' ?>
                                <?php endif; ?>
                            </td>
                            <td class="num st-amount">
                                <span class="m-label"><?= e(__('time.amount')) ?></span>
                                <?php if ($l['is_info']): ?>
                                    <span class="included-pill"><?= e(__('billing.included')) ?></span>
                                <?php elseif ($canEdit): ?>
                                    <input name="lines[<?= $l['id'] ?>][amount]" value="<?= e(money_input($l['amount'])) ?>" inputmode="decimal" class="cell-input num <?= $l['amount'] === null ? 'cell-missing' : '' ?>">
                                    <?= error_for("lines.{$l['id']}.amount") ?>
                                <?php else: ?>
                                    <?= e($money($l['amount'])) ?>
                                <?php endif; ?>
                            </td>
                            <?php if ($canEdit): ?>
                                <td class="row-menu-cell">
                                    <details class="row-menu">
                                        <summary class="icon-btn icon-btn-sm" title="<?= e(__('billing.line_actions')) ?>">⋯</summary>
                                        <div class="row-menu-list">
                                            <?php if ($phaseOpts): ?>
                                                <div class="row-menu-label"><?= e(__('billing.move_to')) ?></div>
                                                <?php foreach (array_merge([['id' => 0, 'name' => __('phases.no_phase'), 'active' => true]], $phaseOpts) as $opt): if ((int) $l['phase_id'] === $opt['id']) continue; ?>
                                                    <button type="button" class="row-menu-item" data-move="<?= $opt['id'] ?: '' ?>" <?= $opt['active'] ? '' : 'disabled' ?>><?= e($opt['name']) ?></button>
                                                <?php endforeach; ?>
                                                <div class="row-menu-sep"></div>
                                                <button type="button" class="row-menu-item" data-split="<?= $l['id'] ?>" data-project="<?= (int) $l['project_id'] ?>"
                                                        data-hours="<?= e(TimeService::formatMinutes((int) round($l['hours'] * 60))) ?>" data-phase="<?= (int) $l['phase_id'] ?>"
                                                        data-desc="<?= e($l['description']) ?>"><?= e(__('billing.split')) ?>…</button>
                                            <?php endif; ?>
                                            <button class="row-menu-item row-menu-danger" formaction="/billing/<?= $s['id'] ?>/lines/<?= $l['id'] ?>/delete" formnovalidate><?= e(__($isTime ? 'billing.remove_line' : 'app.delete')) ?></button>
                                        </div>
                                    </details>
                                </td>
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
                    <tr><td><?= e(__('billing.subtotal')) ?></td><td class="num" data-t="subtotal"><?= e($money($s['subtotal'])) ?></td></tr>
                    <?php if ($canEdit || (float) $s['discount_amount'] > 0): ?>
                        <tr>
                            <td><?= e(__('billing.discount')) ?>
                                <?php if ($canEdit): ?>
                                    <span class="pct-input">
                                        <select name="discount_type">
                                            <option value="percent">%</option>
                                            <option value="amount" <?= $s['discount_type'] === 'amount' ? 'selected' : '' ?>><?= e($s['currency']) ?></option>
                                        </select>
                                        <input name="discount_value" value="<?= e(old('discount_value', $s['discount_value'] !== null ? money_input($s['discount_value']) : '')) ?>" inputmode="decimal">
                                    </span>
                                    <?= error_for('discount_value') ?>
                                <?php elseif ($s['discount_type'] === 'percent'): ?>(%<?= e($pct($s['discount_value'])) ?>)<?php endif; ?>
                            </td>
                            <td class="num" data-t="discount">−<?= e($money($s['discount_amount'])) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ((float) $s['discount_amount'] > 0): ?>
                        <tr><td><?= e(__('billing.after_discount')) ?></td><td class="num" data-t="net"><?= e($money((float) $s['subtotal'] - (float) $s['discount_amount'])) ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td><?= e(__('billing.vat')) ?>
                            <?php if ($canEdit): ?><span class="pct-input">% <input name="vat_rate" value="<?= e($pct($s['vat_rate'])) ?>" inputmode="decimal"></span><?php else: ?>(%<?= e($pct($s['vat_rate'])) ?>)<?php endif; ?>
                        </td>
                        <td class="num" data-t="vat"><?= e($money($s['vat_amount'])) ?></td>
                    </tr>
                    <tr>
                        <td><?= e(__('billing.withholding')) ?>
                            <?php if ($canEdit): ?><span class="pct-input">% <input name="withholding_rate" value="<?= e($pct($s['withholding_rate'])) ?>" inputmode="decimal"></span><?php else: ?>(%<?= e($pct($s['withholding_rate'])) ?>)<?php endif; ?>
                        </td>
                        <td class="num" data-t="wh">−<?= e($money($s['withholding_amount'])) ?></td>
                    </tr>
                    <tr class="grand"><td><?= e(__('billing.total_due')) ?></td><td class="num" data-t="total"><?= e($money($s['total'])) ?></td></tr>
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

<?php if ($canEdit && $projectPhases): ?>
<dialog class="modal" id="split-dialog">
    <form method="post" class="split-form">
        <?= csrf_field() ?>
        <div class="modal-head">
            <h3><?= e(__('billing.split_title')) ?></h3>
            <button type="button" class="icon-btn" data-close aria-label="×">✕</button>
        </div>
        <div class="split-body">
            <p class="muted small"><?= e(__('billing.split_help')) ?> <strong data-split-total></strong></p>
            <div class="split-rows" data-rows></div>
            <button type="button" class="btn btn-sm btn-outline" data-add-row><?= icon('plus', 'icon icon-sm') ?><?= e(__('billing.split_add')) ?></button>
            <p class="small" data-split-sum></p>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-outline" data-close><?= e(__('app.cancel')) ?></button>
            <button class="btn btn-primary" data-split-save><?= e(__('billing.split_save')) ?></button>
        </div>
    </form>
</dialog>
<script>
(() => {
    const dlg = document.getElementById('split-dialog'), form = dlg.querySelector('form'), rows = dlg.querySelector('[data-rows]'),
          sumEl = dlg.querySelector('[data-split-sum]'), save = dlg.querySelector('[data-split-save]');
    const phases = <?= json_encode($projectPhases, JSON_UNESCAPED_UNICODE) ?>;
    const t = <?= json_encode(['none' => __('phases.choose_none'), 'hours' => __('billing.hours'), 'desc' => __('time.description'), 'sum' => __('billing.split_sum_live')], JSON_UNESCAPED_UNICODE) ?>;
    const toMin = v => { v = v.trim().replace(',', '.'); let m;
        if ((m = v.match(/^(\d{1,2}):([0-5]\d)$/))) return +m[1] * 60 + +m[2];
        if (/^\d*\.?\d+$/.test(v)) return +v <= 24 ? Math.round(+v * 60) : +v;
        return NaN; };
    const fmt = m => `${Math.floor(m / 60)}:${String(Math.round(m % 60)).padStart(2, '0')}`;
    let total = 0, project = 0, n = 0;
    const check = () => {
        let s = 0;
        rows.querySelectorAll('[data-h]').forEach(i => { const m = toMin(i.value); if (!isNaN(m)) s += m; });
        const ok = Math.abs(s - total) <= 1;
        sumEl.textContent = t.sum.replace(':sum', fmt(s)).replace(':total', fmt(total));
        sumEl.className = 'small ' + (ok ? 'muted' : 'text-red');
        save.disabled = !ok;
    };
    const addRow = (hours = '', phase = '', desc = '') => {
        const i = n++;
        const row = document.createElement('div');
        row.className = 'split-row';
        const opts = `<option value="">${t.none}</option>` + (phases[project] || []).map(p => `<option value="${p.id}" ${p.active ? '' : 'disabled'}>${p.name.replace(/</g, '&lt;')}</option>`).join('');
        row.innerHTML = `<input name="parts[${i}][hours]" placeholder="${t.hours}" inputmode="decimal" data-h>
            <select name="parts[${i}][phase_id]">${opts}</select>
            <textarea name="parts[${i}][description]" rows="2" placeholder="${t.desc}"></textarea>`;
        row.querySelector('[data-h]').value = hours;
        row.querySelector('select').value = phase;
        row.querySelector('textarea').value = desc;
        rows.append(row);
        row.querySelector('[data-h]').addEventListener('input', check);
    };
    document.querySelectorAll('[data-split]').forEach(b => b.addEventListener('click', () => {
        form.action = '/billing/<?= $s['id'] ?>/lines/' + b.dataset.split + '/split';
        project = b.dataset.project;
        total = toMin(b.dataset.hours);
        dlg.querySelector('[data-split-total]').textContent = b.dataset.hours;
        rows.innerHTML = ''; n = 0;
        const phase = b.dataset.phase === '0' ? '' : b.dataset.phase;
        addRow('', phase, b.dataset.desc);
        addRow('', phase, b.dataset.desc);
        check();
        dlg.showModal();
    }));
    dlg.querySelector('[data-add-row]').addEventListener('click', () => { addRow(); check(); });
    dlg.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => dlg.close()));
})();
</script>
<?php endif; ?>

<?php if ($canEdit): ?>
<script>
// Toplamlar yazarken güncellensin (kaydedince sunucu aynı hesabı yapar): ara toplam → iskonto → KDV/stopaj
(() => {
    const form = document.getElementById('statement-form');
    const cell = k => form.querySelector(`[data-t="${k}"]`);
    const sample = cell('total')?.textContent || '';
    const nf = new Intl.NumberFormat(<?= json_encode(\App\Core\Lang::locale() === 'tr' ? 'tr-TR' : 'en-US') ?>, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = v => sample.replace(/[\d.,]+/, nf.format(Math.round(v * 100) / 100));
    const num = v => { v = (v || '').trim().replace(/\s/g, ''); if (!v) return 0;
        if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.'); else if (/^\d{1,3}(\.\d{3})+$/.test(v)) v = v.replace(/\./g, '');
        const n = Number(v); return isNaN(n) ? 0 : n; };
    const recalc = () => {
        let sub = 0;
        form.querySelectorAll('.st-lines input[name$="[amount]"]').forEach(i => sub += num(i.value));
        const dType = form.querySelector('[name="discount_type"]')?.value, dVal = num(form.querySelector('[name="discount_value"]')?.value);
        const disc = dType === 'amount' ? Math.min(dVal, sub) : Math.round(sub * dVal) / 100;
        const net = sub - disc;
        const vat = Math.round(net * num(form.querySelector('[name="vat_rate"]')?.value)) / 100;
        const wh = Math.round(net * num(form.querySelector('[name="withholding_rate"]')?.value)) / 100;
        const set = (k, v, minus) => { const c = cell(k); if (c) c.textContent = (minus ? '−' : '') + money(v); };
        set('subtotal', sub); set('discount', disc, true); set('net', net); set('vat', vat); set('wh', wh, true); set('total', net + vat - wh);
    };
    form.addEventListener('input', e => { if (e.target.matches('input, select')) recalc(); });
    form.addEventListener('change', e => { if (e.target.matches('select')) recalc(); });
})();
</script>
<?php endif; ?>
<script>
// Satır menüsü: aynı anda bir menü açık, dışarı tıklayınca kapanır; "taşı" gizli alanı doldurup formu kaydeder
document.addEventListener('click', e => {
    document.querySelectorAll('details.row-menu[open]').forEach(d => { if (!d.contains(e.target)) d.open = false; });
    const mv = e.target.closest('[data-move]');
    if (mv && !mv.disabled) {
        const row = mv.closest('tr');
        row.querySelector('[data-phase-input]').value = mv.dataset.move;
        document.getElementById('statement-form').requestSubmit();
    }
    if (e.target.closest('[data-split]')) e.target.closest('details')?.removeAttribute('open');
});
// Açıklama alanları içeriğe göre uzasın
document.querySelectorAll('textarea.autosize').forEach(t => {
    const fit = () => { t.style.height = 'auto'; t.style.height = (t.scrollHeight + t.offsetHeight - t.clientHeight) + 'px'; };
    t.addEventListener('input', fit); fit();
});
// Saat ya da ücret değişince tutar anında hesaplansın (kaydedince sunucu da aynısını yapar)
const num = v => {
    v = v.trim().replace(/\s/g, '');
    if (/^\d{1,2}:[0-5]\d$/.test(v)) { const [h, m] = v.split(':'); return +h + m / 60; }
    if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.');
    else if (/^\d{1,3}(\.\d{3})+$/.test(v)) v = v.replace(/\./g, '');   // "2.500" → binlik ayraç
    return v === '' ? NaN : Number(v);
};
const decimalSep = <?= json_encode(\App\Core\Lang::locale() === 'tr' ? ',' : '.') ?>;
document.querySelectorAll('.st-lines tr').forEach(tr => {
    const hours = tr.querySelector('.hours-input'), rate = tr.querySelector('.rate-input'),
          amount = tr.querySelector('input[name$="[amount]"]');
    if (!hours || !rate || !amount) return;
    const calc = () => {
        const h = num(hours.value), r = num(rate.value);
        if (isNaN(h) || isNaN(r)) return;
        amount.value = (Math.round(h * r * 100) / 100).toFixed(2).replace('.', decimalSep);
        amount.classList.remove('cell-missing');
    };
    hours.addEventListener('input', calc);
    rate.addEventListener('input', calc);
});
</script>
