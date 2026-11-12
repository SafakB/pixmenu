<?php
/**
 * Yazdırılabilir fatura gövdesi (admin + panel). Sayfaya ayrıca partials/billing/print_css eklenir.
 * Parametreler: invoice (satır + display_status, method_label), restaurantName, seller{name, address, tax_info}, paymentInstructions
 */
$cur = $invoice['currency_code'];
?>
<div class="card invoice-doc">
    <div class="card-header border-bottom-dashed p-4">
        <div class="d-flex flex-wrap gap-3">
            <div class="flex-grow-1">
                <h4 class="mb-1"><i class="ri-restaurant-2-line text-primary align-bottom me-1"></i><?= esc($seller['name']) ?></h4>
                <?php if ($seller['address'] !== ''): ?>
                    <p class="text-muted mb-1"><?= nl2br(esc($seller['address'])) ?></p>
                <?php endif ?>
                <?php if ($seller['tax_info'] !== ''): ?>
                    <p class="text-muted mb-0"><?= esc($seller['tax_info']) ?></p>
                <?php endif ?>
            </div>
            <div class="flex-shrink-0 text-sm-end">
                <h6 class="text-muted text-uppercase fw-semibold mb-1">Tahsilat kaydı</h6>
                <p class="text-muted fs-12 mb-0">Resmi fatura değildir; e-fatura ayrıca düzenlenir.</p>
            </div>
        </div>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-lg-3 col-6">
                <p class="text-muted mb-2 text-uppercase fw-semibold">Fatura no</p>
                <h5 class="fs-14 mb-0"><?= esc((string) $invoice['number']) ?></h5>
            </div>
            <div class="col-lg-3 col-6">
                <p class="text-muted mb-2 text-uppercase fw-semibold">Kesim / vade</p>
                <h5 class="fs-14 mb-0"><?= esc(pix_date($invoice['issued_at'])) ?> <small class="text-muted">→ <?= esc(pix_date($invoice['due_date'])) ?></small></h5>
            </div>
            <div class="col-lg-3 col-6">
                <p class="text-muted mb-2 text-uppercase fw-semibold">Durum</p>
                <?= view('partials/billing/badge', ['kind' => 'invoice', 'status' => $invoice['display_status']]) ?>
            </div>
            <div class="col-lg-3 col-6">
                <p class="text-muted mb-2 text-uppercase fw-semibold">Toplam</p>
                <h5 class="fs-14 mb-0"><?= esc(pix_money($invoice['total'], $cur)) ?></h5>
            </div>
        </div>
    </div>
    <div class="card-body p-4 border-top border-top-dashed">
        <h6 class="text-muted text-uppercase fw-semibold mb-2">Müşteri</h6>
        <p class="fw-medium mb-0"><?= esc($restaurantName) ?></p>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-borderless align-middle mb-0">
                <thead>
                    <tr class="table-active">
                        <th scope="col">Açıklama</th>
                        <th scope="col" class="text-nowrap">Dönem</th>
                        <th scope="col" class="text-end">Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="fw-medium"><?= esc($invoice['description']) ?></span></td>
                        <td class="text-nowrap"><?= esc(pix_date($invoice['period_start'])) ?> – <?= esc(pix_date($invoice['period_end'])) ?></td>
                        <td class="text-end text-nowrap"><?= esc(pix_money($invoice['subtotal'], $cur)) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="border-top border-top-dashed mt-2">
            <table class="table table-borderless table-nowrap align-middle mb-0 ms-auto" style="max-width:320px">
                <tbody>
                    <tr><td>Ara toplam</td><td class="text-end"><?= esc(pix_money($invoice['subtotal'], $cur)) ?></td></tr>
                    <?php if ((float) $invoice['discount'] > 0): ?>
                        <tr><td>İndirim</td><td class="text-end">− <?= esc(pix_money($invoice['discount'], $cur)) ?></td></tr>
                    <?php endif ?>
                    <tr><td>KDV (%<?= esc(pix_money($invoice['tax_rate'])) ?>)</td><td class="text-end"><?= esc(pix_money($invoice['tax_amount'], $cur)) ?></td></tr>
                    <tr class="border-top border-top-dashed fs-15">
                        <th scope="row">Genel toplam</th>
                        <th class="text-end"><?= esc(pix_money($invoice['total'], $cur)) ?></th>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php if ($invoice['status'] === 'paid'): ?>
            <div class="mt-3">
                <h6 class="text-muted text-uppercase fw-semibold mb-2">Ödeme</h6>
                <p class="text-muted mb-1">Tarih: <span class="fw-medium"><?= esc(pix_date($invoice['paid_at'])) ?></span></p>
                <p class="text-muted mb-1">Yöntem: <span class="fw-medium"><?= esc((string) ($invoice['method_label'] ?? '—')) ?></span></p>
                <?php if ($invoice['payment_reference'] !== null): ?>
                    <p class="text-muted mb-0">Referans: <span class="fw-medium"><?= esc($invoice['payment_reference']) ?></span></p>
                <?php endif ?>
            </div>
        <?php elseif ($invoice['status'] === 'pending' && $paymentInstructions !== ''): ?>
            <div class="mt-4">
                <div class="alert alert-info mb-0">
                    <span class="fw-semibold">Ödeme bilgisi:</span> <?= nl2br(esc($paymentInstructions)) ?>
                </div>
            </div>
        <?php elseif ($invoice['status'] === 'void'): ?>
            <div class="mt-4">
                <div class="alert alert-secondary mb-0">Bu fatura iptal edilmiştir.</div>
            </div>
        <?php endif ?>
        <div class="hstack gap-2 justify-content-end d-print-none mt-4">
            <button type="button" class="btn btn-success" onclick="window.print()"><i class="ri-printer-line align-bottom me-1"></i> Yazdır</button>
        </div>
    </div>
</div>
