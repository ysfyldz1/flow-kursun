<?php
use App\Core\View;

$q = fn (array $over) => '?' . http_build_query($over + ['scope' => $scope, 'state' => $state]);
?>
<div class="page-head">
    <div>
        <h1><?= e(__('tasks.title')) ?></h1>
        <p class="muted"><?= e(__('tasks.subtitle')) ?></p>
    </div>
</div>

<div class="time-layout">
    <div class="stack">
        <section class="card form-card" data-fold-mobile="<?= e(__('tasks.new')) ?>">
            <h3 class="card-title"><?= e(__('tasks.new')) ?></h3>
            <?= View::partial('Tasks::_form', ['t' => null, 'projects' => $projects, 'users' => $users, 'me' => $me]) ?>
        </section>
    </div>

    <div class="card">
        <div class="toolbar">
            <div class="tabs">
                <?php foreach ($scopes as $s): ?>
                    <a href="<?= e($q(['scope' => $s])) ?>" class="<?= $scope === $s ? 'active' : '' ?>"><?= e(__('tasks.scope_' . $s)) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="tabs">
                <a href="<?= e($q(['state' => 'open'])) ?>" class="<?= $state === 'open' ? 'active' : '' ?>"><?= e(__('tasks.state_open')) ?></a>
                <a href="<?= e($q(['state' => 'done'])) ?>" class="<?= $state === 'done' ? 'active' : '' ?>"><?= e(__('tasks.state_done')) ?></a>
            </div>
        </div>
        <?php if (!$rows): ?>
            <div class="empty empty-lg"><?= e(__($state === 'done' ? 'tasks.empty_done' : 'tasks.empty')) ?></div>
        <?php else: ?>
            <?php foreach ($rows as $t): ?>
                <?= View::partial('Tasks::_row', ['t' => $t]) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
