<?php
use App\Services\TransferService;

[$key, $class] = match (true) {
    $t['status'] === 'uploading'    => ['transfers.status_uploading', 'badge-red'],
    (bool) $t['files_deleted_at']   => ['transfers.status_deleted', 'badge-gray'],
    TransferService::isExpired($t)  => ['transfers.status_expired', 'badge-gray'],
    default                         => ['transfers.status_active', 'badge-green'],
};
?>
<span class="badge <?= $class ?>"><?= e(__($key)) ?></span>
