<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <title><?= esc($title ?? 'PixMenu Admin') ?></title>
</head>
<body>
<header>
    <nav>
        <a href="<?= site_url('admin') ?>">Admin</a> |
        <a href="<?= site_url('admin/restaurants') ?>">Restoranlar</a> |
        <a href="<?= site_url('admin/languages') ?>">Diller</a> |
        <a href="<?= site_url('admin/allergens') ?>">Alerjenler</a>
        <span>— <?= esc($currentAdmin['name'] ?? '') ?></span>
        <?= form_open('admin/logout', ['style' => 'display:inline']) ?>
            <button type="submit">Çıkış</button>
        <?= form_close() ?>
    </nav>
</header>
<main>
    <?= $this->include('partials/flash') ?>
    <?= $this->renderSection('content') ?>
</main>
</body>
</html>
