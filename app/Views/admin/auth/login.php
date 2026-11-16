<?= $this->extend('layouts/auth') ?>
<?= $this->section('heading') ?>Yönetim Paneli<?= $this->endSection() ?>
<?= $this->section('subtitle') ?>PixMenu yönetimine giriş yapın.<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/login_form', ['action' => 'admin/login', 'error' => $error ?? null, 'googleUrl' => null, 'signupUrl' => null]) ?>
<?= $this->endSection() ?>
