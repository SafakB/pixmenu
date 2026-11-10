<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= esc($title ?? 'PixMenu') ?></title>
</head>
<body>
<main>
    <?= $this->include('partials/flash') ?>
    <?= $this->renderSection('content') ?>
</main>
</body>
</html>
