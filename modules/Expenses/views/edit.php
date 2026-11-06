<div class="page-head">
    <div>
        <a href="/expenses" class="back-link">← <?= e(__('expenses.title')) ?></a>
        <h1><?= e(__('expenses.edit')) ?></h1>
    </div>
</div>

<section class="card form-card narrow">
    <?= \App\Core\View::partial('Expenses::_form', ['entry' => $entry, 'projects' => $projects]) ?>
</section>
