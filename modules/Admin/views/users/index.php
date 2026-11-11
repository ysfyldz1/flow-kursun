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
            <table class="table table-m">
                <thead>
                    <tr>
                        <th><?= e(__('users.name')) ?></th>
                        <th><?= e(__('users.role')) ?></th>
                        <?php if (can('rates.view')): ?><th><?= e(__('users.hourly_rate')) ?></th><?php endif; ?>
                        <th><?= e(__('users.last_login')) ?></th>
                        <th><?= e(__('users.status')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="<?= $u['is_active'] ? '' : 'row-muted' ?>">
                        <td class="m-title">
                            <a href="/settings/users/<?= $u['id'] ?>" class="cell-main">
                                <strong><?= e($u['name']) ?></strong>
                                <span><?= e($u['email']) ?><?= $u['title'] ? ' · ' . e($u['title']) : '' ?></span>
                            </a>
                        </td>
                        <td class="m-meta"><?= e(localized(['name_tr' => $u['role_tr'], 'name_en' => $u['role_en']])) ?></td>
                        <?php if (can('rates.view')): ?><td class="<?= $u['hourly_rate'] !== null ? 'm-meta' : 'm-empty' ?>"><?= $u['hourly_rate'] !== null ? e(format_money((float) $u['hourly_rate'], $u['rate_currency'])) : '<span class="muted">—</span>' ?></td><?php endif; ?>
                        <td class="muted m-hide"><?= $u['last_login_at'] ? e(format_date($u['last_login_at'], true)) : '—' ?></td>
                        <td class="m-value">
                            <span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-gray' ?>">
                                <?= e(__($u['is_active'] ? 'users.active' : 'users.inactive')) ?>
                            </span>
                        </td>
                        <td class="cell-actions m-actions">
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
