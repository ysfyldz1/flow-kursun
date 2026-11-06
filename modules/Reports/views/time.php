<?php
use App\Core\View;
use App\Services\TimeService;

$money = can('rates.view');
$max = max(array_column($rows, 'minutes') ?: [1]);
$rate = $totalMinutes ? round($billableMinutes / $totalMinutes * 100) : 0;
$groupQuery = fn (string $g) => '?' . http_build_query(array_merge($_GET, ['group' => $g]));
$link = fn (array $r) => match ($filters['group']) {
    'client'  => '/clients/' . $r['id'],
    'project' => '/projects/' . $r['id'],
    default   => '/time?user=' . $r['id'] . '&week=' . $filters['to'],
};
?>
<?= View::partial('Reports::_head', ['tab' => $tab]) ?>

<div class="grid grid-4">
    <div class="card stat">
        <span class="stat-icon tone-blue"><?= icon('clock') ?></span>
        <div class="stat-text"><strong><?= e(__('reports.total_hours')) ?></strong><span><?= e(__('reports.entries_count', ['count' => array_sum(array_column($rows, 'entries'))])) ?></span></div>
        <div class="stat-value"><?= TimeService::hours($totalMinutes) ?></div>
    </div>
    <div class="card stat">
        <span class="stat-icon tone-green"><?= icon('check') ?></span>
        <div class="stat-text"><strong><?= e(__('reports.billable_hours')) ?></strong><span><?= e(__('reports.billable_rate', ['pct' => $rate])) ?></span></div>
        <div class="stat-value"><?= TimeService::hours($billableMinutes) ?></div>
    </div>
    <?php if ($money): ?>
        <div class="card stat stat-wide">
            <span class="stat-icon tone-amber"><?= icon('receipt') ?></span>
            <div class="stat-text"><strong><?= e(__('reports.billable_value')) ?></strong><span><?= e(__('reports.billable_value_hint')) ?></span></div>
            <div class="stat-values">
                <?php foreach ($amounts ?: ['TRY' => ['amount' => 0]] as $cur => $a): ?>
                    <strong><?= e(format_money($a['amount'], $cur)) ?></strong>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" class="toolbar">
        <div class="tabs">
            <?php foreach (\App\Services\ReportService::GROUPS as $g): ?>
                <a href="<?= e($groupQuery($g)) ?>" class="<?= $filters['group'] === $g ? 'active' : '' ?>"><?= e(__('reports.by_' . $g)) ?></a>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="group" value="<?= e($filters['group']) ?>">
        <?= View::partial('Reports::_period', ['filters' => $filters]) ?>
        <select name="user_id" class="toolbar-select" onchange="this.form.submit()">
            <option value=""><?= e(__('reports.all_people')) ?></option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $filters['user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="client_id" class="toolbar-select" onchange="this.form.submit()">
            <option value=""><?= e(__('reports.all_clients')) ?></option>
            <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= $filters['client_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
    </form>

    <?php if (!$rows): ?>
        <div class="empty empty-lg"><?= e(__('reports.empty')) ?></div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(__('reports.group_' . $filters['group'])) ?></th>
                    <th style="width:32%"></th>
                    <th class="num"><?= e(__('time.total')) ?></th>
                    <th class="num"><?= e(__('time.billable')) ?></th>
                    <th class="num">%</th>
                    <?php if ($money): ?><th class="num"><?= e(__('time.amount')) ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): $pct = $r['minutes'] ? round($r['billable_minutes'] / $r['minutes'] * 100) : 0; ?>
                <tr>
                    <td><a href="<?= e($link($r)) ?>" class="cell-main"><strong><?= e($r['label']) ?></strong><span><?= e((string) $r['sublabel']) ?></span></a></td>
                    <td>
                        <div class="hbar" title="<?= TimeService::formatMinutes((int) $r['minutes']) ?>">
                            <span class="hbar-total" style="width: <?= round($r['minutes'] / $max * 100, 1) ?>%">
                                <span class="hbar-billable" style="width: <?= $pct ?>%"></span>
                            </span>
                        </div>
                    </td>
                    <td class="num"><strong><?= TimeService::hours((int) $r['minutes']) ?></strong></td>
                    <td class="num"><?= TimeService::hours((int) $r['billable_minutes']) ?></td>
                    <td class="num <?= $pct < 60 ? 'text-warn' : '' ?>"><?= $pct ?></td>
                    <?php if ($money): ?>
                        <td class="num">
                            <?php foreach ($r['amounts'] as $cur => $v): ?><div><?= e(format_money($v, $cur)) ?></div><?php endforeach; ?>
                            <?= $r['amounts'] ? '' : '<span class="muted">—</span>' ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="report-legend muted small">
            <span><i class="lg-billable"></i><?= e(__('time.billable')) ?></span>
            <span><i class="lg-total"></i><?= e(__('time.non_billable')) ?></span>
        </div>
    <?php endif; ?>
</div>
