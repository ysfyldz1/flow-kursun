<?php
use App\Core\View;

/** Ekstre üst çubuğu: para birimi, dönem, PDF. $pdfBase: PDF adresi (sorgu eklenir). */
$query = http_build_query(['currency' => $currency] + $filters);
?>
<form method="get" class="toolbar">
    <?php if (count($currencies) > 1): ?>
        <select name="currency" class="toolbar-select" onchange="this.form.submit()">
            <?php foreach ($currencies as $k): ?><option <?= $k === $currency ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?>
        </select>
    <?php else: ?>
        <input type="hidden" name="currency" value="<?= e($currency) ?>">
    <?php endif; ?>
    <?= View::partial('Reports::_period', ['filters' => $filters]) ?>
    <span class="head-right">
        <a href="<?= e($pdfBase . '?' . $query) ?>" target="_blank" class="btn btn-sm btn-outline"><?= e(__('adv.pdf')) ?></a>
        <a href="<?= e($pdfBase . '?' . $query . '&download=1') ?>" class="btn btn-sm btn-outline"><?= e(__('adv.pdf_download')) ?></a>
    </span>
</form>
