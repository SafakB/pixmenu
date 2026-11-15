<?= $this->extend('layouts/panel') ?>
<?php $this->setVar('title', 'Şubeler') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Şubeler']]) ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <div class="flex-grow-1">
            <h5 class="card-title mb-1">
                Şubeler
                <span class="badge bg-success-subtle text-success align-middle ms-1"><?= esc((string) count($branches)) ?></span>
            </h5>
            <p class="text-muted small mb-0">Her şubenin kendine ait menü adresi ve QR kodu vardır.</p>
        </div>
        <?php if ($isOwner): ?>
            <?php if (! empty($canAddBranch)): ?>
                <a href="<?= site_url('panel/branches/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni şube</a>
            <?php else: ?>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small">
                        <i class="ri-information-line align-bottom"></i>
                        Paketinizin şube limiti<?= $maxBranches !== null ? ' (' . esc((string) $maxBranches) . ')' : '' ?> dolu.
                        <a href="<?= site_url('panel/subscription/plans') ?>">Paketleri karşılaştırın</a>
                    </span>
                    <button type="button" class="btn btn-success btn-sm" disabled><i class="ri-add-line align-bottom me-1"></i> Yeni şube</button>
                </div>
            <?php endif ?>
        <?php endif ?>
    </div>
    <div class="card-body">
        <?php if ($branches === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-success rounded-circle fs-24"><i class="ri-map-pin-2-line"></i></div></div>
                <h5 class="mb-1">Henüz şube yok</h5>
                <p class="text-muted mb-0"><?= $isOwner ? 'İlk şubeyi eklemek için "Yeni şube"ye tıklayın.' : 'Size atanmış bir şube bulunmuyor.' ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive table-card">
                <table class="table align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Şube</th>
                            <th>Kategori</th>
                            <th>Ürün</th>
                            <th>Yetkililer</th>
                            <th>Menü</th>
                            <th>Durum</th>
                            <?php if ($isOwner): ?>
                                <th class="text-end">İşlemler</th>
                            <?php endif ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($branches as $branch): ?>
                        <?php
                        $base     = 'panel/branches/' . $branch['id'];
                        $isActive = (int) $branch['is_active'] === 1;
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-xs flex-shrink-0 me-2">
                                        <span class="avatar-title bg-light text-primary rounded fs-16"><i class="ri-map-pin-2-line"></i></span>
                                    </div>
                                    <span class="fw-medium"><?= esc($branch['name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <a href="<?= site_url($base . '/categories') ?>" class="d-inline-flex align-items-center gap-1 text-reset"
                                   data-bs-toggle="tooltip" title="Kategoriler" aria-label="Kategoriler">
                                    <i class="ri-folder-2-line text-primary fs-16"></i>
                                    <span class="fw-medium"><?= esc((string) $branch['category_count']) ?></span>
                                </a>
                            </td>
                            <td>
                                <a href="<?= site_url($base . '/products') ?>" class="d-inline-flex align-items-center gap-1 text-reset"
                                   data-bs-toggle="tooltip" title="Ürünler" aria-label="Ürünler">
                                    <i class="ri-restaurant-line text-success fs-16"></i>
                                    <span class="fw-medium"><?= esc((string) $branch['product_count']) ?></span>
                                </a>
                            </td>
                            <td>
                                <?php if ($branch['managers'] === []): ?>
                                    <span class="text-muted small">—</span>
                                <?php else: ?>
                                    <div class="avatar-group">
                                        <?php foreach ($branch['managers'] as $manager): ?>
                                            <div class="avatar-group-item material-shadow" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top" title="<?= esc($manager['name'], 'attr') ?>">
                                                <div class="avatar-xxs">
                                                    <span class="avatar-title rounded-circle bg-success-subtle text-success fs-10 fw-semibold"><?= esc($manager['initials']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach ?>
                                    </div>
                                <?php endif ?>
                            </td>
                            <td>
                                <a href="<?= esc($branch['menu_url'], 'attr') ?>" target="_blank" rel="noopener">
                                    <i class="ri-external-link-line align-bottom me-1"></i>Menüyü Göster
                                </a>
                            </td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                <?php endif ?>
                            </td>
                            <?php if ($isOwner): ?>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?= site_url($base . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                           data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                        <?= form_open($base . '/toggle', ['class' => 'd-inline'] + ($isActive ? [
                                            'data-confirm'         => $branch['name'] . ' pasifleştirilecek. Şubenin menüsü yayından kalkar.',
                                            'data-confirm-button'  => 'Evet, pasifleştir',
                                            'data-confirm-variant' => 'warning',
                                        ] : [])) ?>
                                            <?php $label = $isActive ? 'Pasifleştir' : 'Aktifleştir' ?>
                                            <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $isActive ? 'warning' : 'success' ?>"
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
                            <?php endif ?>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
