<?php
use App\Services\TimeService;

$used = (int) $p['minutes'];
$pct = $p['budget_hours'] !== null ? min(100, (int) round($used / 60 / max((float) $p['budget_hours'], 0.1) * 100)) : null;
?>
<div class="cell-main align-right">
    <strong><?= TimeService::hours($used) ?> <?= e(__('projects.hours')) ?></strong>
    <?php if ($pct !== null): ?>
        <span><?= e(__('portal.of_budget', ['pct' => $pct, 'budget' => rtrim(rtrim((string) $p['budget_hours'], '0'), '.')])) ?></span>
        <span class="meter"><span style="width: <?= $pct ?>%" class="<?= $pct >= 90 ? 'meter-warn' : '' ?>"></span></span>
    <?php endif; ?>
</div>
