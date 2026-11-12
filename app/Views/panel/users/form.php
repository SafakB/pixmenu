<?= $this->extend('layouts/panel') ?>
<?php
$indexUrl = site_url('panel/users');
$heading  = $user === null ? 'Yeni şube yöneticisi' : 'Şube yöneticisini düzenle';
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Kullanıcılar', 'url' => $indexUrl],
    ['label' => $heading],
]) ?>
<?= $this->section('content') ?>
<?= form_open($action) ?>
    <?= view('partials/user_fields', [
        'user'       => $user,
        'branches'   => $branches,
        'assigned'   => $assigned,
        'allowRole'  => false,
        'cancel_url' => $indexUrl,
    ]) ?>
<?= form_close() ?>
<?= $this->endSection() ?>
<?= $this->section('js') ?>
<script src="<?= asset_url('js/pages/password-addon.init.js') ?>"></script>
<?= $this->endSection() ?>
