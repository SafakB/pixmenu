<?= $this->extend('layouts/panel') ?>
<?php $this->setVar('title', 'Ödeme') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Abonelik', 'url' => site_url('panel/subscription')],
    ['label' => 'Ödeme'],
]) ?>
<?php
$currency = (string) $invoice['currency_code'];
$discount = (float) $invoice['discount'];
?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <div class="avatar-xs flex-shrink-0 me-2">
                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-16"><i class="ri-bank-card-line"></i></span>
                </div>
                <h5 class="card-title mb-0 flex-grow-1">Kart ile ödeme</h5>
                <span class="badge bg-success-subtle text-success"><i class="ri-lock-2-line align-bottom me-1"></i>Güvenli ödeme</span>
            </div>
            <div class="card-body">
                <div class="alert alert-info alert-label-icon label-arrow mb-4" role="note">
                    <i class="ri-information-line label-icon"></i>
                    Online kart ile ödeme çok yakında aktif olacak. Kart bilgileriniz bu ekrandan sunucularımıza gönderilmez ve saklanmaz.
                </div>

                <div class="card-wrapper mb-4" aria-hidden="true"></div>

                <?php /* TODO(PayTR): Direct API — action PayTR ödeme adresi olacak; kart alanlarına PayTR'nin beklediği
                         `name`'ler ve sunucuda üretilen gizli alanlar (merchant_id, merchant_oid, paytr_token, payment_amount…)
                         eklenecek. O zamana kadar kart alanlarında `name` YOKTUR: değerleri hiçbir yere post edilmez. */ ?>
                <?= form_open('panel/subscription/invoices/' . (int) $invoice['id'] . '/pay', ['id' => 'checkout-form', 'autocomplete' => 'off']) ?>
                    <div class="mb-3">
                        <label for="card-number" class="form-label">Kart numarası</label>
                        <input type="tel" class="form-control" id="card-number" inputmode="numeric" autocomplete="off" placeholder="0000 0000 0000 0000" required>
                    </div>
                    <div class="mb-3">
                        <label for="card-name" class="form-label">Kart üzerindeki ad</label>
                        <input type="text" class="form-control" id="card-name" autocomplete="off" placeholder="<?= esc($ownerName, 'attr') ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label for="card-expiry" class="form-label">Son kullanma</label>
                                <input type="tel" class="form-control" id="card-expiry" inputmode="numeric" autocomplete="off" placeholder="AA/YY" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label for="card-cvc" class="form-label">Güvenlik kodu (CVC)</label>
                                <input type="tel" class="form-control" id="card-cvc" inputmode="numeric" autocomplete="off" placeholder="•••" maxlength="4" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 btn-label">
                        <i class="ri-secure-payment-line label-icon align-middle fs-16 me-2"></i>
                        <?= esc(pix_money($invoice['total'], $currency)) ?> öde
                    </button>
                    <p class="text-muted text-center fs-12 mt-3 mb-0">
                        <i class="ri-shield-check-line align-bottom me-1"></i>Ödemeler 3D Secure ile korunacaktır.
                    </p>
                <?= form_close() ?>
            </div>
        </div>

        <?php if ($paymentInstructions !== ''): ?>
            <div class="card">
                <div class="card-body">
                    <h6 class="fs-14 mb-2"><i class="ri-bank-line align-bottom text-info me-1"></i> Havale / EFT ile ödeme</h6>
                    <p class="text-muted mb-0"><?= nl2br(esc($paymentInstructions)) ?></p>
                    <p class="text-muted mb-0 mt-2">Açıklamaya fatura numaranızı (<b><?= esc((string) $invoice['number']) ?></b>) yazmayı unutmayın.</p>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-xl-5">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="card-title mb-0 flex-grow-1">Sipariş özeti</h5>
                <?= view('partials/billing/badge', ['kind' => 'invoice', 'status' => $invoice['display_status']]) ?>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <div class="avatar-sm flex-shrink-0 me-3">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-20"><i class="ri-vip-crown-2-line"></i></span>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1"><?= esc($planName) ?> paketi<?= $cycleLabel !== null ? ' · ' . esc($cycleLabel) : '' ?></h6>
                        <p class="text-muted mb-0"><?= esc($restaurantName) ?></p>
                    </div>
                </div>
                <div class="table-responsive table-card">
                    <table class="table table-borderless align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Fatura no</td>
                                <td class="text-end fw-medium"><?= esc((string) $invoice['number']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Dönem</td>
                                <td class="text-end"><?= esc(pix_date($invoice['period_start'])) ?> – <?= esc(pix_date($invoice['period_end'])) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Son ödeme tarihi</td>
                                <td class="text-end"><?= esc(pix_date($invoice['due_date'])) ?></td>
                            </tr>
                            <tr class="border-top border-top-dashed">
                                <td>Ara toplam</td>
                                <td class="text-end"><?= esc(pix_money($invoice['subtotal'], $currency)) ?></td>
                            </tr>
                            <?php if ($discount > 0): ?>
                                <tr>
                                    <td>İndirim</td>
                                    <td class="text-end text-success">− <?= esc(pix_money($invoice['discount'], $currency)) ?></td>
                                </tr>
                            <?php endif ?>
                            <tr>
                                <td>KDV (%<?= esc(pix_money($invoice['tax_rate'])) ?>)</td>
                                <td class="text-end"><?= esc(pix_money($invoice['tax_amount'], $currency)) ?></td>
                            </tr>
                            <tr class="table-active">
                                <th>Toplam</th>
                                <td class="text-end"><span class="fw-semibold fs-16"><?= esc(pix_money($invoice['total'], $currency)) ?></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="text-center">
            <a href="<?= site_url('panel/subscription/invoices/' . (int) $invoice['id']) ?>" class="btn btn-link text-muted">
                <i class="ri-file-list-3-line align-bottom me-1"></i> Faturayı görüntüle
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script src="<?= asset_url('libs/card/card.js') ?>"></script>
<script src="<?= asset_url('js/pages/checkout.init.js') ?>"></script>
<?= $this->endSection() ?>
