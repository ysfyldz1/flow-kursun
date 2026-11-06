<?php
use App\Core\View;

$row = fn (string $label, ?string $value) => $value
    ? '<div class="dl-row"><dt>' . e(__($label)) . '</dt><dd>' . e($value) . '</dd></div>'
    : '';
?>
<div class="page-head">
    <div>
        <a href="/portal" class="back-link">← <?= e(__('portal.title')) ?></a>
        <div class="title-line">
            <h1><?= e($p['name']) ?></h1>
            <span class="code code-lg"><?= e($p['number']) ?></span>
            <?= View::partial('Projects::_status', ['status' => $p['status']]) ?>
        </div>
        <p class="muted"><?= e(__('projects.type_' . $p['type'])) ?></p>
    </div>
</div>

<div class="grid grid-3-1">
    <div class="stack">
        <section class="card widget widget-auto">
            <header class="widget-head"><h2><?= e(__('clients.section_general')) ?></h2></header>
            <dl class="dl">
                <?= $row('projects.responsible', $p['responsible_name']) ?>
                <?= $row('projects.court', $p['court']) ?>
                <?= $row('projects.case_number', $p['case_number']) ?>
                <?= $row('projects.opened_at', format_date($p['opened_at'])) ?>
                <?= $row('projects.closed_at', format_date($p['closed_at'])) ?>
            </dl>
        </section>

        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('receipt') ?></span>
                <h2><?= e(__('portal.project_statements')) ?></h2>
            </header>
            <?php if (!$p['statements']): ?>
                <div class="empty"><?= e(__('portal.no_project_statements')) ?></div>
            <?php else: ?>
                <table class="table">
                    <?php foreach ($p['statements'] as $s): ?>
                        <tr class="row-link" onclick="location='/portal/statements/<?= $s['id'] ?>'">
                            <td><span class="code"><?= e($s['number']) ?></span></td>
                            <td class="muted"><?= e(format_date($s['issue_date'])) ?></td>
                            <td class="num"><?= e(format_money((float) $s['total'], $s['currency'])) ?></td>
                            <td class="cell-actions"><?= View::partial('Billing::_status', ['s' => $s]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($docs !== null): ?>
            <section class="card widget widget-auto" id="documents">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('folder') ?></span>
                    <h2><?= e(__('docs.title')) ?></h2>
                    <label class="btn btn-sm btn-outline head-right upload-zone" data-upload="/portal/projects/<?= $p['id'] ?>/documents/upload"
                           data-complete="/portal/projects/<?= $p['id'] ?>/documents/complete"
                           data-csrf="<?= e(\App\Core\Csrf::token()) ?>" data-msg-done="<?= e(__('docs.uploaded')) ?>" data-msg-empty="<?= e(__('docs.file_empty')) ?>">
                        <?= icon('plus', 'icon icon-sm') ?><?= e(__('docs.upload')) ?>
                        <input type="file" multiple hidden>
                        <span class="upload-status"></span>
                    </label>
                </header>
                <?php if (!$docs): ?>
                    <div class="empty"><?= e(__('docs.portal_empty')) ?></div>
                <?php else: ?>
                    <table class="table">
                        <?php foreach ($docs as $d): ?>
                            <tr>
                                <td class="cell-main">
                                    <a href="/portal/projects/<?= $p['id'] ?>/documents/<?= $d['id'] ?>" class="link"><strong><?= e($d['name']) ?></strong></a>
                                    <span><?= e(format_bytes($d['size'] !== null ? (int) $d['size'] : null)) ?> · <?= e(format_date($d['created_at'])) ?>
                                        <?php if ($d['source'] === 'client'): ?> · <?= e(__('docs.uploaded_by_you')) ?><?php endif; ?></span>
                                </td>
                                <td class="cell-actions"><a href="/portal/projects/<?= $p['id'] ?>/documents/<?= $d['id'] ?>" class="btn btn-sm btn-outline">↓</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </section>
            <script src="<?= asset('upload.js') ?>" defer></script>
            <script>document.getElementById('documents').addEventListener('uploaded', () => setTimeout(() => location.reload(), 800));</script>
        <?php endif; ?>
    </div>

    <div class="stack">
        <?php if ($showHours): ?>
            <section class="card widget widget-auto">
                <header class="widget-head">
                    <span class="widget-icon"><?= icon('clock') ?></span>
                    <h2><?= e(__('portal.time_spent')) ?></h2>
                </header>
                <div class="card-body portal-meter"><?= View::partial('Portal::_project_meter', ['p' => $p]) ?></div>
            </section>
        <?php endif; ?>

        <section class="card widget widget-auto">
            <header class="widget-head">
                <span class="widget-icon"><?= icon('users') ?></span>
                <h2><?= e(__('projects.team')) ?></h2>
            </header>
            <?php if (!$p['team']): ?>
                <div class="empty"><?= e(__('dash.empty')) ?></div>
            <?php else: ?>
                <dl class="dl">
                    <?php foreach ($p['team'] as $m): ?>
                        <div class="dl-row"><dt class="text-strong"><?= e($m['name']) ?></dt><dd class="muted"><?= e((string) $m['title']) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>
    </div>
</div>
