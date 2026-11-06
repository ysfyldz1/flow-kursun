<?php $tone = ['open' => 'badge-green', 'on_hold' => 'badge-amber', 'closed' => 'badge-gray'][$status] ?? 'badge-gray'; ?>
<span class="badge <?= $tone ?>"><?= e(__('projects.status_' . $status)) ?></span>
