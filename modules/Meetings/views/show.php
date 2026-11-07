<?php
use App\Core\View;
use App\Services\MeetingService;

$url = MeetingService::publicUrl($m);
$isDocx = MeetingService::isDocx($m);
$tones = ['in_person' => 'green', 'proxy' => 'blue-dot', 'representative' => 'amber', 'pending' => 'gray'];
?>
<div class="page-head">
    <div>
        <a href="/clients/<?= $m['client_id'] ?>" class="back-link">← <?= e($m['client_name']) ?></a>
        <div class="title-line">
            <h1><?= e(__('meetings.event_title', ['client' => $m['client_name']])) ?></h1>
            <?= View::partial('Meetings::_status', ['m' => $m]) ?>
        </div>
        <p class="muted">
            <?= e(format_date($m['meeting_at'], true)) ?><?= $m['location'] ? ' · ' . e($m['location']) : '' ?>
            <?php if ($m['project_id']): ?> · <a href="/projects/<?= $m['project_id'] ?>" class="link"><?= e($m['project_number'] . ' · ' . $m['project_name']) ?></a><?php endif; ?>
            <?php if ($m['event_id']): ?> · <a href="/calendar/<?= $m['event_id'] ?>" class="link"><?= e(__('nav.calendar')) ?></a><?php endif; ?>
        </p>
    </div>
    <div class="form-actions" style="margin:0">
        <a href="/meetings/<?= $m['id'] ?>/csv" class="btn btn-outline"><?= e(__('meetings.export')) ?></a>
        <a href="/meetings/<?= $m['id'] ?>/edit" class="btn btn-outline"><?= e(__('clients.edit')) ?></a>
        <form method="post" action="/meetings/<?= $m['id'] ?>/delete" onsubmit="return confirm('<?= e(__('meetings.confirm_delete')) ?>')">
            <?= csrf_field() ?><button class="btn btn-outline btn-danger"><?= e(__('app.delete')) ?></button>
        </form>
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
            <thead>
                <tr>
                    <th><?= e(__('meetings.shareholder')) ?></th>
                    <th class="num"><?= e(__('meetings.shares')) ?></th>
                    <th><?= e(__('meetings.attendance')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($shareholders as $s): ?>
                <tr>
                    <td class="cell-main">
                        <strong><?= e($s['full_name']) ?></strong>
                        <span><?= e($s['id_no'] ?? '') ?></span>
                    </td>
                    <td class="num"><?= e(MeetingService::shareBreakdown($s['share_count'], $s['share_group'])) ?></td>
                    <td class="cell-main">
                        <?php $a = $s['attendance'] ?? 'pending'; ?>
                        <strong><span class="badge badge-<?= $tones[$a] ?>"><?= e(__($a === 'pending' ? 'meetings.pending' : 'meetings.att_' . $a)) ?></span></strong>
                        <?php if ($s['proxy_name']): ?>
                            <span><?= e(__('meetings.proxy')) ?>: <?= e($s['proxy_name']) ?> · <?= e($s['proxy_tc']) ?><?= $s['proxy_is_default'] ? ' · ' . e(__('meetings.default_short')) : '' ?></span>
                        <?php endif; ?>
                        <?php if ($s['responded_at']): ?><span><?= e(format_date($s['responded_at'], true)) ?></span><?php endif; ?>
                    </td>
                    <td class="cell-actions">
                        <?php if ($isDocx && $s['attendance'] === 'proxy'): ?>
                            <a href="/meetings/<?= $m['id'] ?>/proxy/<?= $s['id'] ?>" class="btn btn-sm btn-outline"><?= e(__('meetings.proxy_word')) ?></a>
                        <?php endif; ?>
                        <?php if ($s['response_id']): ?>
                            <form method="post" action="/meetings/<?= $m['id'] ?>/reset/<?= $s['id'] ?>" onsubmit="return confirm('<?= e(__('meetings.confirm_reset')) ?>')">
                                <?= csrf_field() ?><button class="btn btn-sm btn-outline btn-danger"><?= e(__('meetings.reset')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <div class="stack">
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
                <p class="muted small"><?= e(__('meetings.link_hint')) ?></p>
                <form method="post" action="/meetings/<?= $m['id'] ?>/toggle">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline"><?= e(__($m['is_open'] ? 'meetings.close_form' : 'meetings.open_form')) ?></button>
                    <a href="<?= e($url) ?>" target="_blank" class="btn btn-sm btn-outline"><?= e(__('meetings.preview')) ?> ↗</a>
                </form>
            </div>
        </section>

        <section class="card widget widget-auto">
            <header class="widget-head"><h2><?= e(__('gk.agenda')) ?></h2></header>
            <div style="padding:0 16px 14px" class="small"><?= nl2br(e($m['agenda'])) ?></div>
        </section>

        <section class="card widget widget-auto">
            <header class="widget-head"><h2><?= e(__('meetings.proxy_doc')) ?></h2></header>
            <dl class="dl">
                <div class="dl-row"><dt><?= e(__('meetings.proxy_doc')) ?></dt><dd><?= $m['proxy_doc_file'] ? '<a href="/meetings/' . $m['id'] . '/doc" class="link">' . e($m['proxy_doc_name']) . '</a>' : '—' ?></dd></div>
                <div class="dl-row"><dt><?= e(__('meetings.default_proxy')) ?></dt><dd><?= e($m['default_proxy_name'] ?? '—') ?></dd></div>
            </dl>
        </section>
    </div>
</div>
