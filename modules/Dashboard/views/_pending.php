<?php // $pending: PendingService::forCurrentUser() — kişiye bırakılmış kısa not gibi ?>
<section class="card pending-note">
    <?php if (!$pending): ?>
        <p class="pending-line"><span class="dot tone-green"></span><span><?= e(__('pending.all_clear')) ?></span></p>
    <?php endif; ?>
    <?php foreach ($pending as $g): ?>
        <?php if (!$g['items']): ?>
            <p class="pending-line">
                <span class="dot tone-<?= $g['tone'] ?>"></span>
                <a href="<?= e($g['url']) ?>"><?= e($g['text']) ?></a>
            </p>
        <?php else: ?>
            <details class="pending-line">
                <summary>
                    <span class="dot tone-<?= $g['tone'] ?>"></span>
                    <span><?= e($g['text']) ?></span>
                    <span class="pending-toggle"><span class="when-closed"><?= e(__('pending.show')) ?></span><span class="when-open"><?= e(__('pending.hide')) ?></span> <?= icon('chevron-down', 'icon icon-sm') ?></span>
                </summary>
                <div class="pending-items">
                    <?php foreach (array_slice($g['items'], 0, 6) as $i): ?>
                        <a href="<?= e($i['url']) ?>">
                            <span><?= e($i['title']) ?></span>
                            <?php if ($i['meta']): ?><small class="muted"><?= e($i['meta']) ?></small><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($g['count'] > 6): ?>
                        <a href="<?= e($g['url']) ?>" class="muted small"><?= e(__('pending.more', ['count' => $g['count'] - 6])) ?> →</a>
                    <?php endif; ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
