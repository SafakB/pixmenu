<?= $this->extend('layouts/admin') ?>
<?php
/**
 * 403 — rolün izni olmayan admin sayfası/işlemi (AdminPermissionFilter).
 * Değişkenler: currentAdmin, title.
 */
?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-xl-5 col-lg-7">
        <div class="card mt-4">
            <div class="card-body p-4 p-sm-5 text-center">
                <div class="avatar-lg mx-auto mb-4">
                    <div class="avatar-title bg-danger-subtle text-danger rounded-circle display-5">
                        <i class="ri-lock-2-line"></i>
                    </div>
                </div>
                <h4 class="mb-2">Bu işlem için yetkiniz yok</h4>
                <p class="text-muted mb-4">
                    Rolünüz (<?= esc(service('adminAuth')->roleLabel()) ?>) bu sayfayı görüntülemeye veya bu işlemi yapmaya izin vermiyor.
                    Erişim gerekiyorsa sistem yöneticinizle iletişime geçin.
                </p>
                <a href="<?= site_url('admin') ?>" class="btn btn-primary"><i class="ri-home-4-line align-bottom me-1"></i> Gösterge paneline dön</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
