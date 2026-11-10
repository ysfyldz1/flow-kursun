<?php
use App\Core\View;
use Modules\Advances\AdvanceService;

$c = $client;
$type = old('type', 'deposit');
$cur = old('currency', $currency);
?>
<div class="page-head">
    <div>
        <a href="/clients/<?= $c['id'] ?>" class="back-link">← <?= e($c['name']) ?></a>
        <div class="title-line">
            <h1><?= e(__('adv.title')) ?></h1>
            <span class="badge <?= $c['uses_advance'] ? 'badge-green' : 'badge-gray' ?>"><?= e(__($c['uses_advance'] ? 'adv.on' : 'adv.off')) ?></span>
        </div>
        <p class="muted"><?= e(__($c['uses_advance'] ? 'adv.on_hint' : 'adv.off_hint')) ?></p>
    </div>
    <div class="head-actions">
        <form method="post" action="/clients/<?= $c['id'] ?>/advance/toggle" <?= $c['uses_advance'] ? 'onsubmit="return confirm(' . e(json_encode(__('adv.confirm_off'), JSON_UNESCAPED_UNICODE)) . ')"' : '' ?>>
            <?= csrf_field() ?>
            <button class="btn <?= $c['uses_advance'] ? 'btn-outline' : 'btn-primary' ?>"><?= e(__($c['uses_advance'] ? 'adv.turn_off' : 'adv.turn_on')) ?></button>
        </form>
    </div>
</div>

<div class="time-layout">
    <div class="stack">
        <section class="card form-card">
            <h3 class="card-title"><?= e(__('adv.new')) ?></h3>
            <form method="post" action="/clients/<?= $c['id'] ?>/advance">
                <?= csrf_field() ?>
                <label class="field">
                    <span><?= e(__('adv.type')) ?> *</span>
                    <select name="type">
                        <?php foreach (AdvanceService::TYPES as $t): ?><option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(__("adv.type_$t")) ?></option><?php endforeach; ?>
                    </select>
                    <?= error_for('type') ?>
                </label>
                <div class="form-grid">
                    <label class="field">
                        <span><?= e(__('adv.amount')) ?> *</span>
                        <input name="amount" value="<?= e(old('amount')) ?>" inputmode="decimal" required>
                        <?= error_for('amount') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('clients.currency')) ?> *</span>
                        <select name="currency">
                            <?php foreach (array_unique([...$currencies, 'TRY', 'USD', 'EUR', 'GBP']) as $k): ?><option <?= $cur === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?>
                        </select>
                        <?= error_for('currency') ?>
                    </label>
                </div>
                <div class="form-grid">
                    <label class="field">
                        <span><?= e(__('adv.date')) ?> *</span>
                        <input type="date" name="entry_date" value="<?= e(old('entry_date', date('Y-m-d'))) ?>" required>
                        <?= error_for('entry_date') ?>
                    </label>
                    <label class="field">
                        <span><?= e(__('adv.method')) ?></span>
                        <select name="method">
                            <option value=""></option>
                            <?php foreach (AdvanceService::METHODS as $m): ?><option value="<?= $m ?>" <?= old('method', 'transfer') === $m ? 'selected' : '' ?>><?= e(__("adv.method_$m")) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="field">
                    <span><?= e(__('adv.reference')) ?></span>
                    <input name="reference" value="<?= e(old('reference')) ?>">
                </label>
                <label class="field">
                    <span><?= e(__('adv.notes')) ?></span>
                    <textarea name="notes" rows="2"><?= e(old('notes')) ?></textarea>
                    <small class="muted"><?= e(__('adv.adjust_hint')) ?></small>
                    <?= error_for('notes') ?>
                </label>
                <button class="btn btn-primary"><?= e(__('app.save')) ?></button>
            </form>
        </section>
    </div>

    <div class="stack">
        <?php if ($balances): ?>
            <?= View::partial('Advances::_balances', ['balances' => $balances]) ?>
        <?php endif; ?>
        <section class="card">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('receipt') ?></span>
                <h2><?= e(__('adv.ledger', ['currency' => $currency])) ?></h2>
            </header>
            <?= View::partial('Advances::_toolbar', ['currencies' => $currencies, 'currency' => $currency, 'filters' => $filters, 'pdfBase' => '/clients/' . $c['id'] . '/advance/pdf']) ?>
            <?= View::partial('Advances::_ledger', ['ledger' => $ledger, 'currency' => $currency, 'filters' => $filters, 'deleteBase' => '/clients/' . $c['id'] . '/advance']) ?>
        </section>
    </div>
</div>
