<div class="page-head">
    <div>
        <a href="/time?week=<?= e($entry['work_date']) ?>" class="back-link">← <?= e(__('time.title')) ?></a>
        <h1><?= e(__('time.edit')) ?></h1>
    </div>
</div>

<section class="card form-card narrow">
    <?= \App\Core\View::partial('Time::_form', ['entry' => $entry, 'projects' => $projects, 'userId' => $entry['user_id']]) ?>
</section>

<?= \App\Core\View::partial('Time::_script') ?>
