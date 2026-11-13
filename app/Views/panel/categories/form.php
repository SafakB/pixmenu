<?= $this->extend('layouts/panel') ?>
<?php
$errors   = session('errors') ?? [];
$hasOld   = old('translations', null, false) !== null;
$isActive = $hasOld ? old('is_active', null, false) === '1' : (int) ($category['is_active'] ?? 1) === 1;
$indexUrl = site_url('panel/branches/' . $branch['id'] . '/categories');
$heading  = $category === null ? 'Yeni kategori' : 'Kategoriyi düzenle';
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Şubeler', 'url' => site_url('panel/branches')],
    ['label' => $branch['name']],
    ['label' => 'Kategoriler', 'url' => $indexUrl],
    ['label' => $category === null ? 'Yeni' : 'Düzenle'],
]) ?>
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
            <div class="card-header"><h5 class="card-title mb-0">Görsel</h5></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <img id="image-preview" src="<?= esc($image_url ?? '', 'attr') ?>" alt="" class="img-thumbnail<?= $image_url === null ? ' d-none' : '' ?>" style="max-height:160px">
                    <?php if ($image_url === null): ?>
                        <div id="image-placeholder" class="avatar-lg mx-auto">
                            <div class="avatar-title bg-light text-muted rounded fs-1"><i class="ri-image-line"></i></div>
                        </div>
                    <?php endif ?>
                </div>
                <label class="form-label" for="image"><?= $image_url === null ? 'Görsel yükle' : 'Yeni görsel yükle' ?></label>
                <input class="form-control<?= $invalid('image') ?>" type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"
                       data-preview="#image-preview" data-preview-placeholder="#image-placeholder">
                <?php if (isset($errors['image'])): ?><div class="invalid-feedback"><?= esc($errors['image']) ?></div><?php endif ?>
                <div class="form-text">jpg, png, webp — en fazla 3 MB.</div>
                <?php if ($image_url !== null): ?>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1"<?= old('remove_image', null, false) === '1' ? ' checked' : '' ?>>
                        <label class="form-check-label" for="remove_image">Görseli kaldır</label>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Yayın</h5></div>
            <div class="card-body">
                <div class="form-check form-switch form-switch-success mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
                <label class="form-label" for="sort_order">Sıra</label>
                <input type="number" class="form-control<?= $invalid('sort_order') ?>" id="sort_order" name="sort_order" min="0" max="65535"
                       value="<?= esc((string) old('sort_order', $category['sort_order'] ?? '', false), 'attr') ?>">
                <?php if (isset($errors['sort_order'])): ?><div class="invalid-feedback"><?= esc($errors['sort_order']) ?></div><?php endif ?>
                <div class="form-text">Boş bırakılırsa sona eklenir. Listede sürükleyerek de sıralayabilirsiniz.</div>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <a href="<?= $indexUrl ?>" class="btn btn-light w-100">Vazgeç</a>
                    <button type="submit" class="btn btn-success w-100"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?= form_close() ?>
<?= $this->endSection() ?>
