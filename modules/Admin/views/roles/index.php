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
            <table class="table">
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
                        <td>
                            <a href="/settings/roles/<?= $r['id'] ?>" class="cell-main">
                                <strong><?= e(localized($r)) ?></strong>
                                <?php if ($r['is_system']): ?><span><?= e(__('roles.system')) ?></span><?php endif; ?>
                            </a>
                        </td>
                        <td><?= (int) $r['user_count'] ?></td>
                        <td class="cell-actions">
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
