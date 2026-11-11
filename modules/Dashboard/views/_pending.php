<?php // $pending: PendingService::forCurrentUser() ?>
<section class="card widget pending-card">
    <header class="widget-head">
        <span class="widget-icon"><?= icon('bell') ?></span>
        <h2><?= e(__('pending.title')) ?></h2>
    </header>
    <?php if (!$pending): ?>
        <div class="empty"><?= e(__('pending.all_clear')) ?></div>
    <?php else: ?>
        <div class="pending-groups">
            <?php foreach ($pending as $g): ?>
                <div class="pending-group">
                    <a href="<?= e($g['url']) ?>" class="pending-head">
                        <span class="dot tone-<?= $g['tone'] ?>"></span>
                        <strong><?= e(__('pending.' . $g['key'])) ?></strong>
                        <span class="badge badge-<?= $g['tone'] ?>"><?= $g['count'] ?></span>
                    </a>
                    <?php foreach (array_slice($g['items'], 0, 3) as $i): ?>
                        <a href="<?= e($i['url']) ?>" class="pending-item">
                            <span><?= e($i['title']) ?></span>
                            <?php if ($i['meta']): ?><small class="muted"><?= e($i['meta']) ?></small><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($g['count'] > 3): ?>
                        <a href="<?= e($g['url']) ?>" class="pending-item muted small"><?= e(__('pending.more', ['count' => $g['count'] - 3])) ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
