<?= $this->extend('layouts/panel') ?>
<?php
$totalBranches  = count($branches);
$activeBranches = count(array_filter($branches, static fn ($b) => (int) $b['is_active'] === 1));
?>
<?php $this->setVar('title', (string) $restaurantName) ?>
<?php $this->setVar('breadcrumbs', []) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-6 col-xl-4">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Şubeler</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold mb-4"><?= esc((string) $totalBranches) ?></h4>
                        <a href="<?= site_url('panel/branches') ?>" class="text-decoration-underline">Tüm şubeler</a>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-store-2-line text-primary"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Yayındaki menüler</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold mb-4"><?= esc((string) $activeBranches) ?></h4>
                        <span class="text-muted"><?= esc((string) ($totalBranches - $activeBranches)) ?> pasif şube</span>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-qr-code-line text-success"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex align-items-center mb-3">
    <h5 class="mb-0 flex-grow-1">Şubeleriniz</h5>
</div>

<?php if ($branches === []): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-store-2-line"></i></div></div>
            <h5 class="mb-1">Henüz şube yok</h5>
            <p class="text-muted mb-0">Yönetebileceğiniz bir şube bulunmuyor.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($branches as $branch): ?>
            <?php
            $branchBase = 'panel/branches/' . $branch['id'];
            $isActive   = (int) $branch['is_active'] === 1;
            ?>
            <div class="col-md-6 col-xxl-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-sm flex-shrink-0 me-3">
                                <span class="avatar-title bg-light text-primary rounded fs-3"><i class="ri-map-pin-2-line"></i></span>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <h5 class="fs-15 mb-1 text-truncate"><?= esc($branch['name']) ?></h5>
                                <span class="text-muted font-monospace small"><?= esc($branch['public_code']) ?></span>
                            </div>
                            <?php if ($isActive): ?>
                                <span class="badge bg-success-subtle text-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger">Pasif</span>
                            <?php endif ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?= site_url($branchBase . '/categories') ?>" class="btn btn-soft-primary btn-sm"><i class="ri-folder-2-line align-bottom me-1"></i> Kategoriler</a>
                            <a href="<?= site_url($branchBase . '/products') ?>" class="btn btn-soft-primary btn-sm"><i class="ri-restaurant-line align-bottom me-1"></i> Ürünler</a>
                            <a href="<?= site_url('m/' . $branch['public_code']) ?>" class="btn btn-soft-secondary btn-sm ms-auto" target="_blank" rel="noopener"><i class="ri-external-link-line align-bottom me-1"></i> Menü</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
