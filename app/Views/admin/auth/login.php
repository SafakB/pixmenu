<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<h1>PixMenu Admin Girişi</h1>
<?= view('partials/login_form', ['action' => 'admin/login', 'error' => $error ?? null]) ?>
<?= $this->endSection() ?>
