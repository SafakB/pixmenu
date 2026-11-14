<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'İşlem Kayıtları') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'İşlem Kayıtları']]) ?>
<?php
$query = array_filter([
    'area'       => $filters['area'],
    'restaurant' => $filters['restaurant'],
    'actor'      => $filters['actor'],
    'action'     => $filters['action'],
    'from'       => $filters['from'],
    'to'         => $filters['to'],
], static fn ($value): bool => $value !== null && $value !== '');
$pageUrl = static fn (int $target): string => site_url('admin/logs') . '?' . http_build_query($query + ['page' => $target]);
$areas   = ['admin' => ['Admin', 'bg-primary-subtle text-primary'], 'panel' => ['Panel', 'bg-info-subtle text-info']];
?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <form method="get" action="<?= site_url('admin/logs') ?>" class="row g-2 align-items-end">
            <div class="col-6 col-lg-auto">
                <label class="form-label mb-1" for="f-area">Alan</label>
                <select name="area" id="f-area" class="form-select form-select-sm">
                    <option value="">Tümü</option>
                    <?php foreach ($areas as $key => [$label]): ?>
                        <option value="<?= esc($key, 'attr') ?>"<?= $filters['area'] === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label mb-1" for="f-restaurant">Restoran</label>
                <select name="restaurant" id="f-restaurant" class="form-select form-select-sm">
                    <option value="">Tüm restoranlar</option>
                    <?php foreach ($restaurants as $r): ?>
                        <option value="<?= esc((string) $r['id'], 'attr') ?>"<?= $filters['restaurant'] === $r['id'] ? ' selected' : '' ?>><?= esc($r['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label mb-1" for="f-actor">Kullanıcı (e-posta)</label>
                <input type="text" name="actor" id="f-actor" class="form-control form-control-sm" maxlength="100" value="<?= esc($filters['actor'], 'attr') ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label mb-1" for="f-action">İşlem kodu</label>
                <input type="text" name="action" id="f-action" class="form-control form-control-sm" maxlength="60" placeholder="ör. invoice" value="<?= esc($filters['action'], 'attr') ?>">
            </div>
            <div class="col-6 col-lg-auto">
                <label class="form-label mb-1" for="f-from">Başlangıç</label>
                <input type="date" name="from" id="f-from" class="form-control form-control-sm" value="<?= esc($filters['from'], 'attr') ?>">
            </div>
            <div class="col-6 col-lg-auto">
                <label class="form-label mb-1" for="f-to">Bitiş</label>
                <input type="date" name="to" id="f-to" class="form-control form-control-sm" value="<?= esc($filters['to'], 'attr') ?>">
            </div>
            <div class="col-12 col-lg-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="ri-filter-3-line align-bottom me-1"></i> Filtrele</button>
                <?php if ($query !== []): ?>
                    <a href="<?= site_url('admin/logs') ?>" class="btn btn-sm btn-soft-secondary">Temizle</a>
                <?php endif ?>
            </div>
        </form>
    </div>
    <div class="card-body">
        <?php if ($logs === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-history-line"></i></div></div>
                <h5 class="mb-1">Kayıt bulunamadı</h5>
                <p class="text-muted mb-0"><?= $query === [] && $page === 1 ? 'Henüz kaydedilmiş bir işlem yok.' : 'Bu filtreyle eşleşen işlem kaydı yok.' ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive table-card">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Zaman</th><th>Alan</th><th>Kullanıcı</th><th>Restoran</th><th>İşlem</th><th>Ayrıntı</th><th>IP</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-nowrap"><?= esc(pix_date($log['created_at'], true)) ?></td>
                            <td>
                                <?php [$areaLabel, $areaClass] = $areas[$log['area']] ?? [$log['area'], 'bg-secondary-subtle text-secondary'] ?>
                                <span class="badge <?= esc($areaClass, 'attr') ?>"><?= esc($areaLabel) ?></span>
                            </td>
                            <td class="text-nowrap"><?= esc($log['actor_label']) ?></td>
                            <td>
                                <?php if ($log['restaurant_id'] === null): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <a href="<?= site_url('admin/restaurants/' . $log['restaurant_id']) ?>" class="text-reset"><?= esc($log['restaurant_name']) ?></a>
                                <?php endif ?>
                            </td>
                            <td>
                                <span class="fw-medium d-block"><?= esc($log['action_label']) ?></span>
                                <small class="text-muted text-break"><?= esc($log['path']) ?></small>
                            </td>
                            <td>
                                <?php if ($log['detail'] === []): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($log['detail'] as $key => $value): ?>
                                            <span class="badge bg-light text-body fw-normal text-wrap text-start"><span class="text-muted"><?= esc($key) ?>:</span> <?= esc($value) ?></span>
                                        <?php endforeach ?>
                                    </div>
                                <?php endif ?>
                            </td>
                            <td class="text-muted text-nowrap"><?= esc($log['ip']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
    <div class="card-footer d-flex flex-wrap align-items-center gap-2">
        <span class="text-muted fs-13 flex-grow-1">Kayıtlar <?= esc((string) $retentionDays) ?> gün saklanır. Sayfa <?= esc((string) $page) ?></span>
        <?php if ($page > 1): ?>
            <a href="<?= esc($pageUrl($page - 1), 'attr') ?>" class="btn btn-sm btn-soft-primary"><i class="ri-arrow-left-s-line align-bottom"></i> Daha yeni</a>
        <?php endif ?>
        <?php if ($hasMore): ?>
            <a href="<?= esc($pageUrl($page + 1), 'attr') ?>" class="btn btn-sm btn-soft-primary">Daha eski <i class="ri-arrow-right-s-line align-bottom"></i></a>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
