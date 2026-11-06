<?php
use App\Core\View;
use App\Services\TimeService;

$money = can('rates.view');
$age = fn (string $d) => (int) floor((time() - strtotime($d)) / 86400);
?>
<?= View::partial('Reports::_head', ['tab' => $tab]) ?>

<?php if ($amounts): ?>
    <div class="grid grid-4">
        <?php foreach ($amounts as $cur => $a): ?>
            <div class="card stat">
                <span class="stat-icon tone-amber"><?= icon('clock') ?></span>
                <div class="stat-text"><strong><?= e(__('reports.unbilled_in', ['currency' => $cur])) ?></strong><span><?= TimeService::hours((int) $a['minutes']) ?> <?= e(__('projects.hours')) ?></span></div>
                <?php if ($money): ?><div class="stat-value stat-money"><?= e(format_money($a['amount'], $cur)) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty empty-lg"><?= e(__('reports.nothing_unbilled')) ?></div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(__('clients.name')) ?></th>
                    <th><?= e(__('nav.projects')) ?></th>
                    <th class="num"><?= e(__('billing.entries')) ?></th>
                    <th class="num"><?= e(__('billing.hours')) ?></th>
                    <?php if ($money): ?><th class="num"><?= e(__('time.amount')) ?></th><?php endif; ?>
                    <th><?= e(__('reports.oldest')) ?></th>
                    <?php if (can('statements.manage')): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): $days = $age($r['oldest']); ?>
                <tr>
                    <td><a href="/clients/<?= $r['client_id'] ?>" class="cell-main"><strong><?= e($r['client_name']) ?></strong><span><?= e($r['client_code']) ?></span></a></td>
                    <td><a href="/projects/<?= $r['project_id'] ?>" class="cell-main"><strong><?= e($r['project_name']) ?></strong><span><?= e($r['number']) ?> · <?= e(__('projects.billing_' . $r['billing_type'])) ?></span></a></td>
                    <td class="num"><?= (int) $r['entries'] ?></td>
                    <td class="num"><strong><?= TimeService::hours((int) $r['minutes']) ?></strong></td>
                    <?php if ($money): ?>
                        <td class="num">
                            <?= $r['billing_type'] === 'hourly' ? e(format_money((float) $r['amount'], $r['currency'])) : '<span class="muted small">' . e(__('projects.billing_' . $r['billing_type'])) . '</span>' ?>
                            <?php if ($r['missing_rate']): ?><br><span class="text-warn small"><?= e(__('reports.missing_rate', ['count' => $r['missing_rate']])) ?></span><?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td><span class="<?= $days > 60 ? 'text-danger' : ($days > 30 ? 'text-warn' : 'muted') ?>"><?= e(format_date($r['oldest'])) ?> · <?= e(__('reports.days_ago', ['days' => $days])) ?></span></td>
                    <?php if (can('statements.manage')): ?>
                        <td class="cell-actions">
                            <form method="post" action="/billing">
                                <?= csrf_field() ?>
                                <input type="hidden" name="client_id" value="<?= $r['client_id'] ?>">
                                <input type="hidden" name="currency" value="<?= e($r['currency']) ?>">
                                <input type="hidden" name="period_end" value="<?= date('Y-m-d') ?>">
                                <button class="btn btn-sm btn-outline"><?= e(__('billing.create_draft')) ?></button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
