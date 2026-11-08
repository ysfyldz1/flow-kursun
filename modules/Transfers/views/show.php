<?php
use App\Core\View;
use App\Services\TransferService;

$url = TransferService::publicUrl($t);
$expired = TransferService::isExpired($t);
$deletesAt = TransferService::deletesAt($t);
$canExtend = $t['status'] === 'ready' && !$t['files_deleted_at'];
$total = (int) array_sum(array_column($files, 'size_bytes'));
?>
<div class="page-head">
    <div>
        <h1><?= e($t['title']) ?></h1>
        <p class="muted"><a href="/transfers"><?= e(__('transfers.back')) ?></a></p>
    </div>
    <?= View::partial('Transfers::_status', ['t' => $t]) ?>
</div>

<div class="stack tf-wrap">
    <section class="card form-card">
        <?php if ($t['status'] === 'uploading'): ?>
            <div class="tf-error"><?= e(__('transfers.half_done')) ?></div>
        <?php elseif ($t['files_deleted_at']): ?>
            <p class="muted"><?= e(__('transfers.files_gone', ['date' => format_date($t['files_deleted_at'])])) ?></p>
        <?php else: ?>
            <div class="tf-link">
                <input id="tf-url" value="<?= e($url) ?>" readonly onclick="this.select()">
                <button type="button" class="btn btn-primary" data-copy="#tf-url" data-copied="<?= e(__('transfers.copied')) ?>"><?= e(__('transfers.copy')) ?></button>
                <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="btn btn-outline"><?= e(__('transfers.open')) ?></a>
            </div>
        <?php endif; ?>

        <dl class="tf-facts">
            <div><dt><?= e(__('transfers.sender')) ?></dt><dd><?= e($t['creator_name'] ?? '—') ?> · <?= e(format_date($t['created_at'], true)) ?></dd></div>
            <div><dt><?= e(__('transfers.expiry')) ?></dt><dd><?= e($t['expires_at'] ? format_date($t['expires_at'], true) : __('transfers.expiry_0')) ?></dd></div>
            <?php if ($deletesAt): ?><div><dt><?= e(__('transfers.deletes_at')) ?></dt><dd><?= e(format_date($deletesAt)) ?></dd></div><?php endif; ?>
            <?php if ($t['recipient_email']): ?><div><dt><?= e(__('transfers.recipient')) ?></dt><dd><?= e($t['recipient_email']) ?></dd></div><?php endif; ?>
            <?php if ($t['message']): ?><div><dt><?= e(__('transfers.message')) ?></dt><dd class="tf-pre"><?= e($t['message']) ?></dd></div><?php endif; ?>
            <div><dt><?= e(__('transfers.files')) ?></dt><dd><?= e(__('transfers.summary', ['count' => count($files), 'size' => format_bytes($total)])) ?></dd></div>
            <div><dt><?= e(__('transfers.col_downloads')) ?></dt><dd><?= (int) $t['download_count'] ?><?= $t['first_downloaded_at'] ? ' · ' . e(__('transfers.first_download', ['date' => format_date($t['first_downloaded_at'], true)])) : '' ?></dd></div>
            <?php if ($t['password_hash']): ?><div><dt><?= e(__('transfers.password')) ?></dt><dd><?= e(__('transfers.protected')) ?></dd></div><?php endif; ?>
        </dl>

        <div class="form-actions tf-actions">
            <?php if ($canExtend): ?>
                <form method="post" action="/transfers/<?= (int) $t['id'] ?>/expiry">
                    <?= csrf_field() ?>
                    <select name="expiry" class="toolbar-select" onchange="if (this.value && (this.value !== 'now' || confirm(<?= e(json_encode(__('transfers.end_confirm'), JSON_UNESCAPED_UNICODE)) ?>))) this.form.submit(); else this.value = '';">
                        <option value=""><?= e(__('transfers.change_expiry')) ?></option>
                        <option value="7"><?= e(__('transfers.from_today_7')) ?></option>
                        <option value="30"><?= e(__('transfers.from_today_30')) ?></option>
                        <option value="0"><?= e(__('transfers.expiry_0')) ?></option>
                        <?php if (!$expired): ?><option value="now"><?= e(__('transfers.end_now')) ?></option><?php endif; ?>
                    </select>
                </form>
                <?php if ($t['recipient_email'] && !$expired): ?>
                    <form method="post" action="/transfers/<?= (int) $t['id'] ?>/resend">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline"><?= e(__('transfers.resend')) ?></button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <form method="post" action="/transfers/<?= (int) $t['id'] ?>/delete" onsubmit="return confirm(<?= e(json_encode(__('transfers.delete_confirm'), JSON_UNESCAPED_UNICODE)) ?>)">
                <?= csrf_field() ?>
                <button class="btn btn-danger"><?= e(__('app.delete')) ?></button>
            </form>
        </div>
    </section>

    <div class="grid grid-2">
        <section class="card">
            <div class="toolbar"><strong><?= e(__('transfers.files')) ?></strong></div>
            <div class="tf-scroll">
                <table class="table table-compact">
                    <?php foreach ($files as $f): ?>
                        <tr>
                            <td class="tf-path"><?= e($f['relative_path']) ?></td>
                            <td class="nowrap muted"><?= e(format_bytes((int) $f['size_bytes'])) ?></td>
                            <td class="nowrap"><?= (int) $f['download_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </section>
        <section class="card">
            <div class="toolbar"><strong><?= e(__('transfers.history')) ?></strong></div>
            <?php if (!$downloads): ?>
                <div class="empty empty-lg"><?= e(__('transfers.no_downloads')) ?></div>
            <?php else: ?>
                <div class="tf-scroll">
                    <table class="table table-compact">
                        <?php foreach ($downloads as $d): ?>
                            <tr>
                                <td class="tf-path"><?= e($d['file_id'] ? $d['relative_path'] : __('transfers.all_files')) ?></td>
                                <td class="nowrap muted"><?= e(format_date($d['created_at'], true)) ?></td>
                                <td class="nowrap muted"><?= e($d['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<script src="<?= asset('transfer.js') ?>" defer></script>
