<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <h1><?= e(__('guide.title')) ?></h1>
                <p class="muted"><?= e(__('guide.subtitle')) ?></p>
            </div>
        </div>
        <section class="card form-card guide"><?= $html ?></section>
    </div>
</div>
