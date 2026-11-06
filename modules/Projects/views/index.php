<?php
use App\Core\View;

$tabs = ['open' => 'projects.status_open', 'on_hold' => 'projects.status_on_hold', 'closed' => 'projects.status_closed', 'all' => 'app.all'];
$query = fn (array $over) => '?' . http_build_query(array_filter($over + $filters));
?>
<div class="page-head">
    <div>
        <h1><?= e(__('projects.title')) ?></h1>
        <p class="muted"><?= e(__('projects.subtitle')) ?></p>
    </div>
    <?php if (can('projects.edit')): ?>
        <a href="/projects/new" class="btn btn-primary"><?= icon('plus') ?><?= e(__('projects.new')) ?></a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" class="toolbar">
        <div class="tabs">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="<?= e($query(['status' => $key])) ?>" class="<?= $filters['status'] === $key ? 'active' : '' ?>"><?= e(__($label)) ?></a>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <label class="check">
            <input type="checkbox" name="mine" value="1" <?= $filters['mine'] ? 'checked' : '' ?> onchange="this.form.submit()">
            <span><?= e(__('projects.only_mine')) ?></span>
        </label>
        <label class="search">
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('projects.search')) ?>">
        </label>
    </form>

    <?php if (!$projects): ?>
        <div class="empty empty-lg"><?= e(__('projects.empty')) ?></div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(__('projects.number')) ?></th>
                    <th><?= e(__('projects.name')) ?></th>
                    <th><?= e(__('clients.name')) ?></th>
                    <th><?= e(__('projects.responsible')) ?></th>
                    <th><?= e(__('projects.opened_at')) ?></th>
                    <th><?= e(__('users.status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $p): ?>
                <tr class="row-link" onclick="location='/projects/<?= $p['id'] ?>'">
                    <td><span class="code"><?= e($p['number']) ?></span></td>
                    <td>
                        <a href="/projects/<?= $p['id'] ?>" class="cell-main">
                            <strong><?= e($p['name']) ?></strong>
                            <span><?= e(__('projects.type_' . $p['type'])) ?><?= $p['case_number'] ? ' · ' . e($p['case_number']) : '' ?></span>
                        </a>
                    </td>
                    <td><?= e($p['client_name']) ?></td>
                    <td><?= $p['responsible_name'] ? e($p['responsible_name']) : '<span class="muted">—</span>' ?></td>
                    <td class="muted"><?= e(format_date($p['opened_at'])) ?></td>
                    <td><?= View::partial('Projects::_status', ['status' => $p['status']]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
