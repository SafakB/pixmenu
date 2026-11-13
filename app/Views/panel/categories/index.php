<?= $this->extend('layouts/panel') ?>
<?php $base = 'panel/branches/' . $branch['id']; ?>
<?php $this->setVar('title', 'Kategoriler') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Şubeler', 'url' => site_url('panel/branches')],
    ['label' => $branch['name']],
    ['label' => 'Kategoriler'],
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
                <?= esc($branch['name']) ?> kategorileri
                <span class="badge bg-success-subtle text-success align-middle ms-1"><?= esc((string) count($categories)) ?></span>
            </h5>
            <p class="text-muted small mb-0">Menüdeki sırayı değiştirmek için satırları tutamaktan sürükleyin.</p>
        </div>
        <a href="<?= site_url($base . '/products') ?>" class="btn btn-light btn-sm"><i class="ri-restaurant-line align-bottom me-1"></i> Ürünler</a>
        <a href="<?= site_url($base . '/categories/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni kategori</a>
    </div>
    <div class="card-body">
        <?php if ($categories === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-success rounded-circle fs-24"><i class="ri-folder-2-line"></i></div></div>
                <h5 class="mb-1">Henüz kategori yok</h5>
                <p class="text-muted mb-0">İlk kategoriyi eklemek için "Yeni kategori"ye tıklayın.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive table-card">
                <table class="table align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px"></th>
                            <th>Kategori</th>
                            <th>Ürün</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="sortable-rows">
                    <?php foreach ($categories as $category): ?>
                        <?php
                        $rowBase  = $base . '/categories/' . $category['id'];
                        $isActive = (int) $category['is_active'] === 1;
                        ?>
                        <tr data-id="<?= esc((string) $category['id'], 'attr') ?>">
                            <td>
                                <span class="drag-handle text-muted fs-18" data-bs-toggle="tooltip" title="Sürükleyerek sırala" aria-label="Sürükleyerek sırala"><i class="ri-drag-move-2-line"></i></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0 me-3">
                                        <?php if ($category['image_url'] !== null): ?>
                                            <img src="<?= esc($category['image_url'], 'attr') ?>" alt="" class="img-fluid rounded avatar-sm object-fit-cover">
                                        <?php else: ?>
                                            <div class="avatar-title bg-light text-muted rounded fs-20"><i class="ri-image-line"></i></div>
                                        <?php endif ?>
                                    </div>
                                    <a href="<?= site_url($rowBase . '/edit') ?>" class="fw-medium text-body"><?= esc($category['name']) ?></a>
                                </div>
                            </td>
                            <td>
                                <a href="<?= site_url($base . '/products?category=' . $category['id']) ?>" class="badge bg-primary-subtle text-primary fs-12"
                                   data-bs-toggle="tooltip" title="Ürünleri göster"><?= esc((string) $category['product_count']) ?> ürün</a>
                            </td>
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
                                        'data-confirm'        => $category['name'] . ' silinecek. Bu işlem geri alınamaz.',
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
            fetch(<?= json_encode(site_url($base . '/categories/reorder')) ?>, {
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
