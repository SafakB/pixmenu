<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'Restoranlar') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Restoranlar']]) ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <div class="row g-2 align-items-center">
            <div class="col-sm">
                <h5 class="card-title mb-0">Restoranlar <span class="badge bg-light text-muted ms-1"><?= count($restaurants) ?></span></h5>
            </div>
            <div class="col-sm-auto d-flex flex-wrap gap-2">
                <?php if ($restaurants !== []): ?>
                    <div class="search-box">
                        <input type="search" class="form-control form-control-sm" id="restaurant-search" placeholder="Ad veya slug ara..." aria-label="Restoran ara">
                        <i class="ri-search-line search-icon"></i>
                    </div>
                <?php endif ?>
                <a href="<?= site_url('admin/restaurants/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni restoran</a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if ($restaurants === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-store-2-line"></i></div></div>
                <h5 class="mb-1">Henüz restoran yok</h5>
                <p class="text-muted mb-0">İlk restoranı eklemek için "Yeni restoran"a tıklayın.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive table-card">
                <table class="table align-middle table-nowrap table-hover mb-0" id="restaurant-table">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 70px;">ID</th>
                            <th scope="col">Ad</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Para birimi</th>
                            <th scope="col">Paket</th>
                            <th scope="col">Abonelik</th>
                            <th scope="col">Durum</th>
                            <th scope="col" class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($restaurants as $r): ?>
                        <?php $active = (int) $r['is_active'] === 1 ?>
                        <tr data-search="<?= esc(mb_strtolower($r['name'] . ' ' . $r['slug']), 'attr') ?>">
                            <td class="text-muted">#<?= esc((string) $r['id']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-xs flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-14"><i class="ri-store-2-line"></i></span>
                                    </div>
                                    <a href="<?= site_url('admin/restaurants/' . $r['id']) ?>" class="fw-medium"><?= esc($r['name']) ?></a>
                                </div>
                            </td>
                            <td class="text-muted"><?= esc($r['slug']) ?></td>
                            <td><span class="badge bg-light text-body"><?= esc($r['currency_code']) ?></span></td>
                            <td>
                                <?php if ($r['plan_name'] !== null): ?>
                                    <a href="<?= site_url('admin/restaurants/' . $r['id'] . '/subscription') ?>" class="badge fs-12 <?= esc($r['plan_badge_class'], 'attr') ?>"><?= esc($r['plan_name']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($r['subscription_status'] !== null): ?>
                                    <?= view('partials/billing/badge', ['kind' => 'subscription', 'status' => $r['subscription_status']]) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($active): ?>
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                <?php endif ?>
                                <?php if (! empty($r['suspended'])): ?>
                                    <span class="badge bg-danger" data-bs-toggle="tooltip" title="<?= esc(lang('App.subscription.reason.' . $r['suspension_reason']), 'attr') ?>">Askıda</span>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('admin/restaurants/' . $r['id']) ?>" class="btn btn-sm btn-icon btn-soft-info"
                                       data-bs-toggle="tooltip" title="Detay" aria-label="Detay"><i class="ri-eye-line"></i></a>
                                    <a href="<?= site_url('admin/restaurants/' . $r['id'] . '/subscription') ?>" class="btn btn-sm btn-icon btn-soft-secondary"
                                       data-bs-toggle="tooltip" title="Abonelik" aria-label="Abonelik"><i class="ri-vip-crown-2-line"></i></a>
                                    <a href="<?= site_url('admin/restaurants/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                       data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                    <?= form_open('admin/restaurants/' . $r['id'] . '/toggle', ['class' => 'd-inline'] + ($active ? [
                                        'data-confirm'         => $r['name'] . ' pasifleştirilecek. Kullanıcılarının oturumu kapanır, menüleri yayından kalkar.',
                                        'data-confirm-button'  => 'Evet, pasifleştir',
                                        'data-confirm-variant' => 'warning',
                                    ] : [])) ?>
                                        <?php $label = $active ? 'Pasifleştir' : 'Aktifleştir' ?>
                                        <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $active ? 'warning' : 'success' ?>"
                                                data-bs-toggle="tooltip" title="<?= $label ?>" aria-label="<?= $label ?>"><i class="ri-shut-down-line"></i></button>
                                    <?= form_close() ?>
                                    <?= form_open('admin/restaurants/' . $r['id'] . '/delete', [
                                        'class'               => 'd-inline',
                                        'data-confirm'        => $r['name'] . ' silinecek. Bu işlem geri alınamaz.',
                                        'data-confirm-button' => 'Evet, sil',
                                    ]) ?>
                                        <button type="submit" class="btn btn-sm btn-icon btn-soft-danger"
                                                data-bs-toggle="tooltip" title="Sil" aria-label="Sil"><i class="ri-delete-bin-line"></i></button>
                                    <?= form_close() ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <tr id="restaurant-no-result" class="d-none">
                        <td colspan="8" class="text-center text-muted py-4">Aramayla eşleşen restoran bulunamadı.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function () {
    var input = document.getElementById('restaurant-search');
    if (!input) return;
    var rows = document.querySelectorAll('#restaurant-table tbody tr[data-search]');
    var empty = document.getElementById('restaurant-no-result');
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase(); // PHP mb_strtolower ile aynı (locale'siz) eşleme
        var visible = 0;
        rows.forEach(function (row) {
            var match = q === '' || row.dataset.search.indexOf(q) !== -1;
            row.classList.toggle('d-none', !match);
            if (match) visible++;
        });
        empty.classList.toggle('d-none', visible !== 0);
    });
})();
</script>
<?= $this->endSection() ?>
