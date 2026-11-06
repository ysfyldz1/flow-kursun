<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <h1><?= e(__('users.title')) ?></h1>
                <p class="muted"><?= e(__('users.subtitle')) ?></p>
            </div>
            <a href="/settings/users/new" class="btn btn-primary"><?= icon('plus') ?><?= e(__('users.new')) ?></a>
        </div>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= e(__('users.name')) ?></th>
                        <th><?= e(__('users.role')) ?></th>
                        <th><?= e(__('users.hourly_rate')) ?></th>
                        <th><?= e(__('users.last_login')) ?></th>
                        <th><?= e(__('users.status')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="<?= $u['is_active'] ? '' : 'row-muted' ?>">
                        <td>
                            <a href="/settings/users/<?= $u['id'] ?>" class="cell-main">
                                <strong><?= e($u['name']) ?></strong>
                                <span><?= e($u['email']) ?><?= $u['title'] ? ' · ' . e($u['title']) : '' ?></span>
                            </a>
                        </td>
                        <td><?= e(localized(['name_tr' => $u['role_tr'], 'name_en' => $u['role_en']])) ?></td>
                        <td><?= $u['hourly_rate'] !== null ? e(format_money((float) $u['hourly_rate'], $u['rate_currency'])) : '<span class="muted">—</span>' ?></td>
                        <td class="muted"><?= $u['last_login_at'] ? e(format_date($u['last_login_at'], true)) : '—' ?></td>
                        <td>
                            <span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-gray' ?>">
                                <?= e(__($u['is_active'] ? 'users.active' : 'users.inactive')) ?>
                            </span>
                        </td>
                        <td class="cell-actions">
                            <form method="post" action="/settings/users/<?= $u['id'] ?>/active">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline"><?= e(__($u['is_active'] ? 'users.deactivate' : 'users.activate')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
