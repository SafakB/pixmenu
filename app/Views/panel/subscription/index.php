<?= $this->extend('layouts/panel') ?>
<?php $this->setVar('title', 'Abonelik') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Abonelik']]) ?>
<?php
$actions = '<a href="' . site_url('panel/subscription/plans') . '" class="btn btn-soft-primary btn-sm"><i class="ri-price-tag-3-line align-bottom me-1"></i> Paketleri karşılaştır</a>';
?>
<?= $this->section('content') ?>
<?= view('partials/billing/subscription_card', [
    'summary'           => $summary,
    'suspended'         => $suspended,
    'suspension_reason' => $suspension_reason,
    'actions'           => $actions,
]) ?>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h5 class="card-title mb-0 flex-grow-1"><i class="ri-file-list-3-line text-info align-bottom me-1"></i> Faturalar</h5>
    </div>
    <div class="card-body">
        <?php if ($invoices === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-file-list-3-line"></i></div></div>
                <h5 class="mb-1">Henüz fatura yok</h5>
                <p class="text-muted mb-0">Ücretli pakette dönem faturaları burada listelenir.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive table-card">
                <table class="table align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr><th>No</th><th>Dönem</th><th>Kesim</th><th>Vade</th><th>Tutar</th><th>Durum</th><th class="text-end"></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $i): ?>
                        <tr>
                            <td><a href="<?= site_url('panel/subscription/invoices/' . $i['id']) ?>" class="fw-medium"><?= esc((string) $i['number']) ?></a></td>
                            <td class="text-muted"><?= esc(pix_date($i['period_start'])) ?> – <?= esc(pix_date($i['period_end'])) ?></td>
                            <td><?= esc(pix_date($i['issued_at'])) ?></td>
                            <td><?= esc(pix_date($i['due_date'])) ?></td>
                            <td><?= esc(pix_money($i['total'], $i['currency_code'])) ?></td>
                            <td><?= view('partials/billing/badge', ['kind' => 'invoice', 'status' => $i['display_status']]) ?></td>
                            <td class="text-end">
                                <?php if ($i['status'] === 'pending'): ?>
                                    <a href="<?= site_url('panel/subscription/invoices/' . $i['id'] . '/pay') ?>" class="btn btn-sm btn-icon btn-soft-success"
                                       data-bs-toggle="tooltip" title="Öde" aria-label="Öde"><i class="ri-bank-card-line"></i></a>
                                <?php endif ?>
                                <a href="<?= site_url('panel/subscription/invoices/' . $i['id']) ?>" class="btn btn-sm btn-icon btn-soft-info"
                                   data-bs-toggle="tooltip" title="Görüntüle" aria-label="Görüntüle"><i class="ri-eye-line"></i></a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
        <?php if ($paymentInstructions !== ''): ?>
            <div class="alert alert-info mt-3 mb-0"><span class="fw-semibold">Ödeme bilgisi:</span> <?= nl2br(esc($paymentInstructions)) ?></div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
