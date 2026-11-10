<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Yeni restoran</h1>
<?php
$oldLanguageIds = array_map('strval', (array) (old('language_ids') ?? []));
$oldDefault     = (string) old('default_language_id');
?>
<?= form_open_multipart('admin/restaurants') ?>
    <label>Slug (boşsa addan üretilir)
        <input type="text" name="slug" value="<?= esc((string) old('slug'), 'attr') ?>" maxlength="100">
    </label>
    <label>Para birimi
        <input type="text" name="currency_code" value="<?= esc((string) old('currency_code', 'TRY'), 'attr') ?>" maxlength="3" required>
    </label>
    <fieldset>
        <legend>Diller (en az bir) ve varsayılan dil</legend>
        <?php foreach ($allLanguages as $language): ?>
            <label>
                <input type="checkbox" name="language_ids[]" value="<?= esc((string) $language['id'], 'attr') ?>"<?= in_array((string) $language['id'], $oldLanguageIds, true) ? ' checked' : '' ?>>
                <?= esc($language['native_name']) ?> (<?= esc($language['code']) ?>)
            </label>
            <label>
                <input type="radio" name="default_language_id" value="<?= esc((string) $language['id'], 'attr') ?>"<?= $oldDefault === (string) $language['id'] ? ' checked' : '' ?> required> varsayılan
            </label>
        <?php endforeach ?>
    </fieldset>
    <label>Logo (jpg, png, webp — en fazla 2 MB)
        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
    </label>
    <label>
        <input type="checkbox" name="is_active" value="1" checked> Aktif
    </label>
    <?= view('partials/translations', [
        'languages'    => $languages,
        'translations' => [],
        'fields'       => ['name' => 'text', 'description' => 'textarea'],
    ]) ?>
    <fieldset>
        <legend>İlk sahip (owner) — isteğe bağlı, önerilir</legend>
        <label>Ad <input type="text" name="owner_name" value="<?= esc((string) old('owner_name'), 'attr') ?>" maxlength="100"></label>
        <label>E-posta <input type="email" name="owner_email" value="<?= esc((string) old('owner_email'), 'attr') ?>" maxlength="190"></label>
        <label>Şifre (en az 10 karakter, harf + rakam) <input type="password" name="owner_password" autocomplete="new-password"></label>
    </fieldset>
    <button type="submit">Oluştur</button>
<?= form_close() ?>
<?= $this->endSection() ?>
