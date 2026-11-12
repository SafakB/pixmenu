<?php
/**
 * Güncel abonelik özeti kartı (admin + panel).
 * Parametreler: summary (SubscriptionService::summary), suspended (bool), suspension_reason (string|null),
 *               actions (string: kart başlığına basılacak hazır HTML; boş olabilir)
 */
$sub   = $summary['subscription'];
$plan  = $summary['plan'];
$usage = $summary['usage'];
?>
<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <h5 class="card-title mb-0 flex-grow-1"><i class="ri-vip-crown-2-line text-primary align-bottom me-1"></i> Güncel abonelik</h5>
        <?= $actions ?>
    </div>
    <div class="card-body">
        <?php if ($suspended): ?>
            <div class="alert alert-danger mb-3">
                <i class="ri-forbid-2-line align-bottom me-1"></i>
                Restoran askıda: <strong><?= esc(lang('App.subscription.reason.' . $suspension_reason)) ?></strong>. Menü yayında değil.
            </div>
        <?php endif ?>

        <?php if ($sub === null): ?>
            <div class="text-center text-muted py-4">
                <i class="ri-vip-crown-2-line fs-1 d-block mb-2"></i>
                Güncel abonelik yok.
            </div>
        <?php else: ?>
            <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                <div class="avatar-sm flex-shrink-0">
                    <div class="avatar-title bg-primary-subtle text-primary rounded fs-20"><i class="ri-vip-crown-2-line"></i></div>
                </div>
                <div class="flex-grow-1">
                    <h4 class="mb-1"><?= esc($sub['plan_name']) ?></h4>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?= view('partials/billing/badge', ['kind' => 'subscription', 'status' => $sub['status']]) ?>
                        <span class="badge bg-light text-body"><?= esc($sub['cycle_label']) ?></span>
                        <?php if ((int) $sub['cancel_at_period_end'] === 1): ?>
                            <span class="badge bg-warning-subtle text-warning"><i class="ri-calendar-close-line align-bottom"></i> Dönem sonunda sona erecek</span>
                        <?php endif ?>
                    </div>
                </div>
                <?php if ($sub['billing_cycle'] !== 'trial'): ?>
                    <div class="text-end">
                        <h4 class="mb-0"><?= esc(pix_money($sub['price'], $sub['currency_code'])) ?></h4>
                        <small class="text-muted">/ <?= $sub['billing_cycle'] === 'yearly' ? 'yıl' : 'ay' ?> · KDV hariç</small>
                    </div>
                <?php endif ?>
            </div>

            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <p class="text-muted text-uppercase fw-semibold fs-12 mb-1"><?= $sub['billing_cycle'] === 'trial' ? 'Deneme dönemi' : 'Dönem' ?></p>
                    <h6 class="mb-0"><?= esc(pix_date($sub['current_period_start'])) ?> – <?= esc(pix_date($sub['current_period_end'])) ?></h6>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <p class="text-muted text-uppercase fw-semibold fs-12 mb-1"><?= $sub['billing_cycle'] === 'trial' ? 'Kalan süre' : 'Dönem sonuna' ?></p>
                    <h6 class="mb-0">
                        <?php if ($summary['days_left'] < 0): ?>
                            <span class="text-danger">Süresi doldu</span>
                        <?php else: ?>
                            <?= esc((string) $summary['days_left']) ?> gün
                        <?php endif ?>
                    </h6>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <p class="text-muted text-uppercase fw-semibold fs-12 mb-1">Şube kullanımı</p>
                    <h6 class="mb-0">
                        <?= esc((string) $usage['branches']) ?> / <?= $usage['max_branches'] === null ? 'Sınırsız' : esc((string) $usage['max_branches']) ?>
                    </h6>
                    <?php if ($usage['max_branches'] !== null && $usage['max_branches'] > 0): ?>
                        <?php $pct = min(100, (int) round($usage['branches'] / $usage['max_branches'] * 100)) ?>
                        <div class="progress progress-sm mt-2">
                            <div class="progress-bar bg-<?= $pct >= 100 ? 'warning' : 'success' ?>" role="progressbar" style="width: <?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    <?php endif ?>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <p class="text-muted text-uppercase fw-semibold fs-12 mb-1">Paket hakları</p>
                    <div class="d-flex flex-column gap-1 fs-13">
                        <span><i class="<?= ! empty($plan['allow_custom_domain']) ? 'ri-checkbox-circle-fill text-success' : 'ri-close-circle-fill text-danger' ?> align-bottom me-1"></i>Özel alan adı</span>
                        <span><i class="<?= ! empty($plan['allow_custom_design']) ? 'ri-checkbox-circle-fill text-success' : 'ri-close-circle-fill text-danger' ?> align-bottom me-1"></i>Özel menü tasarımı</span>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
