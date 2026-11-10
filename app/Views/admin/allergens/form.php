<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1><?= $allergen === null ? 'Yeni alerjen' : 'Alerjeni düzenle' ?></h1>
<?= form_open_multipart($action) ?>
    <label>Kod (ör. gluten, milk)
        <input type="text" name="code" value="<?= esc((string) old('code', $allergen['code'] ?? ''), 'attr') ?>" maxlength="40" required>
    </label>
    <label>İkon (svg, png, webp — en fazla 512 KB)
        <input type="file" name="icon" accept=".svg,.png,.webp">
    </label>
    <?php if ($icon_url !== null): ?>
        <p><img src="<?= esc($icon_url, 'attr') ?>" alt="" width="64" height="64"></p>
    <?php endif ?>
    <label>Sıra
        <input type="number" name="sort_order" min="0" max="65535" value="<?= esc((string) old('sort_order', $allergen['sort_order'] ?? 0), 'attr') ?>">
    </label>
    <label>
        <input type="checkbox" name="is_active" value="1"<?= (int) old('is_active', $allergen['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif
    </label>
    <?= view('partials/translations', [
        'languages'    => $languages,
        'translations' => $translations,
        'fields'       => ['name' => 'text', 'description' => 'textarea'],
    ]) ?>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?php if ($allergen !== null && $icon_url !== null): ?>
    <?= form_open('admin/allergens/' . $allergen['id'] . '/icon/delete') ?>
        <button type="submit">İkonu sil</button>
    <?= form_close() ?>
<?php endif ?>
<?= $this->endSection() ?>
