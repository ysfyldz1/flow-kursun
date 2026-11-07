<?php
$tone = ['pending' => 'amber', 'approved' => 'green', 'rejected' => 'red', 'cancelled' => 'gray'][$l['status']] ?? 'gray';
?>
<span class="badge badge-<?= $tone ?>"><?= e(__('leaves.status_' . $l['status'])) ?></span>
