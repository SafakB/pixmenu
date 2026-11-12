<?= $this->extend('layouts/admin') ?>
<?php
$errors      = session('errors') ?? [];
$hasOld      = old('translations', null, false) !== null;
$isActive    = $hasOld ? old('is_active', null, false) === '1' : (int) $restaurant['is_active'] === 1;
$default     = (string) old('default_language_id', $restaurant['default_language_id'], false);
$defaultCode = array_values(array_filter($languages, static fn ($l) => ! empty($l['is_default'])))[0]['code'] ?? null;
$name        = (string) (($defaultCode !== null ? ($translations[$defaultCode]['name'] ?? '') : '') ?: $restaurant['slug']);
$showUrl     = site_url('admin/restaurants/' . $restaurant['id']);
$invalid     = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>
<?php $this->setVar('title', 'Restoranı düzenle') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')],
    ['label' => $name, 'url' => $showUrl],
    ['label' => 'Düzenle'],
]) ?>
<?= $this->section('content') ?>
<?= form_open_multipart('admin/restaurants/' . $restaurant['id']) ?>
<div class="row">
    <div class="col-lg-8">
        <?= view('partials/translations', [
            'languages'    => $languages,
            'translations' => $translations,
            'fields'       => ['name' => 'text', 'description' => 'textarea'],
        ]) ?>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Genel bilgiler</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="slug">Slug</label>
                        <input type="text" class="form-control<?= $invalid('slug') ?>" id="slug" name="slug" maxlength="100"
                               value="<?= esc((string) old('slug', $restaurant['slug'], false), 'attr') ?>">
                        <?php if (isset($errors['slug'])): ?><div class="invalid-feedback"><?= esc($errors['slug']) ?></div><?php endif ?>
                        <div class="form-text">Menü adresinde kullanılır; küçük harf, rakam ve tire. Boş bırakılırsa mevcut slug korunur.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="currency_code">Para birimi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase<?= $invalid('currency_code') ?>" id="currency_code" name="currency_code" maxlength="3" required
                               placeholder="TRY" value="<?= esc((string) old('currency_code', $restaurant['currency_code'], false), 'attr') ?>">
                        <?php if (isset($errors['currency_code'])): ?><div class="invalid-feedback"><?= esc($errors['currency_code']) ?></div><?php endif ?>
                        <div class="form-text">3 harfli ISO kodu (TRY, EUR, USD…).</div>
                    </div>
                    <div class="col-md-6 mb-0">
                        <label class="form-label" for="default_language_id">Varsayılan dil <span class="text-danger">*</span></label>
                        <select class="form-select<?= $invalid('default_language_id') ?>" id="default_language_id" name="default_language_id" required>
                            <?php foreach ($languages as $language): ?>
                                <option value="<?= esc((string) $language['id'], 'attr') ?>"<?= $default === (string) $language['id'] ? ' selected' : '' ?>><?= esc($language['native_name']) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?php if (isset($errors['default_language_id'])): ?><div class="invalid-feedback"><?= esc($errors['default_language_id']) ?></div><?php endif ?>
                        <div class="form-text">Dil listesini <a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/languages') ?>">Diller</a> sayfasından yönetin.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Yayın</h5></div>
            <div class="card-body">
                <div class="form-check form-switch form-switch-success mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
                <p class="text-muted small mb-0">Pasif restoranın kullanıcıları giriş yapamaz, menüleri yayından kalkar.</p>

                <hr class="my-4">

                <h6 class="fs-14 mb-3">Logo</h6>
                <div class="text-center mb-3">
                    <img id="logo-preview" src="<?= esc($logo_url ?? '', 'attr') ?>" alt="Logo" class="img-thumbnail<?= $logo_url === null ? ' d-none' : '' ?>" style="max-height:160px">
                    <?php if ($logo_url === null): ?>
                        <div id="logo-placeholder" class="avatar-lg mx-auto">
                            <div class="avatar-title bg-light text-muted rounded fs-1"><i class="ri-image-line"></i></div>
                        </div>
                    <?php endif ?>
                </div>
                <label class="form-label" for="logo">Yeni logo yükle</label>
                <input class="form-control<?= $invalid('logo') ?>" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp" data-preview="#logo-preview">
                <?php if (isset($errors['logo'])): ?><div class="invalid-feedback"><?= esc($errors['logo']) ?></div><?php endif ?>
                <div class="form-text">jpg, png, webp — en fazla 2 MB.</div>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <a href="<?= $showUrl ?>" class="btn btn-light w-100">Vazgeç</a>
                    <button type="submit" class="btn btn-success w-100"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?= form_close() ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
        var img = document.querySelector(input.dataset.preview);
        if (!img || !input.files[0]) return;
        img.src = URL.createObjectURL(input.files[0]);
        img.classList.remove('d-none');
        var placeholder = document.getElementById('logo-placeholder');
        if (placeholder) placeholder.remove();
    });
});
</script>
<?= $this->endSection() ?>
