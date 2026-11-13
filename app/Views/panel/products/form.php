<?= $this->extend('layouts/panel') ?>
<?php
$base           = 'panel/branches/' . $branch['id'];
$indexUrl       = site_url($base . '/products');
$errors         = session('errors') ?? [];
$hasOld         = old('translations', null, false) !== null;
$isActive       = $hasOld ? old('is_active', null, false) === '1' : (int) ($product['is_active'] ?? 1) === 1;
$categoryId     = (string) old('category_id', $product['category_id'] ?? '', false);
$oldAllergens   = old('allergens', null, false);
$allergenLevels = is_array($oldAllergens) ? $oldAllergens : $selectedAllergens;
$heading        = $product === null ? 'Yeni ürün' : 'Ürünü düzenle';
$invalid        = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback       = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
$canDeleteImage = $product !== null && $image_url !== null;
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Şubeler', 'url' => site_url('panel/branches')],
    ['label' => $branch['name']],
    ['label' => 'Ürünler', 'url' => $indexUrl],
    ['label' => $product === null ? 'Yeni' : 'Düzenle'],
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

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-1">Alerjenler</h5>
                <p class="text-muted small mb-0">Ürünün içerdiği veya eser miktarda içerebileceği alerjenleri işaretleyin.</p>
            </div>
            <div class="card-body">
                <?php if ($allergens === []): ?>
                    <p class="text-muted mb-0">Tanımlı alerjen yok.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($allergens as $allergen): ?>
                            <?php
                            $allergenId = (string) $allergen['id'];
                            $level      = (string) ($allergenLevels[$allergen['id']] ?? '');
                            $fieldId    = 'allergen-' . $allergenId;
                            ?>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-xs flex-shrink-0">
                                        <?php if ($allergen['icon_url'] !== null): ?>
                                            <img src="<?= esc($allergen['icon_url'], 'attr') ?>" alt="" class="img-fluid avatar-xs object-fit-contain">
                                        <?php else: ?>
                                            <span class="avatar-title bg-light text-muted rounded fs-14"><i class="ri-leaf-line"></i></span>
                                        <?php endif ?>
                                    </div>
                                    <label class="form-label mb-0 flex-grow-1 text-truncate" for="<?= esc($fieldId, 'attr') ?>"><?= esc($allergen['name']) ?></label>
                                    <select class="form-select form-select-sm w-auto<?= $level !== '' ? ' border-warning' : '' ?>" id="<?= esc($fieldId, 'attr') ?>" name="allergens[<?= esc($allergenId, 'attr') ?>]">
                                        <option value="">—</option>
                                        <option value="contains"<?= $level === 'contains' ? ' selected' : '' ?>>İçerir</option>
                                        <option value="may_contain"<?= $level === 'may_contain' ? ' selected' : '' ?>>Eser miktarda içerebilir</option>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Genel bilgiler</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="category_id">Kategori <span class="text-danger">*</span></label>
                    <select class="form-select<?= $invalid('category_id') ?>" id="category_id" name="category_id" required>
                        <option value="">Seçin</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= esc((string) $category['id'], 'attr') ?>"<?= $categoryId === (string) $category['id'] ? ' selected' : '' ?>><?= esc($category['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?= $feedback('category_id') ?>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="price">Fiyat <span class="text-danger">*</span></label>
                    <div class="input-group<?= isset($errors['price']) ? ' has-validation' : '' ?>">
                        <input type="text" class="form-control<?= $invalid('price') ?>" id="price" name="price" inputmode="decimal" required placeholder="0,00"
                               value="<?= esc((string) old('price', $product['price'] ?? '', false), 'attr') ?>">
                        <span class="input-group-text"><?= esc($currency) ?></span>
                        <?= $feedback('price') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Görsel</h5></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <img id="image-preview" src="<?= esc($image_url ?? '', 'attr') ?>" alt="" class="img-thumbnail<?= $image_url === null ? ' d-none' : '' ?>" style="max-height:180px">
                    <?php if ($image_url === null): ?>
                        <div id="image-placeholder" class="avatar-lg mx-auto">
                            <div class="avatar-title bg-light text-muted rounded fs-1"><i class="ri-image-line"></i></div>
                        </div>
                    <?php endif ?>
                </div>
                <label class="form-label" for="image"><?= $image_url === null ? 'Görsel yükle' : 'Yeni görsel yükle' ?></label>
                <input class="form-control<?= $invalid('image') ?>" type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"
                       data-preview="#image-preview" data-preview-placeholder="#image-placeholder">
                <?= $feedback('image') ?>
                <div class="form-text">jpg, png, webp — en fazla 5 MB.</div>
                <?php if ($canDeleteImage): ?>
                    <button type="submit" form="image-delete-form" class="btn btn-soft-danger btn-sm mt-3"><i class="ri-delete-bin-line align-bottom me-1"></i> Görseli sil</button>
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
                       value="<?= esc((string) old('sort_order', $product['sort_order'] ?? '', false), 'attr') ?>">
                <?= $feedback('sort_order') ?>
                <div class="form-text">Boş bırakılırsa kategorinin sonuna eklenir.</div>
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

<?php if ($canDeleteImage): ?>
    <?= form_open($base . '/products/' . $product['id'] . '/image/delete', [
        'id'                  => 'image-delete-form',
        'class'               => 'd-none',
        'data-confirm'        => 'Ürün görseli silinecek. Bu işlem geri alınamaz.',
        'data-confirm-button' => 'Evet, sil',
    ]) ?>
    <?= form_close() ?>
<?php endif ?>
<?= $this->endSection() ?>
