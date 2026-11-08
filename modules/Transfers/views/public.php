<?php
use App\Core\Lang;
use App\Core\View;
use App\Services\Settings;

$orgName = (string) Settings::get('org.name', config('app.name'));
$showTitle = $t && $state === 'ok';
$total = (int) array_sum(array_column($files, 'size_bytes'));
$sender = (string) ($t['creator_name'] ?? '');
?>
<!doctype html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title><?= e(($showTitle ? $t['title'] . ' · ' : '') . $orgName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
    <?= View::partial('_theme') ?>
</head>
<body class="gk-body">
<div class="gk-wrap">
    <div class="gk-top">
        <?php if (Settings::get('org.logo')): ?>
            <img src="/logo" alt="<?= e($orgName) ?>" class="brand-logo">
        <?php else: ?>
            <span class="brand"><span class="brand-mark"><?= e(mb_strtoupper(mb_substr($orgName, 0, 1))) ?></span><?= e($orgName) ?></span>
        <?php endif; ?>
        <div class="lang-switch">
            <?php foreach (config('app.locales') as $loc): ?>
                <a href="?lang=<?= $loc ?>" class="<?= Lang::locale() === $loc ? 'active' : '' ?>"><?= strtoupper($loc) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (in_array($state, ['notfound', 'expired', 'unavailable'], true)): ?>
        <div class="card gk-message">
            <h1><?= e(__('transfers.public_' . $state)) ?></h1>
            <p class="muted"><?= e(__('transfers.public_' . $state . '_hint')) ?></p>
            <?php if ($state === 'unavailable'): ?><p><a href="/t/<?= e($token) ?>"><?= e(__('transfers.public_back')) ?></a></p><?php endif; ?>
        </div>
    <?php elseif ($state === 'locked'): ?>
        <form method="post" action="/t/<?= e($token) ?>" class="card gk-message">
            <?= csrf_field() ?>
            <h1><?= e(__('transfers.public_locked')) ?></h1>
            <p class="muted"><?= e(__('transfers.public_locked_hint', ['sender' => $sender])) ?></p>
            <?php if ($error): ?><div class="tf-error"><?= e($error) ?></div><?php endif; ?>
            <label class="field">
                <input type="password" name="password" required autofocus placeholder="<?= e(__('transfers.password')) ?>">
            </label>
            <button class="btn btn-primary btn-block"><?= e(__('transfers.public_continue')) ?></button>
        </form>
    <?php else: ?>
        <div class="card gk-card">
            <div class="gk-head">
                <span><?= e(__('transfers.public_from', ['sender' => $sender])) ?></span>
                <h1><?= e($t['title']) ?></h1>
                <p><?= e(__('transfers.summary', ['count' => count($files), 'size' => format_bytes($total)])) ?><?= $t['expires_at'] ? ' · ' . e(__('transfers.public_until', ['date' => format_date($t['expires_at'])])) : '' ?></p>
            </div>
            <?php if ($t['message']): ?>
                <div class="gk-agenda tf-pre"><?= e($t['message']) ?></div>
            <?php endif; ?>
            <div class="gk-form">
                <?php if (count($files) > 1): ?>
                    <a href="/t/<?= e($token) ?>/all" class="btn btn-primary btn-block tf-all"><?= icon('download') ?><?= e(__('transfers.public_all', ['size' => format_bytes($total)])) ?></a>
                <?php endif; ?>
                <?php foreach ($files as $f): $parts = explode('/', $f['relative_path']); $name = array_pop($parts); ?>
                    <a href="/t/<?= e($token) ?>/file/<?= (int) $f['id'] ?>" class="tf-file">
                        <span class="tf-file-text">
                            <strong><?= e($name) ?></strong>
                            <small class="muted"><?= e(($parts ? implode(' / ', $parts) . ' · ' : '') . format_bytes((int) $f['size_bytes'])) ?></small>
                        </span>
                        <?= icon('download') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
