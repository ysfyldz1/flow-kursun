<!doctype html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flow</title>
    <script src="https://appsforoffice.microsoft.com/lib/1/hosted/office.js"></script>
    <style>
        body { margin: 0; font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #0f172a; display: grid; place-items: center; min-height: 100vh; background: #f4f6f9; }
        .box { background: #fff; border: 1px solid #e6e9ef; border-radius: 12px; padding: 24px; max-width: 320px; text-align: center; }
        .ok { font-size: 28px; color: #16a34a; }
    </style>
</head>
<body>
<div class="box">
    <?php if ($token): ?>
        <div class="ok">✓</div>
        <p><?= e(__('outlook.connected_as', ['name' => $name])) ?></p>
        <p style="color:#64748b;font-size:13px" id="hint"><?= e(__('outlook.connected_close')) ?></p>
    <?php else: ?>
        <p><?= e(__('outlook.need_microsoft')) ?></p>
    <?php endif; ?>
</div>
<?php if ($token): ?>
<script>
// Anahtar panele iletilir; pencere panel tarafından kapatılır
Office.onReady(() => {
    try {
        Office.context.ui.messageParent(JSON.stringify(<?= json_encode(['token' => $token, 'name' => $name], JSON_UNESCAPED_UNICODE) ?>));
    } catch (e) {
        document.getElementById('hint').textContent = <?= json_encode(__('outlook.open_from_outlook')) ?>;
    }
});
</script>
<?php endif; ?>
</body>
</html>
