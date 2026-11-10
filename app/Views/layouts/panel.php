<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <title><?= esc($title ?? 'PixMenu Panel') ?></title>
</head>
<body>
<header>
    <nav>
        <a href="<?= site_url('panel') ?>">Panel</a> |
        <a href="<?= site_url('panel/branches') ?>">Şubeler</a> |
        <?php if (! empty($isOwner)): ?>
            <a href="<?= site_url('panel/restaurant') ?>">Restoran</a> |
            <a href="<?= site_url('panel/users') ?>">Kullanıcılar</a> |
        <?php endif ?>
        <a href="<?= site_url('panel/profile') ?>">Profil</a>
        <span>— <?= esc($currentUser['name'] ?? '') ?></span>
        <?= form_open('panel/logout', ['style' => 'display:inline']) ?>
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
