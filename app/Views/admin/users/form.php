<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1><?= $user === null ? 'Yeni kullanıcı' : 'Kullanıcıyı düzenle' ?></h1>
<p><a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/users') ?>">← Kullanıcılar</a></p>
<?= form_open($action) ?>
    <?= view('partials/user_fields', [
        'user'      => $user,
        'branches'  => $branches,
        'assigned'  => $assigned,
        'allowRole' => true,
    ]) ?>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?= $this->endSection() ?>
