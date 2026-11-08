<?php
use App\Services\Settings;

$orgName = Settings::get('org.name', config('app.name'));
?>
<div class="auth-brand">
    <?php if (Settings::get('org.logo')): ?>
        <img src="/logo" alt="<?= e($orgName) ?>" class="brand-logo">
    <?php else: ?>
        <span class="brand-mark"><?= e(mb_strtoupper(mb_substr($orgName, 0, 1))) ?></span><?= e($orgName) ?>
    <?php endif; ?>
</div>
