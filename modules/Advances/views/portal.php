<?php use App\Core\View; ?>
<div class="page-head">
    <div>
        <a href="/portal" class="back-link">← <?= e(__('portal.title')) ?></a>
        <h1><?= e(__('adv.title')) ?></h1>
        <p class="muted"><?= e(__('adv.portal_hint')) ?></p>
    </div>
</div>

<?php if ($balances): ?>
    <?= View::partial('Advances::_balances', ['balances' => $balances]) ?>
<?php endif; ?>
<section class="card widget widget-auto">
    <header class="widget-head">
        <span class="widget-icon"><?= icon('receipt') ?></span>
        <h2><?= e(__('adv.ledger', ['currency' => $currency])) ?></h2>
    </header>
    <?= View::partial('Advances::_toolbar', ['currencies' => $currencies, 'currency' => $currency, 'filters' => $filters, 'pdfBase' => '/portal/advance/pdf']) ?>
    <?= View::partial('Advances::_ledger', ['ledger' => $ledger, 'currency' => $currency, 'filters' => $filters]) ?>
</section>
