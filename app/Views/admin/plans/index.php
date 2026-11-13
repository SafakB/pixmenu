<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'Paketler') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Paketler']]) ?>
<?= $this->section('css') ?>
<style>
    #sortable-plans .drag-handle { cursor: grab; }
    #sortable-plans tr.sortable-ghost { opacity: .4; }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <h5 class="card-title mb-0 flex-grow-1">Paketler <span class="badge bg-light text-muted ms-1"><?= count($plans) ?></span></h5>
        <a href="<?= site_url('admin/plans/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni paket</a>
    </div>
    <div class="card-body">
        <?php if ($plans === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-vip-crown-2-line"></i></div></div>
                <h5 class="mb-1">Henüz paket yok</h5>
                <p class="text-muted mb-0">İlk paketi eklemek için "Yeni paket"e tıklayın.</p>
            </div>
        <?php else: ?>
            <p class="text-muted small"><i class="ri-drag-move-2-line align-bottom me-1"></i>Sırayı (paket karşılaştırma sayfasındaki düzen) değiştirmek için satırları sürükleyin.</p>
            <div class="table-responsive table-card">
                <table class="table align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px"></th>
                            <th>Paket</th>
                            <th>Aylık / Yıllık</th>
                            <th>Şube</th>
                            <th>Haklar</th>
                            <th>Kullanan</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="sortable-plans">
                    <?php foreach ($plans as $p): ?>
                        <?php
                        $id     = (int) $p['id'];
                        $active = (int) $p['is_active'] === 1;
                        $label  = $active ? 'Pasifleştir' : 'Aktifleştir';
                        ?>
                        <tr data-id="<?= esc((string) $id, 'attr') ?>">
                            <td><span class="drag-handle text-muted fs-18" data-bs-toggle="tooltip" title="Sürükleyerek sırala" aria-label="Sürükleyerek sırala"><i class="ri-drag-move-2-line"></i></span></td>
                            <td>
                                <a href="<?= site_url('admin/plans/' . $id . '/edit') ?>" class="badge fs-12 <?= esc(($p['badge_class'] ?? '') !== '' ? $p['badge_class'] : 'bg-primary', 'attr') ?>"><?= esc($p['name']) ?></a>
                                <?php if ((int) $p['is_featured'] === 1): ?><span class="badge bg-danger-subtle text-danger ms-1">Popüler</span><?php endif ?>
                                <div class="text-muted small font-monospace"><?= esc($p['code']) ?></div>
                            </td>
                            <td>
                                <?php if ($p['trial_days'] !== null): ?>
                                    <span class="badge bg-info-subtle text-info"><?= esc((string) $p['trial_days']) ?> gün deneme</span>
                                <?php else: ?>
                                    <?= esc(pix_money($p['price_monthly'], $p['currency_code'])) ?>
                                    <div class="text-muted small"><?= esc(pix_money($p['price_yearly'], $p['currency_code'])) ?> / yıl</div>
                                <?php endif ?>
                            </td>
                            <td><?= $p['max_branches'] === null ? 'Sınırsız' : esc((string) $p['max_branches']) ?></td>
                            <td class="fs-13">
                                <span class="d-block"><i class="<?= (int) $p['allow_custom_domain'] === 1 ? 'ri-checkbox-circle-fill text-success' : 'ri-close-circle-fill text-danger' ?> align-bottom me-1"></i>Alan adı</span>
                                <span class="d-block"><i class="<?= (int) $p['allow_custom_design'] === 1 ? 'ri-checkbox-circle-fill text-success' : 'ri-close-circle-fill text-danger' ?> align-bottom me-1"></i>Özel tasarım</span>
                            </td>
                            <td><span class="badge bg-light text-body"><?= esc((string) $p['current_count']) ?> restoran</span></td>
                            <td>
                                <?php if ($active): ?>
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('admin/plans/' . $id . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                       data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                    <?= form_open('admin/plans/' . $id . '/toggle', ['class' => 'd-inline']) ?>
                                        <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $active ? 'warning' : 'success' ?>"
                                                data-bs-toggle="tooltip" title="<?= $label ?>" aria-label="<?= $label ?>"><i class="ri-shut-down-line"></i></button>
                                    <?= form_close() ?>
                                    <?= form_open('admin/plans/' . $id . '/delete', [
                                        'class'               => 'd-inline',
                                        'data-confirm'        => $p['name'] . ' paketi silinecek. Bu işlem geri alınamaz.',
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
    var list = document.getElementById('sortable-plans');
    if (!list || !meta) return;
    Sortable.create(list, {
        handle: '.drag-handle', animation: 150,
        onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) return;
            var ids = Array.from(list.querySelectorAll('[data-id]')).map(function (el) { return +el.dataset.id; });
            fetch(<?= json_encode(site_url('admin/plans/reorder')) ?>, {
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
