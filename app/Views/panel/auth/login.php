<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<h1>Restoran Paneli Girişi</h1>
<?= view('partials/login_form', ['action' => 'panel/login', 'error' => $error ?? null]) ?>
<?= $this->endSection() ?>
