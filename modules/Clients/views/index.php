<?php
$tabs = ['active' => 'clients.filter_active', 'inactive' => 'clients.filter_inactive', 'all' => 'app.all'];
$query = fn (array $over) => '?' . http_build_query(array_filter($over + $filters));
?>
<div class="page-head">
    <div>
        <h1><?= e(__('clients.title')) ?></h1>
        <p class="muted"><?= e(__('clients.subtitle')) ?></p>
    </div>
    <?php if (can('clients.edit')): ?>
        <a href="/clients/new" class="btn btn-primary"><?= icon('plus') ?><?= e(__('clients.new')) ?></a>
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
        <select name="type" onchange="this.form.submit()" class="toolbar-select">
            <option value=""><?= e(__('clients.all_types')) ?></option>
            <option value="company" <?= $filters['type'] === 'company' ? 'selected' : '' ?>><?= e(__('clients.type_company')) ?></option>
            <option value="person" <?= $filters['type'] === 'person' ? 'selected' : '' ?>><?= e(__('clients.type_person')) ?></option>
        </select>
        <label class="search">
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('clients.search')) ?>">
        </label>
    </form>

    <?php if (!$clients): ?>
        <div class="empty empty-lg"><?= e(__('clients.empty')) ?></div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(__('clients.code')) ?></th>
                    <th><?= e(__('clients.name')) ?></th>
                    <th><?= e(__('clients.contact')) ?></th>
                    <th><?= e(__('clients.tax_id')) ?></th>
                    <th><?= e(__('clients.currency')) ?></th>
                    <th><?= e(__('users.status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr class="row-link <?= $c['is_active'] ? '' : 'row-muted' ?>" onclick="location='/clients/<?= $c['id'] ?>'">
                    <td><span class="code"><?= e($c['code']) ?></span></td>
                    <td>
                        <a href="/clients/<?= $c['id'] ?>" class="cell-main">
                            <strong><?= e($c['name']) ?></strong>
                            <span><?= e(__('clients.type_' . $c['type'])) ?><?= $c['city'] ? ' · ' . e($c['city']) : '' ?></span>
                        </a>
                    </td>
                    <td><?= $c['contact_name'] ? e($c['contact_name']) : '<span class="muted">—</span>' ?></td>
                    <td class="muted"><?= e($c['tax_id'] ?? '—') ?></td>
                    <td><?= e($c['currency']) ?></td>
                    <td>
                        <span class="badge <?= $c['is_active'] ? 'badge-green' : 'badge-gray' ?>">
                            <?= e(__($c['is_active'] ? 'users.active' : 'users.inactive')) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
