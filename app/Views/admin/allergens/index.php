<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'Alerjenler') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Alerjenler']]) ?>
<?= $this->section('css') ?>
<style>
    .allergen-card .drag-handle { cursor: grab; }
    .allergen-card.sortable-ghost { opacity: .4; }
    .allergen-card.sortable-chosen .card { box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15); }
    .allergen-card .avatar-title img { width: 60%; height: 60%; object-fit: contain; }
    .allergen-card .card.is-passive .avatar-title { filter: grayscale(1); opacity: .6; }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <p class="text-muted mb-0 flex-grow-1">
        <?php if ($allergens !== []): ?>
            <i class="ri-drag-move-2-line align-bottom me-1"></i>Sırayı değiştirmek için kartları tutup sürükleyin.
        <?php endif ?>
    </p>
    <a href="<?= site_url('admin/allergens/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni alerjen</a>
</div>

<?php if ($allergens === []): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-leaf-line"></i></div></div>
            <h5 class="mb-1">Henüz alerjen yok</h5>
            <p class="text-muted mb-0">İlk alerjeni eklemek için "Yeni alerjen"e tıklayın.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row" id="sortable-allergens">
    <?php foreach ($allergens as $allergen): ?>
        <?php
        $id       = (int) $allergen['id'];
        $isActive = (int) $allergen['is_active'] === 1;
        $toggle   = $isActive ? 'Pasifleştir' : 'Aktifleştir';
        ?>
        <div class="col-xxl-3 col-xl-4 col-md-6 allergen-card" data-id="<?= esc((string) $id, 'attr') ?>">
            <div class="card card-animate card-height-100<?= $isActive ? '' : ' is-passive' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="min-w-0">
                            <p class="fw-medium text-muted font-monospace mb-0 text-truncate"><?= esc($allergen['code']) ?></p>
                            <h4 class="mt-3 mb-3 ff-secondary fw-semibold text-truncate"><?= esc($allergen['name']) ?></h4>
                            <p class="mb-0">
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                <?php endif ?>
                                <span class="badge bg-light text-body ms-1" data-bs-toggle="tooltip" title="Sıra"><i class="ri-sort-asc align-bottom"></i> <?= esc((string) $allergen['sort_order']) ?></span>
                            </p>
                        </div>
                        <div class="avatar-md flex-shrink-0 ms-2">
                            <span class="avatar-title bg-<?= $isActive ? 'primary' : 'secondary' ?>-subtle text-<?= $isActive ? 'primary' : 'secondary' ?> rounded-circle fs-1">
                                <?php if ($allergen['icon_url'] !== null): ?>
                                    <img src="<?= esc($allergen['icon_url'], 'attr') ?>" alt="">
                                <?php else: ?>
                                    <i class="ri-leaf-line"></i>
                                <?php endif ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center">
                    <span class="drag-handle text-muted fs-18 flex-grow-1" data-bs-toggle="tooltip" title="Sürükleyerek sırala" aria-label="Sürükleyerek sırala"><i class="ri-drag-move-2-line"></i></span>
                    <div class="d-inline-flex gap-1">
                        <a href="<?= site_url('admin/allergens/' . $id . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                           data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                        <?= form_open('admin/allergens/' . $id . '/toggle', ['class' => 'd-inline']) ?>
                            <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $isActive ? 'warning' : 'success' ?>"
                                    data-bs-toggle="tooltip" title="<?= $toggle ?>" aria-label="<?= $toggle ?>"><i class="ri-shut-down-line"></i></button>
                        <?= form_close() ?>
                        <?= form_open('admin/allergens/' . $id . '/delete', [
                            'class'               => 'd-inline',
                            'data-confirm'        => $allergen['name'] . ' alerjeni silinecek. Bu işlem geri alınamaz.',
                            'data-confirm-button' => 'Evet, sil',
                        ]) ?>
                            <button type="submit" class="btn btn-sm btn-icon btn-soft-danger"
                                    data-bs-toggle="tooltip" title="Sil" aria-label="Sil"><i class="ri-delete-bin-line"></i></button>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
<?= $this->section('js') ?>
<script src="<?= asset_url('libs/sortablejs/Sortable.min.js') ?>"></script>
<script>
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var list = document.getElementById('sortable-allergens');
    if (!list || !meta) return;
    Sortable.create(list, {
        handle: '.drag-handle',
        draggable: '.allergen-card',
        animation: 150,
        onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) return;
            var ids = Array.from(list.querySelectorAll('[data-id]')).map(function (el) { return +el.dataset.id; });
            fetch(<?= json_encode(site_url('admin/allergens/reorder')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta.content },
                body: JSON.stringify({ ids: ids })
            }).then(function (res) {
                var t = res.headers.get('X-CSRF-TOKEN'); if (t) meta.content = t;
                if (!res.ok) { location.reload(); return; }
                // Sıra rozetlerini yeni konuma göre güncelle
                list.querySelectorAll('[data-id]').forEach(function (el, i) {
                    var badge = el.querySelector('.ri-sort-asc');
                    if (badge) badge.parentNode.lastChild.textContent = ' ' + i;
                });
            }).catch(function () { location.reload(); });
        }
    });
})();
</script>
<?= $this->endSection() ?>
