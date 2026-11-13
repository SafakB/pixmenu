<?= $this->extend('layouts/panel') ?>
<?php
$errors = session('errors') ?? [];
?>
<?php $this->setVar('title', 'Restoran bilgileri') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Restoran Bilgileri']]) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-8">
        <?= form_open_multipart('panel/restaurant', ['id' => 'restaurant-form']) ?>
            <?= view('partials/translations', [
                'languages'    => $languages,
                'translations' => $translations,
                'fields'       => ['name' => 'text', 'description' => 'textarea'],
            ]) ?>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Logo</h5></div>
                <div class="card-body">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                        <div class="flex-shrink-0 text-center">
                            <img id="logo-preview" src="<?= esc($logo_url ?? '', 'attr') ?>" alt="Logo" class="img-thumbnail<?= $logo_url === null ? ' d-none' : '' ?>" style="max-height:120px">
                            <?php if ($logo_url === null): ?>
                                <div id="logo-placeholder" class="avatar-lg">
                                    <div class="avatar-title bg-light text-muted rounded fs-1"><i class="ri-image-line"></i></div>
                                </div>
                            <?php endif ?>
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label" for="logo"><?= $logo_url === null ? 'Logo yükle' : 'Yeni logo yükle' ?></label>
                            <input class="form-control<?= isset($errors['logo']) ? ' is-invalid' : '' ?>" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp"
                                   data-preview="#logo-preview" data-preview-placeholder="#logo-placeholder">
                            <?php if (isset($errors['logo'])): ?><div class="invalid-feedback"><?= esc($errors['logo']) ?></div><?php endif ?>
                            <div class="form-text">jpg, png, webp — en fazla 2 MB.</div>
                        </div>
                    </div>
                </div>
            </div>
        <?= form_close() ?>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Restoran</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <th class="ps-0 text-muted fw-normal" scope="row">Slug</th>
                                <td class="font-monospace text-end"><?= esc($restaurant['slug']) ?></td>
                            </tr>
                            <tr>
                                <th class="ps-0 text-muted fw-normal" scope="row">Para birimi</th>
                                <td class="text-end"><?= esc($restaurant['currency_code']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mb-0"><i class="ri-information-line align-bottom"></i> Bu bilgileri yalnızca sistem yöneticisi değiştirebilir.</p>
            </div>
            <div class="card-footer">
                <button type="submit" form="restaurant-form" class="btn btn-success w-100"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
            </div>
        </div>

        <?php if ($logo_url !== null): ?>
            <div class="card">
                <div class="card-body d-flex align-items-center gap-2">
                    <p class="text-muted small mb-0 flex-grow-1">Mevcut logoyu kaldırmak için.</p>
                    <?= form_open('panel/restaurant/logo/delete', [
                        'class'               => 'flex-shrink-0',
                        'data-confirm'        => 'Restoran logosu silinecek. Bu işlem geri alınamaz.',
                        'data-confirm-button' => 'Evet, sil',
                    ]) ?>
                        <button type="submit" class="btn btn-soft-danger btn-sm"><i class="ri-delete-bin-line align-bottom me-1"></i> Logoyu sil</button>
                    <?= form_close() ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
