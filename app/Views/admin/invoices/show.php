<?= $this->extend('layouts/admin') ?>
<?php
$errors   = session('errors') ?? [];
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
$base     = 'admin/invoices/' . $invoice['id'];
$pending  = $invoice['status'] === 'pending';
$canManage = admin_can('invoices.manage'); // ödendi / indirim
$canVoid   = admin_can('invoices.void');
?>
<?php $this->setVar('title', 'Fatura ' . $invoice['number']) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Faturalar', 'url' => site_url('admin/invoices')],
    ['label' => (string) $invoice['number']],
]) ?>
<?= $this->section('css') ?>
<?= view('partials/billing/print_css') ?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-xxl-8 col-xl-7">
        <?= view('partials/billing/invoice_doc', [
            'invoice'             => $invoice,
            'restaurantName'      => $restaurant['name'],
            'seller'              => $seller,
            'paymentInstructions' => $paymentInstructions,
            'payUrl'              => null,
        ]) ?>
    </div>
    <div class="col-xxl-4 col-xl-5 d-print-none">
        <div class="card">
            <div class="card-body d-flex align-items-center gap-2">
                <i class="ri-store-2-line fs-20 text-primary"></i>
                <span class="flex-grow-1 fw-medium"><?= esc($restaurant['name']) ?></span>
                <a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/subscription') ?>" class="btn btn-soft-primary btn-sm">Abonelik <i class="ri-arrow-right-line align-bottom"></i></a>
            </div>
        </div>

        <?php if ($pending && ! $canManage && ! $canVoid): ?>
            <div class="alert alert-light border small">
                <i class="ri-lock-2-line align-bottom me-1"></i>Bu fatura üzerinde işlem yapma yetkiniz yok.
            </div>
        <?php endif ?>

        <?php if ($pending && $canManage): ?>
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><i class="ri-percent-line text-primary align-bottom me-1"></i> İndirim</h5></div>
                <?= form_open($base . '/discount') ?>
                <div class="card-body">
                    <label class="form-label" for="discount">İndirim tutarı (KDV hariç)</label>
                    <div class="input-group">
                        <input type="text" inputmode="decimal" class="form-control<?= $invalid('discount') ?>" id="discount" name="discount" placeholder="0,00"
                               value="<?= esc((string) old('discount', number_format((float) $invoice['discount'], 2, ',', '.'), false), 'attr') ?>">
                        <span class="input-group-text"><?= esc((string) $invoice['currency_code']) ?></span>
                        <?= $feedback('discount') ?>
                    </div>
                    <div class="form-text">Mevcut indirimin yerine geçer; KDV ve toplam yeniden hesaplanır. En fazla ara toplam: <?= esc(pix_money($invoice['subtotal'], $invoice['currency_code'])) ?></div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100"><i class="ri-save-line align-bottom me-1"></i> İndirimi kaydet</button>
                </div>
                <?= form_close() ?>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><i class="ri-checkbox-circle-line text-success align-bottom me-1"></i> Ödendi işaretle</h5></div>
                <?= form_open($base . '/pay') ?>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="payment_method">Ödeme yöntemi <span class="text-danger">*</span></label>
                        <select class="form-select<?= $invalid('payment_method') ?>" id="payment_method" name="payment_method" required>
                            <?php foreach ($methods as $code => $label): ?>
                                <option value="<?= esc($code, 'attr') ?>"<?= (string) old('payment_method', 'bank_transfer', false) === $code ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= $feedback('payment_method') ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="paid_at">Ödeme tarihi <span class="text-danger">*</span></label>
                        <input type="date" class="form-control<?= $invalid('paid_at') ?>" id="paid_at" name="paid_at" required max="<?= esc($today, 'attr') ?>"
                               value="<?= esc((string) old('paid_at', $today, false), 'attr') ?>">
                        <?= $feedback('paid_at') ?>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="payment_reference">Referans / açıklama</label>
                        <input type="text" class="form-control<?= $invalid('payment_reference') ?>" id="payment_reference" name="payment_reference" maxlength="100" placeholder="ör. dekont no"
                               value="<?= esc((string) old('payment_reference', '', false), 'attr') ?>">
                        <?= $feedback('payment_reference') ?>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success w-100"><i class="ri-check-line align-bottom me-1"></i> Ödendi olarak işaretle</button>
                </div>
                <?= form_close() ?>
            </div>
        <?php endif ?>

        <?php if ($pending && $canVoid): ?>
            <div class="card border-danger-subtle">
                <div class="card-body">
                    <h6 class="mb-1">Faturayı iptal et</h6>
                    <p class="text-muted small">Fatura silinmez, "İptal" durumuna geçer ve gecikme hesabına katılmaz.</p>
                    <?= form_open($base . '/void', [
                        'data-confirm'        => (string) $invoice['number'] . ' numaralı fatura iptal edilecek. Bu işlem geri alınamaz.',
                        'data-confirm-button' => 'Evet, iptal et',
                    ]) ?>
                        <button type="submit" class="btn btn-soft-danger w-100"><i class="ri-close-circle-line align-bottom me-1"></i> İptal et</button>
                    <?= form_close() ?>
                </div>
            </div>
        <?php endif ?>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Bildirimler</h5></div>
            <div class="card-body">
                <ul class="list-unstyled vstack gap-2 mb-0 fs-13">
                    <li><i class="ri-mail-send-line align-bottom me-1 text-muted"></i> Kesim e-postası: <?= esc(pix_date($invoice['issued_notified_at'], true)) ?></li>
                    <li><i class="ri-alarm-line align-bottom me-1 text-muted"></i> Vade hatırlatması: <?= esc(pix_date($invoice['reminder_notified_at'], true)) ?></li>
                    <li><i class="ri-error-warning-line align-bottom me-1 text-muted"></i> Gecikme bildirimi: <?= esc(pix_date($invoice['overdue_notified_at'], true)) ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
