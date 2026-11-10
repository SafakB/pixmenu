<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1><?= esc($restaurant['name']) ?><?= $restaurant['is_active'] ? '' : ' (pasif)' ?></h1>
<?php if ($restaurant['logo_url'] !== null): ?>
    <p><img src="<?= esc($restaurant['logo_url'], 'attr') ?>" alt="" width="120"></p>
<?php endif ?>
<ul>
    <li>Slug: <?= esc($restaurant['slug']) ?></li>
    <li>Para birimi: <?= esc($restaurant['currency_code']) ?></li>
</ul>
<ul>
    <li><a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/edit') ?>">Bilgileri düzenle</a></li>
    <li><a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/languages') ?>">Diller (<?= esc((string) $counts['languages']) ?>)</a></li>
    <li><a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/branches') ?>">Şubeler (<?= esc((string) $counts['branches']) ?>)</a></li>
    <li><a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/users') ?>">Kullanıcılar (<?= esc((string) $counts['users']) ?>)</a></li>
</ul>
<?= $this->endSection() ?>
