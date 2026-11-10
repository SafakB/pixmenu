<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<h1>Restoran bilgileri</h1>
<p>Slug: <?= esc($restaurant['slug']) ?> · Para birimi: <?= esc($restaurant['currency_code']) ?> (yalnızca yönetici değiştirebilir)</p>
<?= form_open_multipart('panel/restaurant') ?>
    <label>Logo (jpg, png, webp — en fazla 2 MB)
        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
    </label>
    <?php if ($logo_url !== null): ?>
        <p><img src="<?= esc($logo_url, 'attr') ?>" alt="" width="120"></p>
    <?php endif ?>
    <?= view('partials/translations', [
        'languages'    => $languages,
        'translations' => $translations,
        'fields'       => ['name' => 'text', 'description' => 'textarea'],
    ]) ?>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?php if ($logo_url !== null): ?>
    <?= form_open('panel/restaurant/logo/delete') ?>
        <button type="submit">Logoyu sil</button>
    <?= form_close() ?>
<?php endif ?>
<?= $this->endSection() ?>
