<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'Diller') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Diller']]) ?>
<?php
// Dil kodu → public/assets/images/flags altındaki bayrak dosyası. Listede olmayan dillerde kod rozeti gösterilir.
$flags = [
    'tr' => 'tr.svg',
    'en' => 'us.svg',
    'de' => 'germany.svg',
    'ru' => 'russia.svg',
    'ar' => 'ae.svg',
    'es' => 'spain.svg',
    'it' => 'italy.svg',
    'fr' => 'french.svg',
    'zh' => 'china.svg',
];

$total       = count($languages);
$activeCount = count(array_filter($languages, static fn ($l) => (int) $l['is_active'] === 1));
$rtlCount    = count(array_filter($languages, static fn ($l) => $l['direction'] === 'rtl'));
?>
<?= $this->section('css') ?>
<style>
    .language-card .drag-handle { cursor: grab; }
    .language-card.sortable-ghost { opacity: .4; }
    .language-card.sortable-chosen .card { box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15); }
    .language-flag { width: 3.75rem; height: 2.5rem; border-radius: .25rem; object-fit: cover; box-shadow: 0 0 0 1px var(--vz-border-color); }
    .language-card .card.is-passive .language-flag { filter: grayscale(1); opacity: .5; }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-4">
        <div class="card card-animate">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Toplam dil</p>
                        <h2 class="mt-3 mt-md-4 ff-secondary fw-semibold mb-0"><?= $total ?></h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0 d-none d-md-block">
                        <span class="avatar-title bg-info-subtle text-info rounded-circle fs-2"><i class="ri-translate-2"></i></span>
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
                        <p class="fw-medium text-muted mb-0"><span class="d-none d-md-inline">Sağdan sola (</span>RTL<span class="d-none d-md-inline">)</span></p>
                        <h2 class="mt-3 mt-md-4 ff-secondary fw-semibold mb-0"><?= $rtlCount ?></h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0 d-none d-md-block">
                        <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-2"><i class="ri-text-direction-r"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <p class="text-muted mb-0 flex-grow-1">
        <?php if ($languages !== []): ?>
            <i class="ri-drag-move-2-line align-bottom me-1"></i>Sırayı değiştirmek için kartları tutup sürükleyin.
        <?php endif ?>
    </p>
    <a href="<?= site_url('admin/languages/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni dil</a>
</div>

<?php if ($languages === []): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-translate-2"></i></div></div>
            <h5 class="mb-1">Henüz dil yok</h5>
            <p class="text-muted mb-0">İlk dili eklemek için "Yeni dil"e tıklayın.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row" id="sortable-languages">
    <?php foreach ($languages as $language): ?>
        <?php
        $id       = (int) $language['id'];
        $code     = (string) $language['code'];
        $isActive = (int) $language['is_active'] === 1;
        $isRtl    = $language['direction'] === 'rtl';
        $toggle   = $isActive ? 'Pasifleştir' : 'Aktifleştir';
        $flag     = $flags[strtolower($code)] ?? null;
        ?>
        <div class="col-xxl-3 col-xl-4 col-md-6 language-card" data-id="<?= esc((string) $id, 'attr') ?>">
            <div class="card card-animate card-height-100<?= $isActive ? '' : ' is-passive' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="min-w-0">
                            <p class="fw-medium text-muted font-monospace text-uppercase mb-0 text-truncate"><?= esc($code) ?></p>
                            <h4 class="mt-3 mb-1 ff-secondary fw-semibold text-truncate"><bdi dir="<?= $isRtl ? 'rtl' : 'ltr' ?>"><?= esc($language['native_name']) ?></bdi></h4>
                            <p class="text-muted mb-3 text-truncate"><?= esc($language['name']) ?></p>
                            <p class="mb-0">
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                <?php endif ?>
                                <span class="badge bg-<?= $isRtl ? 'warning' : 'info' ?>-subtle text-<?= $isRtl ? 'warning' : 'info' ?> ms-1"
                                      data-bs-toggle="tooltip" title="<?= $isRtl ? 'Sağdan sola' : 'Soldan sağa' ?>"><?= $isRtl ? 'RTL' : 'LTR' ?></span>
                                <span class="badge bg-light text-body ms-1" data-bs-toggle="tooltip" title="Sıra"><i class="ri-sort-asc align-bottom"></i> <?= esc((string) $language['sort_order']) ?></span>
                            </p>
                        </div>
                        <div class="flex-shrink-0 ms-2">
                            <?php if ($flag !== null): ?>
                                <img src="<?= asset_url('images/flags/' . $flag) ?>" alt="" class="language-flag">
                            <?php else: ?>
                                <div class="language-flag">
                                    <span class="avatar-title bg-primary-subtle text-primary rounded fw-semibold text-uppercase font-monospace"><?= esc($code) ?></span>
                                </div>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center">
                    <span class="drag-handle text-muted fs-18 flex-grow-1" data-bs-toggle="tooltip" title="Sürükleyerek sırala" aria-label="Sürükleyerek sırala"><i class="ri-drag-move-2-line"></i></span>
                    <div class="d-inline-flex gap-1">
                        <a href="<?= site_url('admin/languages/' . $id . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                           data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                        <?= form_open('admin/languages/' . $id . '/toggle', ['class' => 'd-inline']) ?>
                            <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $isActive ? 'warning' : 'success' ?>"
                                    data-bs-toggle="tooltip" title="<?= $toggle ?>" aria-label="<?= $toggle ?>"><i class="ri-shut-down-line"></i></button>
                        <?= form_close() ?>
                        <?= form_open('admin/languages/' . $id . '/delete', [
                            'class'               => 'd-inline',
                            'data-confirm'        => $language['native_name'] . ' dili silinecek. Bu işlem geri alınamaz.',
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
    var list = document.getElementById('sortable-languages');
    if (!list || !meta) return;
    Sortable.create(list, {
        handle: '.drag-handle',
        draggable: '.language-card',
        animation: 150,
        onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) return;
            var ids = Array.from(list.querySelectorAll('[data-id]')).map(function (el) { return +el.dataset.id; });
            fetch(<?= json_encode(site_url('admin/languages/reorder')) ?>, {
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
