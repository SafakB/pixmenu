<?= $this->extend('layouts/admin') ?>
<?php
$id     = $restaurant['id'];
$base   = 'admin/restaurants/' . $id;
$active = ! empty($restaurant['is_active']);
$roles  = ['owner' => ['Sahip', 'primary'], 'manager' => ['Şube yöneticisi', 'info']];
?>
<?php $this->setVar('title', $restaurant['name']) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')],
    ['label' => $restaurant['name']],
]) ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
            <div class="flex-shrink-0">
                <?php if ($restaurant['logo_url'] !== null): ?>
                    <img src="<?= esc($restaurant['logo_url'], 'attr') ?>" alt="Logo" class="avatar-lg rounded bg-light p-1 object-fit-contain">
                <?php else: ?>
                    <div class="avatar-lg">
                        <div class="avatar-title bg-primary-subtle text-primary rounded fs-1"><i class="ri-store-2-line"></i></div>
                    </div>
                <?php endif ?>
            </div>
            <div class="flex-grow-1">
                <h4 class="mb-2">
                    <?= esc($restaurant['name']) ?>
                    <?php if ($active): ?>
                        <span class="badge bg-success-subtle text-success fs-12 align-middle ms-1">Aktif</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger fs-12 align-middle ms-1">Pasif</span>
                    <?php endif ?>
                </h4>
                <div class="d-flex flex-wrap gap-3 text-muted">
                    <div><i class="ri-links-line align-bottom me-1"></i> <?= esc($restaurant['slug']) ?></div>
                    <div><i class="ri-money-dollar-circle-line align-bottom me-1"></i> <?= esc($restaurant['currency_code']) ?></div>
                    <div><i class="ri-hashtag align-bottom me-1"></i> <?= esc((string) $id) ?></div>
                </div>
            </div>
            <div class="flex-shrink-0 d-flex flex-wrap gap-2">
                <a href="<?= site_url($base . '/edit') ?>" class="btn btn-primary btn-sm"><i class="ri-pencil-line align-bottom me-1"></i> Bilgileri düzenle</a>
                <?= form_open($base . '/toggle', ['class' => 'd-inline'] + ($active ? [
                    'data-confirm'         => $restaurant['name'] . ' pasifleştirilecek. Kullanıcılarının oturumu kapanır, menüleri yayından kalkar.',
                    'data-confirm-button'  => 'Evet, pasifleştir',
                    'data-confirm-variant' => 'warning',
                ] : [])) ?>
                    <?php if ($active): ?>
                        <button type="submit" class="btn btn-soft-warning btn-sm"><i class="ri-shut-down-line align-bottom me-1"></i> Pasifleştir</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-soft-success btn-sm"><i class="ri-shut-down-line align-bottom me-1"></i> Aktifleştir</button>
                    <?php endif ?>
                <?= form_close() ?>
                <?= form_open($base . '/delete', [
                    'class'               => 'd-inline',
                    'data-confirm'        => $restaurant['name'] . ' silinecek. Bu işlem geri alınamaz.',
                    'data-confirm-button' => 'Evet, sil',
                ]) ?>
                    <button type="submit" class="btn btn-soft-danger btn-sm"><i class="ri-delete-bin-line align-bottom me-1"></i> Sil</button>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8">
        <?php /* ---------- Şubeler ---------- */ ?>
        <div class="card">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0 flex-grow-1">
                    <i class="ri-map-pin-2-line text-success align-bottom me-1"></i> Şubeler
                    <span class="badge bg-success-subtle text-success align-middle ms-1"><?= esc((string) count($branches)) ?></span>
                </h5>
                <a href="<?= site_url($base . '/branches/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni şube</a>
                <a href="<?= site_url($base . '/branches') ?>" class="btn btn-soft-primary btn-sm">Yönet <i class="ri-arrow-right-line align-bottom"></i></a>
            </div>
            <div class="card-body">
                <?php if ($branches === []): ?>
                    <div class="text-center text-muted py-4">
                        <i class="ri-map-pin-2-line fs-1 d-block mb-2"></i>
                        Henüz şube yok. İlk şubeyi eklemek için "Yeni şube"ye tıklayın.
                    </div>
                <?php else: ?>
                    <div class="table-responsive table-card">
                        <table class="table align-middle table-nowrap mb-0">
                            <thead class="table-light">
                                <tr><th>Şube</th><th>Menü</th><th>Durum</th><th class="text-end">İşlemler</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($branches as $b): ?>
                                <?php $bBase = $base . '/branches/' . $b['id']; ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url($bBase . '/edit') ?>" class="fw-medium text-reset"><?= esc($b['name']) ?></a>
                                        <div class="text-muted small"><?= esc($b['slug']) ?></div>
                                    </td>
                                    <td>
                                        <a href="<?= esc($b['menu_url'], 'attr') ?>" target="_blank" rel="noopener" class="font-monospace">
                                            <?= esc($b['public_code']) ?> <i class="ri-external-link-line align-bottom"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($b['is_active']): ?>
                                            <span class="badge bg-success-subtle text-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="<?= site_url($bBase . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                               data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                            <?= form_open($bBase . '/toggle', ['class' => 'd-inline'] + ($b['is_active'] ? [
                                                'data-confirm'         => $b['name'] . ' pasifleştirilecek. Şubenin menüsü yayından kalkar.',
                                                'data-confirm-button'  => 'Evet, pasifleştir',
                                                'data-confirm-variant' => 'warning',
                                            ] : [])) ?>
                                                <?php $label = $b['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?>
                                                <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $b['is_active'] ? 'warning' : 'success' ?>"
                                                        data-bs-toggle="tooltip" title="<?= $label ?>" aria-label="<?= $label ?>"><i class="ri-shut-down-line"></i></button>
                                            <?= form_close() ?>
                                            <?= form_open($bBase . '/delete', [
                                                'class'               => 'd-inline',
                                                'data-confirm'        => $b['name'] . ' silinecek. Bu işlem geri alınamaz.',
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

        <?php /* ---------- Kullanıcılar ---------- */ ?>
        <div class="card">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0 flex-grow-1">
                    <i class="ri-team-line text-info align-bottom me-1"></i> Kullanıcılar
                    <span class="badge bg-info-subtle text-info align-middle ms-1"><?= esc((string) count($users)) ?></span>
                </h5>
                <a href="<?= site_url($base . '/users/new') ?>" class="btn btn-success btn-sm"><i class="ri-user-add-line align-bottom me-1"></i> Yeni kullanıcı</a>
                <a href="<?= site_url($base . '/users') ?>" class="btn btn-soft-primary btn-sm">Yönet <i class="ri-arrow-right-line align-bottom"></i></a>
            </div>
            <div class="card-body">
                <?php if ($users === []): ?>
                    <div class="text-center text-muted py-4">
                        <i class="ri-team-line fs-1 d-block mb-2"></i>
                        Henüz kullanıcı yok. Restoran sahibini eklemek için "Yeni kullanıcı"ya tıklayın.
                    </div>
                <?php else: ?>
                    <div class="table-responsive table-card">
                        <table class="table align-middle table-nowrap mb-0">
                            <thead class="table-light">
                                <tr><th>Kullanıcı</th><th>Rol</th><th>Şubeler</th><th>Durum</th><th class="text-end">İşlemler</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($users as $u): ?>
                                <?php
                                $uBase                   = $base . '/users/' . $u['id'];
                                [$roleLabel, $roleColor] = $roles[$u['role']] ?? [$u['role'], 'secondary'];
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url($uBase . '/edit') ?>" class="fw-medium text-reset"><?= esc($u['name']) ?></a>
                                        <div class="text-muted small"><?= esc($u['email']) ?></div>
                                    </td>
                                    <td><span class="badge bg-<?= esc($roleColor, 'attr') ?>-subtle text-<?= esc($roleColor, 'attr') ?>"><?= esc($roleLabel) ?></span></td>
                                    <td class="text-muted"><?= $u['role'] === 'owner' ? 'Tümü' : esc((string) $u['branch_count']) . ' şube' ?></td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                            <span class="badge bg-success-subtle text-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="<?= site_url($uBase . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                               data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                            <?= form_open($uBase . '/toggle', ['class' => 'd-inline'] + ($u['is_active'] ? [
                                                'data-confirm'         => $u['name'] . ' pasifleştirilecek. Kullanıcının oturumu kapanır.',
                                                'data-confirm-button'  => 'Evet, pasifleştir',
                                                'data-confirm-variant' => 'warning',
                                            ] : [])) ?>
                                                <?php $label = $u['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?>
                                                <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $u['is_active'] ? 'warning' : 'success' ?>"
                                                        data-bs-toggle="tooltip" title="<?= $label ?>" aria-label="<?= $label ?>"><i class="ri-shut-down-line"></i></button>
                                            <?= form_close() ?>
                                            <?= form_open($uBase . '/delete', [
                                                'class'               => 'd-inline',
                                                'data-confirm'        => $u['name'] . ' silinecek. Bu işlem geri alınamaz.',
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
    </div>

    <div class="col-xl-4">
        <?php /* ---------- Abonelik ---------- */ ?>
        <?php
        $bSub    = $billing['summary']['subscription'];
        $bUsage  = $billing['summary']['usage'];
        ?>
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h5 class="card-title mb-0 flex-grow-1"><i class="ri-vip-crown-2-line text-primary align-bottom me-1"></i> Abonelik</h5>
                <a href="<?= site_url($base . '/subscription') ?>" class="btn btn-soft-primary btn-sm">Yönet <i class="ri-arrow-right-line align-bottom"></i></a>
            </div>
            <div class="card-body">
                <?php if ($billing['suspended']): ?>
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="ri-forbid-2-line align-bottom me-1"></i> Askıda: <?= esc(lang('App.subscription.reason.' . $billing['suspension_reason'])) ?>
                    </div>
                <?php endif ?>
                <?php if ($bSub === null): ?>
                    <p class="text-muted text-center mb-0 py-3">Güncel abonelik yok.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex px-0"><span class="flex-grow-1 text-muted">Paket</span><span class="fw-medium"><?= esc($bSub['plan_name']) ?></span></li>
                        <li class="list-group-item d-flex px-0"><span class="flex-grow-1 text-muted">Durum</span><?= view('partials/billing/badge', ['kind' => 'subscription', 'status' => $bSub['status']]) ?></li>
                        <li class="list-group-item d-flex px-0"><span class="flex-grow-1 text-muted">Dönem</span><span><?= esc($bSub['cycle_label']) ?> · <?= esc(pix_date($bSub['current_period_end'])) ?>'e kadar</span></li>
                        <li class="list-group-item d-flex px-0"><span class="flex-grow-1 text-muted">Şube</span><span><?= esc((string) $bUsage['branches']) ?> / <?= $bUsage['max_branches'] === null ? 'Sınırsız' : esc((string) $bUsage['max_branches']) ?></span></li>
                        <li class="list-group-item d-flex px-0">
                            <span class="flex-grow-1 text-muted">Açık fatura</span>
                            <span>
                                <?= esc((string) $billing['open_invoices']) ?>
                                <?php if ($billing['overdue_invoices'] > 0): ?>
                                    <span class="badge bg-danger-subtle text-danger ms-1"><?= esc((string) $billing['overdue_invoices']) ?> gecikmiş</span>
                                <?php endif ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex px-0">
                            <span class="flex-grow-1 text-muted">Alan adı</span>
                            <a href="<?= site_url($base . '/domain') ?>"><?= $billing['domain'] === null ? 'Tanımla' : esc($billing['domain']['domain']) ?></a>
                        </li>
                    </ul>
                <?php endif ?>
            </div>
        </div>

        <?php /* ---------- Diller ---------- */ ?>
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h5 class="card-title mb-0 flex-grow-1">
                    <i class="ri-translate-2 text-warning align-bottom me-1"></i> Diller
                    <span class="badge bg-warning-subtle text-warning align-middle ms-1"><?= esc((string) count($languages)) ?></span>
                </h5>
                <a href="<?= site_url($base . '/languages') ?>" class="btn btn-soft-primary btn-sm">Yönet <i class="ri-arrow-right-line align-bottom"></i></a>
            </div>
            <div class="card-body">
                <?php if ($languages === []): ?>
                    <p class="text-muted text-center mb-0 py-3">Aktif dil yok.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($languages as $lang): ?>
                            <li class="list-group-item d-flex align-items-center px-0">
                                <span class="badge bg-light text-body text-uppercase me-2" style="min-width:2.5rem"><?= esc($lang['code']) ?></span>
                                <span class="flex-grow-1"><?= esc($lang['native_name']) ?></span>
                                <?php if ($lang['is_default']): ?>
                                    <span class="badge bg-primary-subtle text-primary"><i class="ri-star-fill align-bottom me-1"></i>Varsayılan</span>
                                <?php endif ?>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
