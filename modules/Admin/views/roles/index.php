<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <h1><?= e(__('roles.title')) ?></h1>
                <p class="muted"><?= e(__('roles.subtitle')) ?></p>
            </div>
            <a href="/settings/roles/new" class="btn btn-primary"><?= icon('plus') ?><?= e(__('roles.new')) ?></a>
        </div>

        <div class="card">
            <table class="table table-m">
                <thead>
                    <tr>
                        <th><?= e(__('roles.name')) ?></th>
                        <th><?= e(__('roles.users')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($roles as $r): ?>
                    <tr>
                        <td class="m-title">
                            <a href="/settings/roles/<?= $r['id'] ?>" class="cell-main">
                                <strong><?= e(localized($r)) ?></strong>
                                <?php if ($r['is_system']): ?><span><?= e(__('roles.system')) ?></span><?php endif; ?>
                            </a>
                        </td>
                        <td class="m-value"><?= (int) $r['user_count'] ?><span class="m-label"> <?= e(__('roles.users_short')) ?></span></td>
                        <td class="cell-actions <?= $r['is_system'] ? 'm-empty' : 'm-actions' ?>">
                            <?php if (!$r['is_system']): ?>
                                <form method="post" action="/settings/roles/<?= $r['id'] ?>/delete" onsubmit="return confirm('<?= e(__('app.confirm_delete')) ?>')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline btn-danger"><?= e(__('app.delete')) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
