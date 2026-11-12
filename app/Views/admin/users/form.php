<?= $this->extend('layouts/admin') ?>
<?php
$indexUrl = site_url('admin/restaurants/' . $restaurant['id'] . '/users');
$heading  = $user === null ? 'Yeni kullanıcı' : 'Kullanıcıyı düzenle';
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')],
    ['label' => $restaurant['name'], 'url' => site_url('admin/restaurants/' . $restaurant['id'])],
    ['label' => 'Kullanıcılar', 'url' => $indexUrl],
    ['label' => $heading],
]) ?>
<?= $this->section('content') ?>
<?= form_open($action) ?>
    <?= view('partials/user_fields', [
        'user'       => $user,
        'branches'   => $branches,
        'assigned'   => $assigned,
        'allowRole'  => true,
        'cancel_url' => $indexUrl,
    ]) ?>
<?= form_close() ?>
<?= $this->endSection() ?>
<?= $this->section('js') ?>
<script src="<?= asset_url('js/pages/password-addon.init.js') ?>"></script>
<?= $this->endSection() ?>
