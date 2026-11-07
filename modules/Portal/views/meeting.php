<?php
use App\Core\View;
use App\Services\MeetingService;

$url = MeetingService::publicUrl($m);
$tones = ['in_person' => 'green', 'proxy' => 'blue-dot', 'representative' => 'amber', 'pending' => 'gray'];
?>
<div class="page-head">
    <div>
        <a href="/portal" class="back-link">← <?= e(__('portal.title')) ?></a>
        <div class="title-line">
            <h1><?= e(__('meetings.event_title', ['client' => $m['client_name']])) ?></h1>
            <?= View::partial('Meetings::_status', ['m' => $m]) ?>
        </div>
        <p class="muted"><?= e(format_date($m['meeting_at'], true)) ?><?= $m['location'] ? ' · ' . e($m['location']) : '' ?></p>
    </div>
</div>

<div class="grid grid-4">
    <?php foreach ($summary as $k => $n): ?>
        <div class="card stat">
            <div class="stat-text"><strong><?= e(__($k === 'pending' ? 'meetings.pending' : 'meetings.att_' . $k)) ?></strong><span><?= e(__('meetings.of_total', ['total' => count($shareholders)])) ?></span></div>
            <div class="stat-value"><?= $n ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-3-1">
    <section class="card widget widget-auto">
        <header class="widget-head">
            <span class="widget-icon"><?= icon('users') ?></span>
            <h2><?= e(__('meetings.shareholders')) ?></h2>
        </header>
        <table class="table">
            <tbody>
            <?php foreach ($shareholders as $s): $a = $s['attendance'] ?? 'pending'; ?>
                <tr>
                    <td class="cell-main">
                        <strong><?= e($s['full_name']) ?></strong>
                        <span><?= e(MeetingService::shareBreakdown($s['share_count'], $s['share_group'])) ?></span>
                    </td>
                    <td class="cell-main align-right">
                        <strong><span class="badge badge-<?= $tones[$a] ?>"><?= e(__($a === 'pending' ? 'meetings.pending' : 'meetings.att_' . $a)) ?></span></strong>
                        <?php if ($s['proxy_name']): ?><span><?= e(__('meetings.proxy')) ?>: <?= e($s['proxy_name']) ?></span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <div class="stack">
        <?php if (MeetingService::accepting($m)): ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('globe') ?></span>
                    <h2><?= e(__('meetings.link')) ?></h2>
                </header>
                <div style="padding:0 16px 14px" class="stack">
                    <div class="input-group">
                        <input value="<?= e($url) ?>" readonly id="gk-link" onclick="this.select()">
                        <button type="button" class="btn btn-outline" onclick="navigator.clipboard.writeText(document.getElementById('gk-link').value); this.textContent = <?= e(json_encode(__('meetings.copied'))) ?>"><?= e(__('meetings.copy')) ?></button>
                    </div>
                    <p class="muted small"><?= e(__('meetings.portal_link_hint')) ?></p>
                </div>
            </section>
        <?php endif; ?>
        <section class="card widget widget-auto">
            <header class="widget-head"><h2><?= e(__('gk.agenda')) ?></h2></header>
            <div style="padding:0 16px 14px" class="small"><?= nl2br(e($m['agenda'])) ?></div>
        </section>
    </div>
</div>
