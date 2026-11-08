<?php
use App\Core\View;
use App\Services\TransferService;

$enabled = TransferService::enabled();
?>
<div class="page-head">
    <div>
        <h1><?= e(__('transfers.title')) ?></h1>
        <p class="muted"><?= e(__('transfers.subtitle')) ?></p>
    </div>
    <?php if ($enabled): ?>
        <a href="/transfers/new" class="btn btn-primary"><?= icon('send') ?><?= e(__('transfers.new')) ?></a>
    <?php endif; ?>
</div>

<?php if (!$enabled): ?>
    <div class="card card-body"><?= e(__(can('settings.manage') ? 'transfers.setup_admin' : 'transfers.setup_user')) ?>
        <?php if (can('settings.manage')): ?><a href="/settings/microsoft"><?= e(__('ms.title')) ?></a><?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty empty-lg"><?= e(__('transfers.empty')) ?></div>
    <?php else: ?>
        <div class="tf-table">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= e(__('transfers.col_transfer')) ?></th>
                        <th><?= e(__('transfers.recipient')) ?></th>
                        <th><?= e(__('transfers.col_size')) ?></th>
                        <th><?= e(__('transfers.col_downloads')) ?></th>
                        <th><?= e(__('transfers.expiry')) ?></th>
                        <th><?= e(__('transfers.col_status')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $t): ?>
                        <tr>
                            <td>
                                <a href="/transfers/<?= (int) $t['id'] ?>"><strong><?= e($t['title']) ?></strong></a>
                                <div class="muted small"><?= e(format_date($t['created_at'], true)) ?><?= can('transfers.all') ? ' · ' . e($t['creator_name']) : '' ?><?= $t['password_hash'] ? ' · ' . e(__('transfers.protected')) : '' ?></div>
                            </td>
                            <td><?= e($t['recipient_email'] ?: '—') ?></td>
                            <td class="nowrap"><?= e(__('transfers.summary', ['count' => (int) $t['file_count'], 'size' => format_bytes((int) $t['total_size'])])) ?></td>
                            <td><?= (int) $t['download_count'] ?></td>
                            <td class="nowrap"><?= e($t['expires_at'] ? format_date($t['expires_at']) : __('transfers.expiry_0')) ?></td>
                            <td><?= View::partial('Transfers::_status', ['t' => $t]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
