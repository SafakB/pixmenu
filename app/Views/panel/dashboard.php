<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<h1><?= esc($restaurantName) ?></h1>
<h2>Şubeler</h2>
<ul>
    <?php foreach ($branches as $branch): ?>
        <li>
            <?= esc($branch['name']) ?><?= $branch['is_active'] ? '' : ' (pasif)' ?> —
            <a href="<?= site_url('panel/branches/' . $branch['id'] . '/categories') ?>">Kategoriler</a> ·
            <a href="<?= site_url('panel/branches/' . $branch['id'] . '/products') ?>">Ürünler</a> ·
            <a href="<?= site_url('m/' . $branch['public_code']) ?>">Menü</a>
        </li>
    <?php endforeach ?>
</ul>
<?= $this->endSection() ?>
