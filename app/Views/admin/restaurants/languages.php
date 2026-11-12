<?= $this->extend('layouts/admin') ?>
<?php
$errors   = session('errors') ?? [];
$showUrl  = site_url('admin/restaurants/' . $restaurant['id']);
$selected = count(array_filter($languages, static fn ($l) => ! empty($l['selected'])));
?>
<?php $this->setVar('title', 'Restoran dilleri') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')],
    ['label' => $restaurant['name'], 'url' => $showUrl],
    ['label' => 'Diller'],
]) ?>
<?= $this->section('content') ?>
<?= form_open('admin/restaurants/' . $restaurant['id'] . '/languages') ?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="card-title mb-0 flex-grow-1">
                    <i class="ri-translate-2 text-warning align-bottom me-1"></i> Diller
                    <span class="badge bg-warning-subtle text-warning align-middle ms-1"><?= esc((string) $selected) ?> / <?= esc((string) count($languages)) ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($errors['language_ids'])): ?>
                    <div class="alert alert-danger py-2"><?= esc($errors['language_ids']) ?></div>
                <?php endif ?>

                <?php if ($languages === []): ?>
                    <div class="text-center py-4">
                        <div class="avatar-md mx-auto mb-3">
                            <div class="avatar-title bg-light text-muted rounded-circle fs-2"><i class="ri-translate-2"></i></div>
                        </div>
                        <h5 class="mb-1">Tanımlı dil yok</h5>
                        <p class="text-muted mb-0">Önce <a href="<?= site_url('admin/languages') ?>">Diller</a> sayfasından dil ekleyin.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($languages as $language): ?>
                            <?php
                            $lid      = 'lang-' . $language['id'];
                            $isActive = ! empty($language['is_active']);
                            $isSel    = ! empty($language['selected']);
                            $disabled = ! $isActive && ! $isSel;
                            ?>
                            <label class="list-group-item d-flex align-items-center gap-3 mb-0<?= $disabled ? ' bg-light text-muted' : '' ?>" for="<?= esc($lid, 'attr') ?>"<?= $disabled ? '' : ' role="button"' ?>>
                                <input class="form-check-input fs-16 m-0 flex-shrink-0" type="checkbox" id="<?= esc($lid, 'attr') ?>" name="language_ids[]" value="<?= esc((string) $language['id'], 'attr') ?>"<?= $isSel ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?>>
                                <span class="flex-grow-1">
                                    <span class="fw-medium d-block"><?= esc($language['native_name']) ?></span>
                                    <span class="text-muted fs-12 text-uppercase"><?= esc($language['code']) ?></span>
                                </span>
                                <span class="d-flex flex-wrap justify-content-end gap-1">
                                    <?php if (! empty($language['is_default'])): ?>
                                        <span class="badge bg-primary-subtle text-primary"><i class="ri-star-fill align-bottom me-1"></i>Varsayılan</span>
                                    <?php endif ?>
                                    <?php if (! $isActive): ?>
                                        <span class="badge bg-danger-subtle text-danger">Global olarak pasif</span>
                                    <?php endif ?>
                                </span>
                            </label>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Bilgi</h5></div>
            <div class="card-body">
                <ul class="list-unstyled text-muted mb-0 vstack gap-2">
                    <li class="d-flex gap-2"><i class="ri-information-line text-info fs-16"></i><span>İşaretli diller restoranda kalır, işareti kaldırılanlar çıkarılır.</span></li>
                    <li class="d-flex gap-2"><i class="ri-history-line text-success fs-16"></i><span>Çıkarılan dilin çevirileri silinmez; dil geri eklenirse veriler geri gelir.</span></li>
                    <li class="d-flex gap-2"><i class="ri-star-line text-primary fs-16"></i><span>Restoranın veya şubelerinin varsayılan dili çıkarılamaz.</span></li>
                    <li class="d-flex gap-2"><i class="ri-forbid-line text-danger fs-16"></i><span>Global olarak pasif diller yeni eklenemez.</span></li>
                </ul>
            </div>
            <div class="card-footer d-flex gap-2 justify-content-end">
                <a href="<?= $showUrl ?>" class="btn btn-light">Vazgeç</a>
                <button type="submit" class="btn btn-success"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
            </div>
        </div>
    </div>
</div>
<?= form_close() ?>
<?= $this->endSection() ?>
