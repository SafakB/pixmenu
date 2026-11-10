<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php $base = 'panel/branches/' . $branch['id']; ?>
<h1><?= esc($branch['name']) ?> — Kategoriler</h1>
<p>
    <a href="<?= site_url('panel/branches') ?>">← Şubeler</a> |
    <a href="<?= site_url($base . '/products') ?>">Ürünler</a> |
    <a href="<?= site_url($base . '/categories/new') ?>">Yeni kategori</a>
</p>
<table>
    <thead><tr><th>Sıra</th><th>Görsel</th><th>Ad</th><th>Ürün</th><th>Durum</th><th></th></tr></thead>
    <tbody data-reorder-url="<?= site_url($base . '/categories/reorder') ?>">
    <?php foreach ($categories as $category): ?>
        <tr data-id="<?= esc((string) $category['id'], 'attr') ?>">
            <td><?= esc((string) $category['sort_order']) ?></td>
            <td><?php if ($category['image_url'] !== null): ?><img src="<?= esc($category['image_url'], 'attr') ?>" alt="" width="48"><?php endif ?></td>
            <td><?= esc($category['name']) ?></td>
            <td><a href="<?= site_url($base . '/products?category=' . $category['id']) ?>"><?= esc((string) $category['product_count']) ?></a></td>
            <td><?= $category['is_active'] ? 'aktif' : 'pasif' ?></td>
            <td>
                <a href="<?= site_url($base . '/categories/' . $category['id'] . '/edit') ?>">Düzenle</a>
                <?= form_open($base . '/categories/' . $category['id'] . '/toggle', ['style' => 'display:inline']) ?>
                    <button type="submit"><?= $category['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
                <?= form_close() ?>
                <?= form_open($base . '/categories/' . $category['id'] . '/delete', ['style' => 'display:inline']) ?>
                    <button type="submit">Sil</button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
