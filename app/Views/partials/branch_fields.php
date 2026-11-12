<?php
/**
 * Şube formu gövdesi (admin + panel ortak): iki kolonlu düzen + kaydet kartı.
 * Çağıran view yalnızca form_open/form_close ile sarar.
 *
 * @var array<string, mixed>|null                  $branch
 * @var list<array<string, mixed>>                 $languages    restoranın aktif dilleri (is_default = zorunlu dil)
 * @var array<string, array<string, string|null>>  $translations
 * @var string                                     $cancel_url   "Vazgeç" bağlantısı
 */
$errors   = session('errors') ?? [];
$hasOld   = old('translations', null, false) !== null;
$isActive = $hasOld ? old('is_active', null, false) === '1' : (int) ($branch['is_active'] ?? 1) === 1;
$default  = (string) old('default_language_id', $branch['default_language_id'] ?? '', false);
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
?>
<div class="row">
    <div class="col-lg-8">
        <?= view('partials/translations', [
            'languages'    => $languages,
            'translations' => $translations,
            'fields'       => ['name' => 'text', 'address' => 'text', 'description' => 'textarea'],
        ]) ?>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Genel bilgiler</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="slug">Slug</label>
                        <input type="text" class="form-control<?= $invalid('slug') ?>" id="slug" name="slug" maxlength="100"
                               value="<?= esc((string) old('slug', $branch['slug'] ?? '', false), 'attr') ?>">
                        <?= $feedback('slug') ?>
                        <div class="form-text">Küçük harf, rakam ve tire. Boş bırakılırsa addan üretilir.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="phone">Telefon</label>
                        <input type="text" class="form-control<?= $invalid('phone') ?>" id="phone" name="phone" maxlength="30" placeholder="0 (5xx) xxx xx xx"
                               value="<?= esc((string) old('phone', $branch['phone'] ?? '', false), 'attr') ?>">
                        <?= $feedback('phone') ?>
                    </div>
                    <div class="col-md-6 mb-0">
                        <label class="form-label" for="default_language_id">Menünün varsayılan dili <span class="text-danger">*</span></label>
                        <select class="form-select<?= $invalid('default_language_id') ?>" id="default_language_id" name="default_language_id" required>
                            <?php foreach ($languages as $language): ?>
                                <option value="<?= esc((string) $language['id'], 'attr') ?>"<?= $default === (string) $language['id'] ? ' selected' : '' ?>><?= esc($language['native_name']) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= $feedback('default_language_id') ?>
                        <div class="form-text">Ziyaretçi menüyü ilk açtığında bu dilde görür.</div>
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
                <p class="text-muted small mb-0">Pasif şubenin menü adresi ziyaretçilere açılmaz.</p>

                <hr class="my-4">

                <label class="form-label" for="sort_order">Sıra</label>
                <input type="number" class="form-control<?= $invalid('sort_order') ?>" id="sort_order" name="sort_order" min="0" max="65535"
                       value="<?= esc((string) old('sort_order', $branch['sort_order'] ?? 0, false), 'attr') ?>">
                <?= $feedback('sort_order') ?>
                <div class="form-text">Küçük sayı listede önce gelir.</div>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <a href="<?= esc($cancel_url, 'attr') ?>" class="btn btn-light w-100">Vazgeç</a>
                    <button type="submit" class="btn btn-success w-100"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</div>
