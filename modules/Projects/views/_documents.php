<?php
$canShare = can('documents.manage') && $project['portal_visible'];
$base = '/projects/' . $project['id'] . '/documents/';
?>
<?php if ($crumbs): ?>
    <nav class="docs-crumbs">
        <button type="button" class="link" data-folder="">⌂ <?= e(__('docs.title')) ?></button>
        <?php foreach ($crumbs as $n => [$cid, $cname]): ?>
            <span class="muted">›</span>
            <?php if ($n === count($crumbs) - 1): ?><strong><?= e($cname) ?></strong>
            <?php else: ?><button type="button" class="link" data-folder="<?= e($cid) ?>"><?= e($cname) ?></button><?php endif; ?>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
<div hidden data-current-folder="<?= $crumbs ? e($folder) : '' ?>"></div>
<?php if (!$items): ?>
    <div class="empty"><?= e(__('docs.empty')) ?></div>
<?php else: ?>
    <table class="table">
        <?php foreach ($items as $i): $share = $shares[$i['id']] ?? null; ?>
            <tr>
                <td class="cell-main">
                    <?php if (isset($i['folder'])): ?>
                        <button type="button" class="link docs-folder" data-folder="<?= e($i['id']) ?>"><strong><?= icon('folder', 'icon icon-sm') ?> <?= e($i['name']) ?></strong></button>
                        <span><?= e(__('docs.folder_items', ['count' => $i['folder']['childCount'] ?? 0])) ?></span>
                    <?php else: ?>
                        <a href="<?= $base . e($i['id']) ?>/open" target="_blank" rel="noopener" class="link"><strong><?= e($i['name']) ?></strong></a>
                        <span>
                            <?= e(format_bytes($i['size'] ?? null)) ?> ·
                            <?= e(format_date(date('Y-m-d H:i:s', strtotime($i['lastModifiedDateTime'])), true)) ?>
                            <?php if (!empty($i['lastModifiedBy']['user']['displayName'])): ?> · <?= e($i['lastModifiedBy']['user']['displayName']) ?><?php endif; ?>
                            <?php if ($share && $share['source'] === 'client'): ?> · <span class="badge badge-amber"><?= e(__('docs.from_client')) ?></span><?php endif; ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td class="cell-actions nowrap">
                    <?php if (isset($i['folder'])): ?>
                        <a href="<?= e($i['webUrl']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline" title="<?= e(__('docs.open_in_m365')) ?>">↗</a>
                    <?php else: ?>
                        <?php if ($canShare): ?>
                            <button type="button" class="btn btn-sm <?= $share ? 'btn-primary' : 'btn-outline' ?>" data-share="<?= $base . e($i['id']) ?>/share"
                                    title="<?= e(__($share ? 'docs.unshare_hint' : 'docs.share_hint')) ?>"><?= e(__($share ? 'docs.shared' : 'docs.share')) ?></button>
                        <?php elseif ($share): ?>
                            <span class="badge badge-blue-dot"><?= e(__('docs.shared')) ?></span>
                        <?php endif; ?>
                        <a href="<?= $base . e($i['id']) ?>/download" class="btn btn-sm btn-outline" title="<?= e(__('docs.download')) ?>">↓</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
