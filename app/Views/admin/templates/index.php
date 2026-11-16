<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'Menü şablonları') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Menü şablonları']]) ?>
<?php
$total       = count($templates);
$activeCount = count(array_filter($templates, static fn ($t) => (int) $t['is_active'] === 1));
$usedCount   = count(array_filter($templates, static fn ($t) => (int) $t['branch_count'] > 0));
?>
<?= $this->section('css') ?>
<style>
    .template-card .drag-handle { cursor: grab; }
    .template-card.sortable-ghost { opacity: .4; }
    .template-card.sortable-chosen .card { box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15); }
    .template-card .card.is-passive .avatar-title { filter: grayscale(1); opacity: .6; }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-4">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Toplam şablon</p>
                        <h2 class="mt-3 mt-md-4 ff-secondary fw-semibold mb-0"><?= $total ?></h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0 d-none d-md-block">
                        <span class="avatar-title bg-info-subtle text-info rounded-circle fs-2"><i class="ri-layout-masonry-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card card-animate bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-white-50 mb-0">Aktif</p>
                        <h2 class="mt-3 mt-md-4 ff-secondary fw-semibold text-white mb-0"><?= $activeCount ?></h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0 d-none d-md-block">
                        <span class="avatar-title bg-white bg-opacity-25 text-white rounded-circle fs-2"><i class="ri-checkbox-circle-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Kullanımda</p>
                        <h2 class="mt-3 mt-md-4 ff-secondary fw-semibold mb-0"><?= $usedCount ?></h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0 d-none d-md-block">
                        <span class="avatar-title bg-success-subtle text-success rounded-circle fs-2"><i class="ri-map-pin-2-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <p class="text-muted mb-0 flex-grow-1">
        <?php if ($templates !== []): ?>
            <i class="ri-drag-move-2-line align-bottom me-1"></i>Sırayı değiştirmek için kartları tutup sürükleyin. Şubeye şablon, şube formundan atanır.
        <?php endif ?>
    </p>
    <a href="<?= site_url('admin/templates/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni şablon</a>
</div>

<?php if ($templates === []): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-layout-masonry-line"></i></div></div>
            <h5 class="mb-1">Henüz şablon yok</h5>
            <p class="text-muted mb-0">İlk şablonu eklemek için "Yeni şablon"a tıklayın.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row" id="sortable-templates">
    <?php foreach ($templates as $template): ?>
        <?php
        $code      = (string) $template['code'];
        $isActive  = (int) $template['is_active'] === 1;
        $isDefault = ! empty($template['is_default']);
        $used      = (int) $template['branch_count'];
        $locked    = $isDefault || $used > 0; // silinemez / pasifleştirilemez
        $toggle    = $isActive ? 'Pasifleştir' : 'Aktifleştir';
        $base      = 'admin/templates/' . $code;
        ?>
        <div class="col-xxl-3 col-xl-4 col-md-6 template-card" data-id="<?= esc($code, 'attr') ?>">
            <div class="card card-animate card-height-100<?= $isActive ? '' : ' is-passive' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="min-w-0">
                            <p class="fw-medium text-muted font-monospace mb-0 text-truncate"><?= esc($code) ?></p>
                            <h4 class="mt-3 mb-1 ff-secondary fw-semibold text-truncate"><?= esc($template['name']) ?></h4>
                            <p class="text-muted mb-3 text-truncate text-wrap"><?= esc((string) ($template['description'] ?? '')) ?: '&nbsp;' ?></p>
                            <p class="mb-0">
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                <?php endif ?>
                                <?php if ($isDefault): ?>
                                    <span class="badge bg-primary-subtle text-primary ms-1">Varsayılan</span>
                                <?php endif ?>
                                <span class="badge bg-light text-body ms-1" data-bs-toggle="tooltip" title="Kullanan şube"><i class="ri-map-pin-2-line align-bottom"></i> <?= esc((string) $used) ?></span>
                            </p>
                        </div>
                        <div class="flex-shrink-0 ms-2">
                            <div class="avatar-md">
                                <span class="avatar-title bg-primary-subtle text-primary rounded fs-1"><i class="ri-layout-masonry-line"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center">
                    <span class="drag-handle text-muted fs-18 flex-grow-1" data-bs-toggle="tooltip" title="Sürükleyerek sırala" aria-label="Sürükleyerek sırala"><i class="ri-drag-move-2-line"></i></span>
                    <div class="d-inline-flex gap-1">
                        <a href="<?= site_url($base . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                           data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                        <?php if (! $locked || ! $isActive): ?>
                            <?= form_open($base . '/toggle', ['class' => 'd-inline']) ?>
                                <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $isActive ? 'warning' : 'success' ?>"
                                        data-bs-toggle="tooltip" title="<?= $toggle ?>" aria-label="<?= $toggle ?>"><i class="ri-shut-down-line"></i></button>
                            <?= form_close() ?>
                        <?php endif ?>
                        <?php if (! $locked): ?>
                            <?= form_open($base . '/delete', [
                                'class'               => 'd-inline',
                                'data-confirm'        => $template['name'] . ' şablonu silinecek. Bu işlem geri alınamaz.',
                                'data-confirm-button' => 'Evet, sil',
                            ]) ?>
                                <button type="submit" class="btn btn-sm btn-icon btn-soft-danger"
                                        data-bs-toggle="tooltip" title="Sil" aria-label="Sil"><i class="ri-delete-bin-line"></i></button>
                            <?= form_close() ?>
                        <?php endif ?>
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
    var list = document.getElementById('sortable-templates');
    if (!list || !meta) return;
    Sortable.create(list, {
        handle: '.drag-handle',
        draggable: '.template-card',
        animation: 150,
        onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) return;
            var ids = Array.from(list.querySelectorAll('[data-id]')).map(function (el) { return el.dataset.id; });
            fetch(<?= json_encode(site_url('admin/templates/reorder')) ?>, {
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
