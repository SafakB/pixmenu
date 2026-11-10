<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<h1><?= $branch === null ? 'Yeni şube' : 'Şubeyi düzenle' ?></h1>
<p><a href="<?= site_url('panel/branches') ?>">← Şubeler</a></p>
<?= form_open($action) ?>
    <?= view('partials/branch_fields', [
        'branch'       => $branch,
        'languages'    => $languages,
        'translations' => $translations,
    ]) ?>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?= $this->endSection() ?>
