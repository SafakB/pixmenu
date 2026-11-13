<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'Gösterge Paneli') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Gösterge Paneli']]) ?>
<?= $this->section('content') ?>
<?php
$cards = [
    'restaurants' => ['label' => 'Restoranlar', 'icon' => 'ri-store-2-line', 'color' => 'primary', 'url' => admin_can('restaurants.view') ? site_url('admin/restaurants') : null],
    'branches'    => ['label' => 'Şubeler', 'icon' => 'ri-map-pin-2-line', 'color' => 'success', 'url' => null],
    'users'       => ['label' => 'Kullanıcılar', 'icon' => 'ri-team-line', 'color' => 'info', 'url' => null],
    'languages'   => ['label' => 'Diller', 'icon' => 'ri-translate-2', 'color' => 'warning', 'url' => admin_can('definitions.manage') ? site_url('admin/languages') : null],
    'allergens'   => ['label' => 'Alerjenler', 'icon' => 'ri-error-warning-line', 'color' => 'danger', 'url' => admin_can('definitions.manage') ? site_url('admin/allergens') : null],
];
$canBilling = admin_can('billing.view');
?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-5">
    <?php foreach ($cards as $key => $card): ?>
        <?php $count = (int) ($stats[$key] ?? 0) ?>
        <div class="col">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="fw-medium text-muted mb-0"><?= esc($card['label']) ?></p>
                            <h2 class="mt-4 ff-secondary fw-semibold">
                                <span class="counter-value" data-target="<?= esc((string) $count, 'attr') ?>"><?= esc((string) $count) ?></span>
                            </h2>
                            <?php if ($card['url'] !== null): ?>
                                <a href="<?= esc($card['url'], 'attr') ?>" class="text-decoration-underline">Tümünü gör</a>
                            <?php else: ?>
                                <p class="mb-0 text-muted">Toplam kayıt</p>
                            <?php endif ?>
                        </div>
                        <div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-<?= esc($card['color'], 'attr') ?>-subtle rounded-circle fs-2">
                                    <i class="<?= esc($card['icon'], 'attr') ?> text-<?= esc($card['color'], 'attr') ?>"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?php /* ---------- Abonelik (billing.view) ---------- */ ?>
<?php if ($canBilling): ?>
<div class="row">
    <div class="col-xl-6">
        <div class="card card-height-100">
            <div class="card-header d-flex align-items-center">
                <h5 class="card-title mb-0 flex-grow-1">
                    <i class="ri-error-warning-line text-danger align-bottom me-1"></i> Geciken faturalar
                    <span class="badge bg-danger-subtle text-danger align-middle ms-1"><?= esc((string) $billing['overdue_count']) ?></span>
                </h5>
                <a href="<?= site_url('admin/invoices?overdue=1') ?>" class="btn btn-soft-primary btn-sm">Tümü <i class="ri-arrow-right-line align-bottom"></i></a>
            </div>
            <div class="card-body">
                <?php if ($billing['overdue'] === []): ?>
                    <div class="text-center text-muted py-4"><i class="ri-checkbox-circle-line fs-1 d-block mb-2 text-success"></i>Geciken fatura yok.</div>
                <?php else: ?>
                    <p class="text-muted mb-3">
                        Toplam açık tutar:
                        <?php foreach ($billing['overdue_sum'] as $currency => $sum): ?>
                            <span class="fw-semibold text-body ms-1"><?= esc(pix_money($sum, (string) $currency)) ?></span>
                        <?php endforeach ?>
                    </p>
                    <div class="table-responsive table-card">
                        <table class="table align-middle table-nowrap mb-0">
                            <thead class="table-light"><tr><th>Fatura</th><th>Restoran</th><th>Tutar</th><th>Gecikme</th></tr></thead>
                            <tbody>
                            <?php foreach ($billing['overdue'] as $i): ?>
                                <tr>
                                    <td><a href="<?= site_url('admin/invoices/' . $i['id']) ?>" class="fw-medium"><?= esc((string) $i['number']) ?></a></td>
                                    <td><a href="<?= site_url('admin/restaurants/' . $i['restaurant_id'] . '/subscription') ?>" class="text-reset"><?= esc($i['restaurant_name']) ?></a></td>
                                    <td><?= esc(pix_money($i['total'], $i['currency_code'])) ?></td>
                                    <td><span class="badge bg-danger-subtle text-danger"><?= esc((string) $i['days_late']) ?> gün</span></td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-height-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="ri-time-line text-info align-bottom me-1"></i> Bu hafta biten denemeler
                    <span class="badge bg-info-subtle text-info align-middle ms-1"><?= esc((string) count($billing['trials'])) ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($billing['trials'] === []): ?>
                    <p class="text-muted text-center py-4 mb-0">Bu hafta biten deneme yok.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($billing['trials'] as $t): ?>
                            <li class="list-group-item d-flex align-items-center px-0">
                                <a href="<?= site_url('admin/restaurants/' . $t['restaurant_id'] . '/subscription') ?>" class="flex-grow-1 text-reset fw-medium"><?= esc($t['restaurant_name']) ?></a>
                                <span class="badge bg-info-subtle text-info"><?= $t['days_left'] === 0 ? 'Bugün' : esc((string) $t['days_left']) . ' gün' ?></span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-height-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="ri-forbid-2-line text-danger align-bottom me-1"></i> Askıdaki restoranlar
                    <span class="badge bg-danger-subtle text-danger align-middle ms-1"><?= esc((string) count($billing['suspended'])) ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($billing['suspended'] === []): ?>
                    <p class="text-muted text-center py-4 mb-0">Askıda restoran yok.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($billing['suspended'] as $s): ?>
                            <li class="list-group-item px-0">
                                <a href="<?= site_url('admin/restaurants/' . $s['id'] . '/subscription') ?>" class="text-reset fw-medium d-block"><?= esc($s['name']) ?></a>
                                <?= view('partials/billing/badge', ['kind' => 'reason', 'status' => (string) $s['suspension_reason']]) ?>
                                <small class="text-muted ms-1"><?= esc(pix_date($s['suspended_at'])) ?></small>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>
