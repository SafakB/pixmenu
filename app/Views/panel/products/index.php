<?= $this->extend('layouts/panel') ?>
<?php
$base        = 'panel/branches/' . $branch['id'];
$indexUrl    = site_url($base . '/products');
$isFiltered  = $filters['category'] !== null || $filters['q'] !== '';
// Ürünler kategori içinde sıralanır; sürükle-bırak yalnızca tek kategori listelenirken anlamlıdır.
$canReorder  = $filters['category'] !== null && $filters['q'] === '' && count($products) > 1;
?>
<?php $this->setVar('title', 'Ürünler') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Şubeler', 'url' => site_url('panel/branches')],
    ['label' => $branch['name']],
    ['label' => 'Ürünler'],
]) ?>
<?= $this->section('css') ?>
<style>
    #sortable-rows .drag-handle { cursor: grab; }
    #sortable-rows .sortable-ghost { opacity: .4; }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <div class="flex-grow-1">
            <h5 class="card-title mb-1">
                <?= esc($branch['name']) ?> ürünleri
                <span class="badge bg-success-subtle text-success align-middle ms-1"><?= esc((string) count($products)) ?></span>
            </h5>
            <p class="text-muted small mb-0">
                <?php if ($canReorder): ?>
                    Menüdeki sırayı değiştirmek için satırları tutamaktan sürükleyin.
                <?php else: ?>
                    Sıralamak için bir kategori seçin.
                <?php endif ?>
            </p>
        </div>
        <a href="<?= site_url($base . '/categories') ?>" class="btn btn-light btn-sm"><i class="ri-folder-2-line align-bottom me-1"></i> Kategoriler</a>
        <a href="<?= site_url($base . '/products/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni ürün</a>
    </div>
    <div class="card-body border-bottom border-bottom-dashed">
        <form method="get" action="<?= $indexUrl ?>">
            <div class="row g-2">
                <div class="col-sm-5 col-lg-4">
                    <select class="form-select" name="category" aria-label="Kategori">
                        <option value="">Tüm kategoriler</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= esc((string) $category['id'], 'attr') ?>"<?= $filters['category'] === (int) $category['id'] ? ' selected' : '' ?>><?= esc($category['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-sm-7 col-lg-5">
                    <div class="search-box">
                        <input type="search" class="form-control search" name="q" value="<?= esc($filters['q'], 'attr') ?>" maxlength="100" placeholder="Ürün adı ara…" aria-label="Ara">
                        <i class="ri-search-line search-icon"></i>
                    </div>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="ri-equalizer-fill align-bottom me-1"></i> Filtrele</button>
                    <?php if ($isFiltered): ?>
                        <a href="<?= $indexUrl ?>" class="btn btn-light" data-bs-toggle="tooltip" title="Filtreyi temizle" aria-label="Filtreyi temizle"><i class="ri-close-line"></i></a>
                    <?php endif ?>
                </div>
            </div>
        </form>
    </div>
    <div class="card-body">
        <?php if ($products === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-success rounded-circle fs-24"><i class="<?= $isFiltered ? 'ri-search-line' : 'ri-restaurant-line' ?>"></i></div></div>
                <?php if ($isFiltered): ?>
                    <h5 class="mb-1">Sonuç bulunamadı</h5>
                    <p class="text-muted mb-0">Filtreye uyan ürün yok. <a href="<?= $indexUrl ?>">Filtreyi temizleyin</a>.</p>
                <?php else: ?>
                    <h5 class="mb-1">Henüz ürün yok</h5>
                    <p class="text-muted mb-0">İlk ürünü eklemek için "Yeni ürün"e tıklayın.</p>
                <?php endif ?>
            </div>
        <?php else: ?>
            <div class="table-responsive table-card">
                <table class="table align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <?php if ($canReorder): ?><th style="width:40px"></th><?php endif ?>
                            <th>Ürün</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody<?= $canReorder ? ' id="sortable-rows"' : '' ?>>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $rowBase  = $base . '/products/' . $product['id'];
                        $isActive = (int) $product['is_active'] === 1;
                        ?>
                        <tr data-id="<?= esc((string) $product['id'], 'attr') ?>">
                            <?php if ($canReorder): ?>
                                <td>
                                    <span class="drag-handle text-muted fs-18" data-bs-toggle="tooltip" title="Sürükleyerek sırala" aria-label="Sürükleyerek sırala"><i class="ri-drag-move-2-line"></i></span>
                                </td>
                            <?php endif ?>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0 me-3">
                                        <?php if ($product['thumb_url'] !== null): ?>
                                            <img src="<?= esc($product['thumb_url'], 'attr') ?>" alt="" class="img-fluid rounded avatar-sm object-fit-cover">
                                        <?php else: ?>
                                            <div class="avatar-title bg-light text-muted rounded fs-20"><i class="ri-image-line"></i></div>
                                        <?php endif ?>
                                    </div>
                                    <a href="<?= site_url($rowBase . '/edit') ?>" class="fw-medium text-body"><?= esc($product['name']) ?></a>
                                </div>
                            </td>
                            <td>
                                <a href="<?= site_url($base . '/products?category=' . $product['category_id']) ?>" class="text-muted"><?= esc($product['category_name']) ?></a>
                            </td>
                            <td class="fw-medium"><?= esc($product['price']) ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url($rowBase . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                       data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                    <?= form_open($rowBase . '/toggle', ['class' => 'd-inline']) ?>
                                        <?php $label = $isActive ? 'Pasifleştir' : 'Aktifleştir' ?>
                                        <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $isActive ? 'warning' : 'success' ?>"
                                                data-bs-toggle="tooltip" title="<?= $label ?>" aria-label="<?= $label ?>"><i class="ri-shut-down-line"></i></button>
                                    <?= form_close() ?>
                                    <?= form_open($rowBase . '/delete', [
                                        'class'               => 'd-inline',
                                        'data-confirm'        => $product['name'] . ' silinecek. Bu işlem geri alınamaz.',
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

<?php if ($canReorder): ?>
<?= $this->section('js') ?>
<script src="<?= asset_url('libs/sortablejs/Sortable.min.js') ?>"></script>
<script>
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var list = document.getElementById('sortable-rows');
    if (!list || !meta) return;
    Sortable.create(list, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) return;
            var ids = Array.from(list.querySelectorAll('[data-id]')).map(function (el) { return +el.dataset.id; });
            fetch(<?= json_encode(site_url($base . '/products/reorder')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta.content },
                body: JSON.stringify({ ids: ids })
            }).then(function (res) {
                var t = res.headers.get('X-CSRF-TOKEN'); if (t) meta.content = t;
                if (!res.ok) location.reload();
            }).catch(function () { location.reload(); });
        }
    });
})();
</script>
<?= $this->endSection() ?>
<?php endif ?>
