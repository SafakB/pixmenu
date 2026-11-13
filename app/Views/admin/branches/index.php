<?= $this->extend('layouts/admin') ?>
<?php
$restaurantUrl = site_url('admin/restaurants/' . $restaurant['id']);
$branchBase    = 'admin/restaurants/' . $restaurant['id'] . '/branches';
?>
<?php $this->setVar('title', 'Şubeler') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')],
    ['label' => $restaurant['name'], 'url' => $restaurantUrl],
    ['label' => 'Şubeler'],
]) ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <div class="flex-grow-1">
            <h5 class="card-title mb-1">
                <?= esc($restaurant['name']) ?> şubeleri
                <span class="badge bg-success-subtle text-success align-middle ms-1"><?= esc((string) count($branches)) ?></span>
            </h5>
            <p class="text-muted small mb-0">Her şubenin kendine ait menü adresi ve QR kodu vardır.</p>
        </div>
        <a href="<?= $restaurantUrl ?>" class="btn btn-light btn-sm"><i class="ri-arrow-left-line align-bottom me-1"></i> Restorana dön</a>
        <a href="<?= site_url($branchBase . '/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni şube</a>
    </div>
    <div class="card-body">
        <?php if ($branches === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-success rounded-circle fs-24"><i class="ri-map-pin-2-line"></i></div></div>
                <h5 class="mb-1">Henüz şube yok</h5>
                <p class="text-muted mb-0">İlk şubeyi eklemek için "Yeni şube"ye tıklayın.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive table-card">
                <table class="table align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px">Sıra</th>
                            <th>Şube</th>
                            <th>Menü adresi</th>
                            <th>Şablon</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($branches as $branch): ?>
                        <?php $base = $branchBase . '/' . $branch['id']; ?>
                        <tr>
                            <td class="text-muted"><?= esc((string) $branch['sort_order']) ?></td>
                            <td>
                                <a href="<?= site_url($base . '/edit') ?>" class="fw-medium text-reset"><?= esc($branch['name']) ?></a>
                                <div class="text-muted small"><?= esc($branch['slug']) ?></div>
                            </td>
                            <td>
                                <a href="<?= esc($branch['menu_url'], 'attr') ?>" target="_blank" rel="noopener">
                                    <span class="font-monospace"><?= esc($branch['public_code']) ?></span>
                                    <i class="ri-external-link-line align-bottom ms-1"></i>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info"><i class="ri-layout-masonry-line align-bottom me-1"></i><?= esc($branch['template_name']) ?></span>
                            </td>
                            <td>
                                <?php if ($branch['is_active']): ?>
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url($base . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                       data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                    <?= form_open($base . '/regenerate-code', [
                                        'class'                => 'd-inline',
                                        'data-confirm'         => $branch['name'] . ' için yeni menü kodu oluşturulacak. Basılı eski QR kodları artık çalışmaz.',
                                        'data-confirm-title'   => 'QR kodu yenilensin mi?',
                                        'data-confirm-button'  => 'Evet, yenile',
                                        'data-confirm-variant' => 'warning',
                                    ]) ?>
                                        <button type="submit" class="btn btn-sm btn-icon btn-soft-secondary"
                                                data-bs-toggle="tooltip" title="Menü kodunu yenile" aria-label="Menü kodunu yenile"><i class="ri-qr-code-line"></i></button>
                                    <?= form_close() ?>
                                    <?= form_open($base . '/toggle', ['class' => 'd-inline'] + ($branch['is_active'] ? [
                                        'data-confirm'         => $branch['name'] . ' pasifleştirilecek. Şubenin menüsü yayından kalkar.',
                                        'data-confirm-button'  => 'Evet, pasifleştir',
                                        'data-confirm-variant' => 'warning',
                                    ] : [])) ?>
                                        <?php $label = $branch['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?>
                                        <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $branch['is_active'] ? 'warning' : 'success' ?>"
                                                data-bs-toggle="tooltip" title="<?= $label ?>" aria-label="<?= $label ?>"><i class="ri-shut-down-line"></i></button>
                                    <?= form_close() ?>
                                    <?= form_open($base . '/delete', [
                                        'class'               => 'd-inline',
                                        'data-confirm'        => $branch['name'] . ' silinecek. Bu işlem geri alınamaz.',
                                        'data-confirm-button' => 'Evet, sil',
                                    ]) ?>
                                        <button type="submit" class="btn btn-sm btn-icon btn-soft-danger"
                                                data-bs-toggle="tooltip" title="Sil" aria-label="Sil"><i class="ri-delete-bin-line"></i></button>
                                    <?= form_close() ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
