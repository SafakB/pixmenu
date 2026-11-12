<?= $this->extend('layouts/admin') ?>
<?php
$heading  = $allergen === null ? 'Yeni alerjen' : 'Alerjeni düzenle';
$indexUrl = site_url('admin/allergens');
$errors   = session('errors') ?? [];
$hasOld   = old('translations', null, false) !== null;
$isActive = $hasOld ? old('is_active', null, false) === '1' : (int) ($allergen['is_active'] ?? 1) === 1;
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
$canDeleteIcon = $allergen !== null && $icon_url !== null;
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Alerjenler', 'url' => $indexUrl],
    ['label' => $heading],
]) ?>
<?= $this->section('css') ?>
<style>
    .allergen-icon-preview { width: 72px; height: 72px; }
    .allergen-icon-preview img { width: 60%; height: 60%; object-fit: contain; }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= form_open_multipart($action) ?>
<div class="row">
    <div class="col-lg-8">
        <?= view('partials/translations', [
            'languages'    => $languages,
            'translations' => $translations,
            'fields'       => ['name' => 'text', 'description' => 'textarea'],
        ]) ?>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Genel bilgiler</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="code">Kod <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= $invalid('code') ?>" id="code" name="code" maxlength="40" required placeholder="ör. gluten, milk"
                           value="<?= esc((string) old('code', $allergen['code'] ?? '', false), 'attr') ?>">
                    <?= $feedback('code') ?>
                    <div class="form-text">Sistem içi tanımlayıcı; ziyaretçiye çeviri adı gösterilir.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="sort_order">Sıra</label>
                    <input type="number" class="form-control<?= $invalid('sort_order') ?>" id="sort_order" name="sort_order" min="0" max="65535"
                           value="<?= esc((string) old('sort_order', $allergen['sort_order'] ?? 0, false), 'attr') ?>">
                    <?= $feedback('sort_order') ?>
                    <div class="form-text">Küçük sayı listede önce gelir.</div>
                </div>

                <div class="form-check form-switch form-switch-success mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>

                <hr class="my-4">

                <label class="form-label" for="icon">İkon</label>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="allergen-icon-preview flex-shrink-0">
                        <div class="avatar-title bg-light rounded">
                            <img id="icon-preview" src="<?= esc($icon_url ?? '', 'attr') ?>" alt="" class="<?= $icon_url === null ? 'd-none' : '' ?>">
                            <i id="icon-placeholder" class="ri-image-line fs-1 text-muted<?= $icon_url !== null ? ' d-none' : '' ?>"></i>
                        </div>
                    </div>
                    <?php if ($canDeleteIcon): ?>
                        <button type="submit" form="icon-delete-form" class="btn btn-soft-danger btn-sm"><i class="ri-delete-bin-line align-bottom me-1"></i> İkonu sil</button>
                    <?php endif ?>
                </div>
                <input class="form-control<?= $invalid('icon') ?>" type="file" id="icon" name="icon" accept=".svg,.png,.webp">
                <?= $feedback('icon') ?>
                <div class="form-text">svg, png veya webp — en fazla 512 KB.</div>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <a href="<?= esc($indexUrl, 'attr') ?>" class="btn btn-light w-100">Vazgeç</a>
                    <button type="submit" class="btn btn-success w-100"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?= form_close() ?>
<?php if ($canDeleteIcon): ?>
    <?= form_open('admin/allergens/' . $allergen['id'] . '/icon/delete', [
        'id'                  => 'icon-delete-form',
        'class'               => 'd-none',
        'data-confirm'        => 'Alerjenin ikonu silinecek.',
        'data-confirm-button' => 'Evet, sil',
    ]) ?>
    <?= form_close() ?>
<?php endif ?>
<?= $this->endSection() ?>
<?= $this->section('js') ?>
<script>
document.getElementById('icon').addEventListener('change', function () {
    if (!this.files[0]) return;
    var img = document.getElementById('icon-preview');
    img.src = URL.createObjectURL(this.files[0]);
    img.classList.remove('d-none');
    document.getElementById('icon-placeholder').classList.add('d-none');
});
</script>
<?= $this->endSection() ?>
