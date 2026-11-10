<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Restoranı düzenle</h1>
<?php $default = (string) old('default_language_id', $restaurant['default_language_id']); ?>
<?= form_open_multipart('admin/restaurants/' . $restaurant['id']) ?>
    <label>Slug
        <input type="text" name="slug" value="<?= esc((string) old('slug', $restaurant['slug']), 'attr') ?>" maxlength="100">
    </label>
    <label>Para birimi
        <input type="text" name="currency_code" value="<?= esc((string) old('currency_code', $restaurant['currency_code']), 'attr') ?>" maxlength="3" required>
    </label>
    <label>Varsayılan dil
        <select name="default_language_id" required>
            <?php foreach ($languages as $language): ?>
                <option value="<?= esc((string) $language['id'], 'attr') ?>"<?= $default === (string) $language['id'] ? ' selected' : '' ?>><?= esc($language['native_name']) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <label>Logo (jpg, png, webp — en fazla 2 MB)
        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
    </label>
    <?php if ($logo_url !== null): ?>
        <p><img src="<?= esc($logo_url, 'attr') ?>" alt="" width="120"></p>
    <?php endif ?>
    <label>
        <input type="checkbox" name="is_active" value="1"<?= (int) $restaurant['is_active'] === 1 ? ' checked' : '' ?>> Aktif
    </label>
    <?= view('partials/translations', [
        'languages'    => $languages,
        'translations' => $translations,
        'fields'       => ['name' => 'text', 'description' => 'textarea'],
    ]) ?>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?= $this->endSection() ?>
