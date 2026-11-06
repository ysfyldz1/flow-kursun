<?php
$tabs = ['time' => 'reports.time', 'unbilled' => 'reports.unbilled'];
if (can('rates.view')) {
    $tabs += ['aging' => 'reports.aging', 'collections' => 'reports.collections'];
}
$exportQuery = http_build_query(array_merge($_GET, ['export' => 1]));
?>
<div class="page-head">
    <div>
        <h1><?= e(__('reports.title')) ?></h1>
        <p class="muted"><?= e(__('reports.desc_' . $tab)) ?></p>
    </div>
    <a href="?<?= e($exportQuery) ?>" class="btn btn-outline"><?= icon('chart') ?><?= e(__('reports.export')) ?></a>
</div>

<nav class="report-tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="/reports/<?= $key ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= e(__($label)) ?></a>
    <?php endforeach; ?>
</nav>
