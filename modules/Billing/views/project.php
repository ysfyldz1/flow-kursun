<?php
use App\Core\View;
use App\Services\TimeService;

$p = $project;
$cur = $p['currency'];
$rates = can('rates.view');
$h = fn ($m) => TimeService::hours((int) $m);
$money = fn ($v) => $v === null ? '—' : format_money((float) $v, $cur);
$back = '/billing/new/project/' . $p['id'];
$anyOpen = (bool) array_filter($groups, fn ($g) => $g['state'] === 'open' || ($g['active'] && ($g['remaining'] ?? 0) > 0 && $g['state'] !== 'done'));
$sum = fn (string $k) => array_sum(array_map(fn ($g) => (float) ($g[$k] ?? 0), $groups));
$stateLabel = fn (array $g) => __('billing.state_' . $g['state']);
// Döküm oluşturma formu (gizli alanlarla)
$draftForm = function (array $fields, string $label, string $class = 'btn btn-primary') use ($p, $back, $cur) {
    $html = '<form method="get" action="/billing/preview" class="inline-form">'
        . '<input type="hidden" name="client_id" value="' . (int) $p['client_id'] . '">'
        . '<input type="hidden" name="currency" value="' . e($cur) . '">'
        . '<input type="hidden" name="project_id" value="' . (int) $p['id'] . '">'
        . '<input type="hidden" name="back" value="' . e($back . (isset($fields['_tab']) ? '?tab=' . $fields['_tab'] : '')) . '">';
    unset($fields['_tab']);
    foreach ($fields as $k => $v) {
        $html .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
    return $html . '<button class="' . $class . '">' . e($label) . '</button></form>';
};
?>
<div class="page-head">
    <div>
        <a href="/billing/new?client_id=<?= $p['client_id'] ?>" class="back-link">← <?= e($p['client_name']) ?></a>
        <div class="title-line">
            <h1><?= e($p['name']) ?></h1>
            <span class="code code-lg"><?= e($p['number']) ?></span>
        </div>
    </div>
    <?php if ($anyOpen): ?>
        <div class="head-actions">
            <?= $draftForm([], __($phased ? 'billing.whole_project' : 'billing.create_draft'), $phased ? 'btn btn-outline' : 'btn btn-primary') ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($phased): ?>
    <!-- Aşama özeti: kalem kalem -->
    <section class="card bill-summary">
        <table class="table table-m">
            <thead>
                <tr>
                    <th><?= e(__('phases.field')) ?></th>
                    <?php if ($rates): ?>
                        <th class="num"><?= e(__('phases.fee')) ?></th>
                        <th class="num"><?= e(__('billing.fee_billed')) ?></th>
                        <th class="num"><?= e(__('phases.remaining')) ?></th>
                    <?php endif; ?>
                    <th class="num"><?= e(__('billing.unbilled_time')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g): ?>
                    <tr class="sum-<?= e($g['state']) ?> <?= $g['key'] === $tab ? 'sum-current' : '' ?>">
                        <td class="m-title sum-name"><a href="?tab=<?= e($g['key']) ?>" class="link-plain"><strong><?= e($g['label']) ?></strong></a></td>
                        <?php if ($rates): ?>
                            <td class="num m-meta"><span class="m-label"><?= e(__('phases.fee')) ?></span><?= $g['fee'] !== null ? e($money($g['fee'])) : '<span class="muted">—</span>' ?></td>
                            <td class="num m-meta"><span class="m-label"><?= e(__('billing.fee_billed')) ?></span><?= e($money($g['fee_billed'] + $g['extra_billed'] + ($g['fee'] === null ? $g['billed_amount'] : 0))) ?></td>
                            <td class="num m-meta"><span class="m-label"><?= e(__('phases.remaining')) ?></span><?= $g['remaining'] !== null ? e($money($g['remaining'])) : '<span class="muted">—</span>' ?></td>
                        <?php endif; ?>
                        <td class="num m-value"><span class="m-label"><?= e(__('billing.unbilled_time')) ?></span>
                            <?php if ($g['unbilled_count']): ?><?= $h($g['unbilled_minutes']) ?> <?= e(__('projects.hours')) ?><?php if ($rates): ?><br><span class="muted small"><?= e($money($g['unbilled_amount'])) ?></span><?php endif; ?>
                            <?php else: ?><span class="muted">—</span><?php endif; ?>
                        </td>
                        <td class="m-actions"><span class="state-pill state-<?= e($g['state']) ?>"><?= e($stateLabel($g)) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($rates): ?>
                <tfoot>
                    <tr>
                        <td><strong><?= e(__('time.total')) ?></strong></td>
                        <td class="num"><strong><?= e($money($sum('fee'))) ?></strong></td>
                        <td class="num"><strong><?= e($money($sum('fee_billed') + $sum('extra_billed'))) ?></strong></td>
                        <td class="num"><strong><?= e($money($sum('remaining'))) ?></strong></td>
                        <td class="num"><strong><?= $h($sum('unbilled_minutes')) ?> <?= e(__('projects.hours')) ?></strong><br><span class="muted small"><?= e($money($sum('unbilled_amount'))) ?></span></td>
                        <td></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </section>

    <!-- Aşama sekmeleri -->
    <nav class="phase-tabs">
        <?php foreach ($groups as $g): ?>
            <a href="?tab=<?= e($g['key']) ?>" class="phase-tab tab-<?= e($g['state']) ?> <?= $g['key'] === $tab ? 'active' : '' ?>">
                <?= e($g['label']) ?>
                <?php if ($g['unbilled_count']): ?><span class="tab-count"><?= (int) $g['unbilled_count'] ?></span><?php elseif ($g['state'] === 'done'): ?> ✓<?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if ($current): $g = $current; ?>
    <section class="card phase-panel panel-<?= e($g['state']) ?>">
        <header class="phase-panel-head">
            <div>
                <h2><?= e($phased ? $g['label'] : __('billing.unbilled_time')) ?></h2>
                <div class="grp-meta">
                    <?php if ($rates && $g['fee'] !== null): ?>
                        <span class="chip"><?= e(__('billing.tag_phase_fee')) ?> <b><?= e($money($g['fee'])) ?></b></span>
                        <span class="chip"><?= e(__('billing.fee_billed')) ?> <b><?= e($money($g['fee_billed'])) ?></b></span>
                        <span class="chip <?= $g['remaining'] < 0 ? 'chip-red' : '' ?>"><?= e(__('phases.remaining')) ?> <b><?= e($money($g['remaining'])) ?></b></span>
                    <?php endif; ?>
                    <?php if ($g['overdue']): ?><span class="badge badge-red"><?= e(__('phases.overdue')) ?></span><?php endif; ?>
                    <?php if (!$g['active']): ?><span class="badge badge-amber"><?= e(__('phases.optional_pending')) ?></span><?php endif; ?>
                    <span class="state-pill state-<?= e($g['state']) ?>"><?= e($stateLabel($g)) ?></span>
                </div>
            </div>
            <?php $openDraft = current(array_filter($g['statements'], fn ($st) => $st['status'] === 'draft')); ?>
            <?php if ($openDraft): ?>
                <a href="/billing/<?= $openDraft['id'] ?>" class="btn btn-primary"><?= e(__('billing.go_to_draft')) ?></a>
            <?php elseif ($g['active'] && ($g['unbilled_count'] || ($g['remaining'] ?? 0) > 0)): ?>
                <?php if (!$phased): ?>
                    <?= $draftForm([], __('billing.create_draft')) ?>
                <?php elseif ($g['key'] === 'none'): ?>
                    <?= $draftForm(['unphased' => 1, '_tab' => 'none'], __('billing.draft_unphased')) ?>
                <?php else: ?>
                    <?= $draftForm(['phase_id' => $g['phase_id'], '_tab' => $g['key']], __('billing.draft_this_phase')) ?>
                <?php endif; ?>
            <?php endif; ?>
        </header>

        <?php if ($g['draft_count']): ?>
            <div class="alert alert-info"><?= e(__('billing.n_in_draft_hint', ['count' => $g['draft_count']])) ?></div>
        <?php endif; ?>

        <?php if ($entries): ?>
            <table class="table table-m bill-entries">
                <?php foreach ($entries as $t): ?>
                    <tr>
                        <td class="muted nowrap m-meta"><?= e(format_date($t['work_date'])) ?></td>
                        <td class="cell-main m-title"><strong><?= e($t['user_name']) ?></strong><span><?= e($t['description']) ?></span></td>
                        <td class="num m-value"><?= TimeService::formatMinutes((int) $t['minutes']) ?><?php if ($rates && $t['amount'] !== null): ?><br><span class="muted small"><?= e($money($t['amount'])) ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <div class="empty"><?= e(__($g['state'] === 'done' ? 'billing.all_billed' : 'billing.no_unbilled_here')) ?></div>
        <?php endif; ?>

        <?php if ($g['statements']): ?>
            <div class="prev-statements">
                <h3><?= e(__('billing.previous_statements')) ?></h3>
                <?php foreach ($g['statements'] as $st): ?>
                    <div class="prev-row">
                        <a href="/billing/<?= $st['id'] ?>" class="link"><strong><?= e($st['number'] ?: __('billing.draft')) ?></strong></a>
                        <span class="muted small"><?= e(format_date($st['issue_date'] ?: substr($st['created_at'], 0, 10))) ?></span>
                        <?= View::partial('Billing::_status', ['s' => $st]) ?>
                        <?php if ($rates): ?><span class="prev-amount"><?= e($money($st['part_amount'])) ?></span><?php endif; ?>
                        <a href="/billing/<?= $st['id'] ?>/pdf" target="_blank" class="btn btn-sm btn-outline">PDF</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
