<div class="toast toast-<?= e($type) ?>" role="status">
    <span class="toast-icon"><?= icon($type === 'success' ? 'check' : 'alert') ?></span>
    <div class="toast-body">
        <strong><?= e(__('app.toast_' . $type)) ?></strong>
        <span><?= e($message) ?></span>
    </div>
    <button type="button" class="toast-close" aria-label="×">✕</button>
    <span class="toast-bar"></span>
</div>
