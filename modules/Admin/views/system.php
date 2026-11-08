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
