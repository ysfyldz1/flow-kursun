<div class="page-head">
    <div>
        <h1><?= e(__('dash.hello', ['name' => explode(' ', auth()['name'])[0]])) ?></h1>
        <p class="muted"><?= e(__('dash.subtitle')) ?></p>
    </div>
    <button class="btn btn-outline"><?= icon('sliders') ?><?= e(__('dash.customize')) ?></button>
</div>

<div class="grid grid-4">
    <?php foreach ($stats as $s): ?>
        <div class="card stat">
            <span class="stat-icon tone-<?= $s['tone'] ?>"><?= icon($s['icon']) ?></span>
            <div class="stat-text">
                <strong><?= e($s['label']) ?></strong>
                <span><?= e($s['hint']) ?></span>
            </div>
            <div class="stat-value"><?= e($s['value']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-3">
    <?php foreach ([['clock', 'dash.recent_time'], ['folder', 'dash.my_projects'], ['receipt', 'dash.unpaid_list']] as [$ico, $label]): ?>
        <section class="card widget">
            <header class="widget-head">
                <span class="widget-icon"><?= icon($ico) ?></span>
                <h2><?= e(__($label)) ?></h2>
                <button class="icon-btn icon-btn-sm"><?= icon('more') ?></button>
            </header>
            <div class="empty"><?= e(__('dash.empty')) ?></div>
        </section>
    <?php endforeach; ?>
</div>
