<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php $base = 'panel/branches/' . $branch['id']; ?>
<h1><?= esc($branch['name']) ?> — Ürünler</h1>
<p>
    <a href="<?= site_url('panel/branches') ?>">← Şubeler</a> |
    <a href="<?= site_url($base . '/categories') ?>">Kategoriler</a> |
    <a href="<?= site_url($base . '/products/new') ?>">Yeni ürün</a>
</p>
<form method="get" action="<?= site_url($base . '/products') ?>">
    <label>Kategori
        <select name="category">
            <option value="">Tümü</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= esc((string) $category['id'], 'attr') ?>"<?= $filters['category'] === $category['id'] ? ' selected' : '' ?>><?= esc($category['name']) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <label>Ara
        <input type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" maxlength="100">
    </label>
    <button type="submit">Filtrele</button>
</form>
<table>
    <thead><tr><th>Sıra</th><th>Görsel</th><th>Ad</th><th>Kategori</th><th>Fiyat</th><th>Durum</th><th></th></tr></thead>
    <tbody data-reorder-url="<?= site_url($base . '/products/reorder') ?>">
    <?php foreach ($products as $product): ?>
        <tr data-id="<?= esc((string) $product['id'], 'attr') ?>">
            <td><?= esc((string) $product['sort_order']) ?></td>
            <td><?php if ($product['thumb_url'] !== null): ?><img src="<?= esc($product['thumb_url'], 'attr') ?>" alt="" width="48"><?php endif ?></td>
            <td><?= esc($product['name']) ?></td>
            <td><?= esc($product['category_name']) ?></td>
            <td><?= esc($product['price']) ?></td>
            <td><?= $product['is_active'] ? 'aktif' : 'pasif' ?></td>
            <td>
                <a href="<?= site_url($base . '/products/' . $product['id'] . '/edit') ?>">Düzenle</a>
                <?= form_open($base . '/products/' . $product['id'] . '/toggle', ['style' => 'display:inline']) ?>
                    <button type="submit"><?= $product['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
                <?= form_close() ?>
                <?= form_open($base . '/products/' . $product['id'] . '/delete', ['style' => 'display:inline']) ?>
                    <button type="submit">Sil</button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
