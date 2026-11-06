<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= (int) $code ?></title>
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
</head>
<body class="auth-body">
    <div class="auth-card" style="text-align:center">
        <div class="error-code"><?= (int) $code ?></div>
        <p class="muted"><?= e($message) ?></p>
        <a href="/" class="btn btn-primary"><?= e(__('error.home')) ?></a>
    </div>
</body>
</html>
