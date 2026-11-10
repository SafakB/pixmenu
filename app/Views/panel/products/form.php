<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<?php
$base           = 'panel/branches/' . $branch['id'];
$categoryId     = (string) old('category_id', $product['category_id'] ?? '');
$oldAllergens   = old('allergens');
$allergenLevels = is_array($oldAllergens) ? $oldAllergens : $selectedAllergens;
?>
<h1><?= esc($branch['name']) ?> — <?= $product === null ? 'Yeni ürün' : 'Ürünü düzenle' ?></h1>
<p><a href="<?= site_url($base . '/products') ?>">← Ürünler</a></p>
<?= form_open_multipart($action) ?>
    <label>Kategori
        <select name="category_id" required>
            <option value="">Seçin</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= esc((string) $category['id'], 'attr') ?>"<?= $categoryId === (string) $category['id'] ? ' selected' : '' ?>><?= esc($category['name']) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <label>Fiyat (<?= esc($currency) ?>)
        <input type="text" name="price" inputmode="decimal" value="<?= esc((string) old('price', $product['price'] ?? ''), 'attr') ?>" required>
    </label>
    <label>Görsel (jpg, png, webp — en fazla 5 MB)
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
    </label>
    <?php if ($image_url !== null): ?>
        <p><img src="<?= esc($image_url, 'attr') ?>" alt="" width="200"></p>
    <?php endif ?>
    <label>Sıra (boşsa sona eklenir)
        <input type="number" name="sort_order" min="0" max="65535" value="<?= esc((string) old('sort_order', $product['sort_order'] ?? ''), 'attr') ?>">
    </label>
    <label>
        <input type="checkbox" name="is_active" value="1"<?= (int) ($product['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif
    </label>
    <?= view('partials/translations', [
        'languages'    => $languages,
        'translations' => $translations,
        'fields'       => ['name' => 'text', 'description' => 'textarea'],
    ]) ?>
    <fieldset>
        <legend>Alerjenler</legend>
        <?php foreach ($allergens as $allergen): ?>
            <?php $level = (string) ($allergenLevels[$allergen['id']] ?? ''); ?>
            <label>
                <?php if ($allergen['icon_url'] !== null): ?><img src="<?= esc($allergen['icon_url'], 'attr') ?>" alt="" width="20" height="20"><?php endif ?>
                <?= esc($allergen['name']) ?>
                <select name="allergens[<?= esc((string) $allergen['id'], 'attr') ?>]">
                    <option value="">—</option>
                    <option value="contains"<?= $level === 'contains' ? ' selected' : '' ?>>içerir</option>
                    <option value="may_contain"<?= $level === 'may_contain' ? ' selected' : '' ?>>eser miktarda içerebilir</option>
                </select>
            </label>
        <?php endforeach ?>
    </fieldset>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?php if ($product !== null && $image_url !== null): ?>
    <?= form_open($base . '/products/' . $product['id'] . '/image/delete') ?>
        <button type="submit">Görseli sil</button>
    <?= form_close() ?>
<?php endif ?>
<?= $this->endSection() ?>
