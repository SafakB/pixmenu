<?= $this->extend('layouts/panel') ?>
<?php $this->setVar('title', 'Kullanıcılar') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Kullanıcılar'],
]) ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <div class="flex-grow-1">
            <h5 class="card-title mb-1">
                Kullanıcılar
                <span class="badge bg-success-subtle text-success align-middle ms-1"><?= esc((string) count($users)) ?></span>
            </h5>
            <p class="text-muted small mb-0">Şube yöneticileri yalnızca kendilerine atanan şubelerin menüsünü yönetebilir.</p>
        </div>
        <a href="<?= site_url('panel/users/new') ?>" class="btn btn-success btn-sm"><i class="ri-user-add-line align-bottom me-1"></i> Yeni şube yöneticisi</a>
    </div>
    <div class="card-body">
        <?php if ($users === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-success rounded-circle fs-24"><i class="ri-team-line"></i></div></div>
                <h5 class="mb-1">Henüz kullanıcı yok</h5>
                <p class="text-muted mb-0">İlk şube yöneticisini eklemek için "Yeni şube yöneticisi"ne tıklayın.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive table-card">
                <table class="table align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Rol</th>
                            <th>Şubeler</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $base     = 'panel/users/' . $user['id'];
                        $isOwner  = $user['role'] === 'owner';
                        $isActive = ! empty($user['is_active']);
                        $initial  = mb_strtoupper(mb_substr(trim((string) $user['name']), 0, 1));
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-xs flex-shrink-0">
                                        <span class="avatar-title rounded-circle fs-13 <?= $isOwner ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' ?>"><?= esc($initial) ?></span>
                                    </div>
                                    <div class="min-w-0">
                                        <?php if ($user['editable']): ?>
                                            <a href="<?= site_url($base . '/edit') ?>" class="fw-medium text-reset"><?= esc($user['name']) ?></a>
                                        <?php else: ?>
                                            <span class="fw-medium"><?= esc($user['name']) ?></span>
                                        <?php endif ?>
                                        <div class="text-muted small"><?= esc($user['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($isOwner): ?>
                                    <span class="badge bg-primary-subtle text-primary">Restoran sahibi</span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info">Şube yöneticisi</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($isOwner): ?>
                                    <span class="text-muted">Tüm şubeler</span>
                                <?php else: ?>
                                    <i class="ri-store-2-line align-middle text-muted me-1"></i><?= esc((string) count($user['branch_ids'])) ?> şube
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <?php if ($user['editable']): ?>
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?= site_url($base . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                           data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                        <?= form_open($base . '/toggle', ['class' => 'd-inline'] + ($isActive ? [
                                            'data-confirm'         => $user['name'] . ' pasifleştirilecek. Açık oturumu kapanır ve panele giriş yapamaz.',
                                            'data-confirm-button'  => 'Evet, pasifleştir',
                                            'data-confirm-variant' => 'warning',
                                        ] : [])) ?>
                                            <?php $label = $isActive ? 'Pasifleştir' : 'Aktifleştir' ?>
                                            <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $isActive ? 'warning' : 'success' ?>"
                                                    data-bs-toggle="tooltip" title="<?= $label ?>" aria-label="<?= $label ?>"><i class="ri-shut-down-line"></i></button>
                                        <?= form_close() ?>
                                        <?= form_open($base . '/delete', [
                                            'class'               => 'd-inline',
                                            'data-confirm'        => $user['name'] . ' silinecek. Bu işlem geri alınamaz.',
                                            'data-confirm-button' => 'Evet, sil',
                                        ]) ?>
                                            <button type="submit" class="btn btn-sm btn-icon btn-soft-danger"
                                                    data-bs-toggle="tooltip" title="Sil" aria-label="Sil"><i class="ri-delete-bin-line"></i></button>
                                        <?= form_close() ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted" data-bs-toggle="tooltip" title="Restoran sahibi hesabı buradan değiştirilemez"><i class="ri-lock-line fs-16"></i></span>
                                <?php endif ?>
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
