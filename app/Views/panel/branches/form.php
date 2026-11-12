<?= $this->extend('layouts/panel') ?>
<?php
$indexUrl = site_url('panel/branches');
$heading  = $branch === null ? 'Yeni şube' : 'Şubeyi düzenle';
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Şubeler', 'url' => $indexUrl],
    ['label' => $heading],
]) ?>
<?= $this->section('content') ?>
<?= form_open($action) ?>
    <?= view('partials/branch_fields', [
        'branch'       => $branch,
        'languages'    => $languages,
        'translations' => $translations,
        'cancel_url'   => $indexUrl,
    ]) ?>
<?= form_close() ?>
<?= $this->endSection() ?>
