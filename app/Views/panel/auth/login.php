<?= $this->extend('layouts/auth') ?>
<?= $this->section('heading') ?>Restoran Paneli<?= $this->endSection() ?>
<?= $this->section('subtitle') ?>Menünüzü yönetmek için giriş yapın.<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/login_form', [
    'action'    => 'panel/login',
    'error'     => $error ?? null,
    'googleUrl' => $googleUrl ?? null,
    'signupUrl' => $signupUrl ?? null,
]) ?>
<?= $this->endSection() ?>
