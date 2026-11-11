<?php use App\Services\Settings; $cronRun = Settings::get('cron.last_run'); ?>
<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <h1><?= e(__('system.title')) ?></h1>
                <p class="muted"><?= e(__('system.subtitle')) ?></p>
            </div>
        </div>

        <section class="card form-card">
            <dl class="tf-facts">
                <div><dt><?= e(__('system.version')) ?></dt><dd><?= e($version ?: '—') ?></dd></div>
                <div><dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd></div>
                <div><dt><?= e(__('system.cron')) ?></dt><dd><?= e($cronRun ? format_date($cronRun, true) : __('system.cron_never')) ?></dd></div>
            </dl>
        </section>

        <section class="card form-card">
            <h3 class="section-title"><?= e(__('system.jobs')) ?></h3>
            <?php if (!\App\Services\Scheduler::healthy()): ?>
                <div class="alert alert-error">
                    <?= e($cronRun ? __('system.cron_stale', ['date' => format_date($cronRun, true)]) : __('system.cron_missing')) ?>
                </div>
            <?php endif; ?>
            <table class="table">
                <thead><tr><th><?= e(__('system.job')) ?></th><th><?= e(__('system.job_when')) ?></th><th><?= e(__('system.job_last')) ?></th></tr></thead>
                <tbody>
                <?php foreach (\App\Services\Scheduler::status() as $name => $j): ?>
                    <tr>
                        <td><?= e(__('system.job_' . $name)) ?></td>
                        <td class="muted"><?= e(__('system.every_' . $j['every'], ['hour' => sprintf('%02d:00', $j['hour'])])) ?></td>
                        <td>
                            <?= e($j['last'] ? format_date($j['last'], true) : '—') ?>
                            <?php if ($j['error']): ?><div class="text-warn small"><?= e($j['error']) ?></div><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="muted small" style="margin-top:12px"><?= e(__('system.cron_hint')) ?></p>
            <pre class="code-block"><?= e('/usr/local/bin/php ' . BASE_PATH . '/bin/cron.php >/dev/null 2>&1') ?></pre>
        </section>

        <form method="post" action="/settings/system/update" class="card form-card" onsubmit="return confirm(<?= e(json_encode(__('system.update_confirm'), JSON_UNESCAPED_UNICODE)) ?>)">
            <?= csrf_field() ?>
            <h3 class="section-title"><?= e(__('system.updates')) ?></h3>
            <?php if (!$pending): ?>
                <p class="muted"><?= e(__('system.up_to_date')) ?></p>
            <?php else: ?>
                <p class="muted small"><?= e(__('system.pending_hint')) ?></p>
                <ul>
                    <?php foreach ($pending as $name): ?><li><code><?= e($name) ?></code></li><?php endforeach; ?>
                </ul>
                <div class="form-actions">
                    <button class="btn btn-primary"><?= e(__('system.update')) ?></button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
