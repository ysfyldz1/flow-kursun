<!doctype html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('app.offline_title')) ?></title>
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
</head>
<body class="auth-body">
    <div class="auth-card" style="text-align:center">
        <div class="error-code" style="font-size:40px">⚡</div>
        <h1><?= e(__('app.offline_title')) ?></h1>
        <p class="muted"><?= e(__('app.offline_text')) ?></p>
        <button class="btn btn-primary" onclick="location.reload()"><?= e(__('app.retry')) ?></button>
    </div>
</body>
</html>
