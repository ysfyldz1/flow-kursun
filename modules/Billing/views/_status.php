<?php
$st = \App\Services\StatementService::paymentStatus($s);
$tone = ['draft' => 'badge-gray', 'unpaid' => 'badge-amber', 'partial' => 'badge-blue-dot', 'paid' => 'badge-green', 'overdue' => 'badge-red', 'cancelled' => 'badge-gray'][$st];
?>
<span class="badge <?= $tone ?>"><?= e(__('billing.status_' . $st)) ?></span>
