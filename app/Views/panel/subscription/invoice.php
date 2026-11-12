<?= $this->extend('layouts/panel') ?>
<?php $this->setVar('title', 'Fatura ' . $invoice['number']) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Abonelik', 'url' => site_url('panel/subscription')],
    ['label' => (string) $invoice['number']],
]) ?>
<?= $this->section('css') ?>
<?= view('partials/billing/print_css') ?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-xxl-9">
        <?= view('partials/billing/invoice_doc', [
            'invoice'             => $invoice,
            'restaurantName'      => $restaurantName,
            'seller'              => $seller,
            'paymentInstructions' => $paymentInstructions,
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
