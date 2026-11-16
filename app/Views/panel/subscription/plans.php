<?= $this->extend('layouts/panel') ?>
<?php $this->setVar('title', 'Paketler') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Abonelik', 'url' => site_url('panel/subscription')],
    ['label' => 'Paketler'],
]) ?>
<?php
$startYearly = $currentCycle === 'yearly';
$colClass    = count($plans) >= 4 ? 'col-xxl-3 col-lg-6' : 'col-xl-4 col-lg-6';
?>
<?= $this->section('content') ?>
<div class="row justify-content-center mt-2">
    <div class="col-lg-6">
        <div class="text-center mb-4">
            <h4 class="fw-semibold fs-22">Paketler ve fiyatlar</h4>
            <p class="text-muted mb-4 fs-15">
                Fiyatlara KDV dahil değildir (%<?= esc(pix_money($taxRate)) ?>).
                <?= $canSelfUpgrade ? 'Deneme paketinizden ücretli bir pakete hemen geçebilirsiniz.' : 'Paket değişikliği için bizimle iletişime geçin.' ?>
            </p>
            <div class="d-inline-flex">
                <ul class="nav nav-pills arrow-navtabs plan-nav rounded mb-3 p-1" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold<?= $startYearly ? '' : ' active' ?>" type="button" data-cycle="monthly" aria-selected="<?= $startYearly ? 'false' : 'true' ?>">Aylık</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold<?= $startYearly ? ' active' : '' ?>" type="button" data-cycle="yearly" aria-selected="<?= $startYearly ? 'true' : 'false' ?>">Yıllık</button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($plans === []): ?>
    <div class="card"><div class="card-body text-center text-muted py-5">Şu an listelenecek paket yok.</div></div>
<?php else: ?>
<div class="row justify-content-center">
    <?php foreach ($plans as $p): ?>
        <?php
        $isCurrent = $currentPlanId === (int) $p['id'];

        if ($isCurrent) {
            $footer = '<button type="button" class="btn btn-primary disabled w-100">Mevcut paketiniz</button>';
        } elseif ($canSelfUpgrade && $p['trial_days'] === null) {
            $footer = form_open('panel/subscription/upgrade', [
                'data-confirm'         => 'Paketiniz hemen ' . $p['name'] . ' olarak değişir; kalan deneme günleriniz düşer ve ilk dönem faturası kesilir. Ardından ödeme ekranına yönlendirileceksiniz.',
                'data-confirm-title'   => $p['name'] . ' paketine geçilsin mi?',
                'data-confirm-button'  => 'Evet, geç',
                'data-confirm-variant' => 'primary',
            ])
                . '<input type="hidden" name="plan_id" value="' . esc((string) $p['id'], 'attr') . '">'
                . '<input type="hidden" name="billing_cycle" value="' . ($startYearly ? 'yearly' : 'monthly') . '" data-cycle-input>'
                . '<button type="submit" class="btn btn-' . ((int) $p['is_featured'] === 1 ? 'primary' : 'soft-primary') . ' w-100">'
                . '<i class="ri-arrow-up-circle-line align-bottom me-1"></i> Bu pakete geç</button>'
                . form_close();
        } elseif ($p['trial_days'] !== null) {
            $footer = '';
        } else {
            $footer = '<button type="button" class="btn btn-soft-secondary disabled w-100">Geçiş için bizimle iletişime geçin</button>';
        }
        ?>
        <div class="<?= $colClass ?> mb-4">
            <?= view('partials/billing/plan_card', [
                'plan'        => $p,
                'startYearly' => $startYearly,
                'isCurrent'   => $isCurrent,
                'footer'      => $footer,
            ]) ?>
        </div>
    <?php endforeach ?>
</div>
<p class="text-center text-muted">
    <i class="ri-customer-service-2-line align-bottom me-1"></i>
    <?php if ($canSelfUpgrade): ?>
        Geçiş anında yeni dönem bugünden başlar ve ilk dönem faturası kesilir. Ücretli paketler arası değişiklik için bizimle iletişime geçin.
    <?php else: ?>
        Paketinizi yükseltmek veya değiştirmek için bizimle iletişime geçin; geçiş aynı gün yapılır ve yeni dönem bugünden başlar.
    <?php endif ?>
</p>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script src="<?= asset_url('js/pix-plan-cycle.js') ?>"></script>
<?= $this->endSection() ?>
