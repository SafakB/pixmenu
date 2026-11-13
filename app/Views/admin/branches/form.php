<?= $this->extend('layouts/admin') ?>
<?php
$indexUrl = site_url('admin/restaurants/' . $restaurant['id'] . '/branches');
$heading  = $branch === null ? 'Yeni şube' : 'Şubeyi düzenle';
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')],
    ['label' => $restaurant['name'], 'url' => site_url('admin/restaurants/' . $restaurant['id'])],
    ['label' => 'Şubeler', 'url' => $indexUrl],
    ['label' => $heading],
]) ?>
<?= $this->section('content') ?>
<?= form_open($action) ?>
    <?= view('partials/branch_fields', [
        'branch'       => $branch,
        'languages'    => $languages,
        'translations' => $translations,
        'templates'    => $templates,
        'cancel_url'   => $indexUrl,
    ]) ?>
<?= form_close() ?>
<?= $this->endSection() ?>
