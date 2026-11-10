<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<h1><?= $user === null ? 'Yeni şube yöneticisi' : 'Şube yöneticisini düzenle' ?></h1>
<p><a href="<?= site_url('panel/users') ?>">← Kullanıcılar</a></p>
<?= form_open($action) ?>
    <?= view('partials/user_fields', [
        'user'      => $user,
        'branches'  => $branches,
        'assigned'  => $assigned,
        'allowRole' => false,
    ]) ?>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?= $this->endSection() ?>
