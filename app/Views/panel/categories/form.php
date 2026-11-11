<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<h1><?= esc($branch['name']) ?> — <?= $category === null ? 'Yeni kategori' : 'Kategoriyi düzenle' ?></h1>
<p><a href="<?= site_url('panel/branches/' . $branch['id'] . '/categories') ?>">← Kategoriler</a></p>
<?= form_open_multipart($action) ?>
    <label>Görsel (jpg, png, webp — en fazla 3 MB)
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
    </label>
    <?php if ($image_url !== null): ?>
        <p><img src="<?= esc($image_url, 'attr') ?>" alt="" width="160"></p>
        <label><input type="checkbox" name="remove_image" value="1"> Görseli kaldır</label>
    <?php endif ?>
    <label>Sıra (boşsa sona eklenir)
        <input type="number" name="sort_order" min="0" max="65535" value="<?= esc((string) old('sort_order', $category['sort_order'] ?? '', false), 'attr') ?>">
    </label>
    <label>
        <input type="checkbox" name="is_active" value="1"<?= (int) ($category['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif
    </label>
    <?= view('partials/translations', [
        'languages'    => $languages,
        'translations' => $translations,
        'fields'       => ['name' => 'text', 'description' => 'textarea'],
    ]) ?>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?= $this->endSection() ?>
