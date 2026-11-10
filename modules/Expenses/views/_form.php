<?php
use App\Services\ExpenseService;

$val = fn (string $k, string $default = '') => old($k, (string) ($entry[$k] ?? $default));
$billable = has_errors() ? !empty($GLOBALS['__old']['is_billable']) : (bool) ($entry['is_billable'] ?? true);
$selected = (int) $val('project_id', (string) ($_GET['project_id'] ?? ''));
// Avanslı müvekkil ipucu; bakiyeyi yalnız tahsilat yetkisi olan görür
$hints = \Modules\Advances\AdvanceService::projectHints(array_column($projects, 'id'), $entry);
$showBalance = can('payments.manage');
?>
<form method="post" action="<?= $entry ? '/expenses/' . $entry['id'] : '/expenses' ?>" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <label class="field">
        <span><?= e(__('time.project')) ?> *</span>
        <select name="project_id" required>
            <option value=""><?= e(__('time.choose_project')) ?></option>
            <?php foreach ($projects as $p): ?>
                <?php $h = $hints[(int) $p['id']] ?? null; ?>
                <option value="<?= $p['id'] ?>" <?= (int) $p['id'] === $selected ? 'selected' : '' ?><?= $h ? ' data-advance="' . $h['balance'] . '" data-advance-text="' . e(format_money($h['balance'], $h['currency'])) . '"' : '' ?>><?= e($p['number'] . ' · ' . $p['name'] . ' — ' . $p['client_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?= error_for('project_id') ?>
    </label>
    <div class="form-grid">
        <label class="field">
            <span><?= e(__('time.date')) ?> *</span>
            <input type="date" name="expense_date" value="<?= e($val('expense_date', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>" required>
            <?= error_for('expense_date') ?>
        </label>
        <label class="field">
            <span><?= e(__('expenses.category')) ?> *</span>
            <select name="category" required>
                <?php foreach (ExpenseService::CATEGORIES as $c): ?>
                    <option value="<?= $c ?>" <?= $val('category', 'court_fee') === $c ? 'selected' : '' ?>><?= e(__('expenses.cat_' . $c)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <label class="field">
        <span><?= e(__('time.amount')) ?> *</span>
        <input name="amount" inputmode="decimal" value="<?= e(money_input($val('amount'))) ?>" required placeholder="0,00">
        <small class="muted"><?= e(__('expenses.currency_hint')) ?></small>
        <?= error_for('amount') ?>
    </label>
    <label class="field">
        <span><?= e(__('time.description')) ?> *</span>
        <textarea name="description" rows="2" required placeholder="<?= e(__('expenses.description_hint')) ?>"><?= e($val('description')) ?></textarea>
        <?= error_for('description') ?>
    </label>
    <label class="field">
        <span><?= e(__('expenses.receipt')) ?></span>
        <?php if (!empty($entry['receipt_file'])): ?>
            <div class="logo-preview">
                <a href="/expenses/<?= $entry['id'] ?>/receipt" target="_blank" class="link"><?= e(__('portal.view_receipt')) ?> ↗</a>
                <label class="check"><input type="checkbox" name="remove_receipt" value="1"><span><?= e(__('expenses.remove_receipt')) ?></span></label>
            </div>
        <?php endif; ?>
        <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
        <?= error_for('receipt') ?>
    </label>
    <label class="check">
        <input type="checkbox" name="is_billable" value="1" <?= $billable ? 'checked' : '' ?>>
        <span><?= e(__('expenses.billable')) ?></span>
    </label>
    <?php if ($hints): ?>
        <p class="small" data-advance-hint hidden></p>
        <script>
        (() => {
            const form = document.currentScript.closest('form');
            const hint = form.querySelector('[data-advance-hint]');
            const t = <?= json_encode([
                'ok'      => __($showBalance ? 'adv.hint_ok' : 'adv.hint_plain'),
                'short'   => __($showBalance ? 'adv.hint_short' : 'adv.hint_plain'),
            ], JSON_UNESCAPED_UNICODE) ?>;
            const parse = (s) => {
                s = (s || '').trim();
                s = s.includes(',') ? s.replace(/\./g, '').replace(',', '.') : (/^\d{1,3}(\.\d{3})+$/.test(s) ? s.replace(/\./g, '') : s);
                return parseFloat(s) || 0;
            };
            const update = () => {
                const o = form.project_id.selectedOptions[0];
                if (!o || o.dataset.advance === undefined || !form.is_billable.checked) { hint.hidden = true; return; }
                const short = parse(form.amount.value) > parseFloat(o.dataset.advance);
                hint.textContent = (short ? t.short : t.ok).replace(':balance', o.dataset.advanceText);
                hint.className = 'small ' + (short ? 'text-danger' : 'text-success');
                hint.hidden = false;
            };
            ['change', 'input'].forEach(ev => form.addEventListener(ev, update));
            update();
        })();
        </script>
    <?php endif; ?>
    <div class="form-actions form-actions-tight">
        <?php if ($entry): ?><a href="/expenses" class="btn btn-outline"><?= e(__('app.cancel')) ?></a><?php endif; ?>
        <button class="btn btn-primary"><?= e(__($entry ? 'app.save' : 'expenses.add')) ?></button>
    </div>
</form>
