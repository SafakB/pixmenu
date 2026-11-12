<?= $this->extend('layouts/admin') ?>
<?php
$errors         = session('errors') ?? [];
$hasOld         = old('translations', null, false) !== null;
$isActive       = $hasOld ? old('is_active', null, false) === '1' : true;
$oldLanguageIds = array_map('strval', (array) (old('language_ids', null, false) ?? []));
$oldDefault     = (string) old('default_language_id', null, false);
$indexUrl       = site_url('admin/restaurants');
$invalid        = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$languageError  = $errors['language_ids'] ?? $errors['default_language_id'] ?? null;
?>
<?php $this->setVar('title', 'Yeni restoran') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Restoranlar', 'url' => $indexUrl],
    ['label' => 'Yeni restoran'],
]) ?>
<?= $this->section('content') ?>
<?= form_open_multipart('admin/restaurants') ?>
<div class="row">
    <div class="col-lg-8">
        <?= view('partials/translations', [
            'languages'    => $languages,
            'translations' => [],
            'fields'       => ['name' => 'text', 'description' => 'textarea'],
        ]) ?>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Genel bilgiler</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-0">
                        <label class="form-label" for="slug">Slug</label>
                        <input type="text" class="form-control<?= $invalid('slug') ?>" id="slug" name="slug" maxlength="100"
                               value="<?= esc((string) old('slug', null, false), 'attr') ?>">
                        <?php if (isset($errors['slug'])): ?><div class="invalid-feedback"><?= esc($errors['slug']) ?></div><?php endif ?>
                        <div class="form-text">Menü adresinde kullanılır; küçük harf, rakam ve tire. Boş bırakılırsa addan üretilir.</div>
                    </div>
                    <div class="col-md-6 mb-0">
                        <label class="form-label" for="currency_code">Para birimi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase<?= $invalid('currency_code') ?>" id="currency_code" name="currency_code" maxlength="3" required
                               placeholder="TRY" value="<?= esc((string) old('currency_code', 'TRY', false), 'attr') ?>">
                        <?php if (isset($errors['currency_code'])): ?><div class="invalid-feedback"><?= esc($errors['currency_code']) ?></div><?php endif ?>
                        <div class="form-text">3 harfli ISO kodu (TRY, EUR, USD…).</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Diller <span class="text-danger">*</span></h5></div>
            <div class="card-body">
                <p class="text-muted small">Restoranın kullanacağı dilleri işaretleyin (en az bir) ve birini varsayılan seçin.</p>
                <?php if ($languageError !== null): ?><div class="alert alert-danger py-2"><?= esc($languageError) ?></div><?php endif ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Dil</th>
                                <th class="text-center" style="width:110px">Kullan</th>
                                <th class="text-center" style="width:110px">Varsayılan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allLanguages as $language): ?>
                                <?php $id = (string) $language['id']; ?>
                                <tr>
                                    <td><?= esc($language['native_name']) ?> <span class="text-muted text-uppercase small">(<?= esc($language['code']) ?>)</span></td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="language_ids[]" id="lang-use-<?= esc($id, 'attr') ?>" value="<?= esc($id, 'attr') ?>"<?= in_array($id, $oldLanguageIds, true) ? ' checked' : '' ?> aria-label="<?= esc($language['native_name'], 'attr') ?> kullan">
                                    </td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="radio" name="default_language_id" value="<?= esc($id, 'attr') ?>" data-use="#lang-use-<?= esc($id, 'attr') ?>"<?= $oldDefault === $id ? ' checked' : '' ?> required aria-label="<?= esc($language['native_name'], 'attr') ?> varsayılan">
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">İlk sahip (owner)</h5></div>
            <div class="card-body">
                <p class="text-muted small">İsteğe bağlı ama önerilir; restoranın ilk yönetici hesabı oluşturulur.</p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="owner_name">Ad</label>
                        <input type="text" class="form-control<?= $invalid('owner_name') ?>" id="owner_name" name="owner_name" maxlength="100"
                               value="<?= esc((string) old('owner_name', null, false), 'attr') ?>">
                        <?php if (isset($errors['owner_name'])): ?><div class="invalid-feedback"><?= esc($errors['owner_name']) ?></div><?php endif ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="owner_email">E-posta</label>
                        <input type="email" class="form-control<?= $invalid('owner_email') ?>" id="owner_email" name="owner_email" maxlength="190"
                               value="<?= esc((string) old('owner_email', null, false), 'attr') ?>">
                        <?php if (isset($errors['owner_email'])): ?><div class="invalid-feedback"><?= esc($errors['owner_email']) ?></div><?php endif ?>
                    </div>
                    <div class="col-md-6 mb-0">
                        <label class="form-label" for="owner_password">Şifre</label>
                        <input type="password" class="form-control<?= $invalid('owner_password') ?>" id="owner_password" name="owner_password" autocomplete="new-password">
                        <?php if (isset($errors['owner_password'])): ?><div class="invalid-feedback"><?= esc($errors['owner_password']) ?></div><?php endif ?>
                        <div class="form-text">En az 10 karakter, harf ve rakam içermeli.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php
        $oldPlan  = (string) old('plan_id', (string) ($defaultPlanId ?? ''), false);
        $oldCycle = (string) old('billing_cycle', 'monthly', false);
        ?>
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Paket</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="plan_id">Paket <span class="text-danger">*</span></label>
                    <select class="form-select<?= $invalid('plan_id') ?>" id="plan_id" name="plan_id" required>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= esc((string) $plan['id'], 'attr') ?>" data-trial="<?= $plan['trial_days'] !== null ? '1' : '0' ?>"<?= $oldPlan === (string) $plan['id'] ? ' selected' : '' ?>>
                                <?= esc($plan['name']) ?><?= $plan['trial_days'] !== null
                                    ? ' — ' . esc((string) $plan['trial_days']) . ' gün deneme'
                                    : ' — ' . esc(pix_money($plan['price_monthly'], $plan['currency_code'])) . ' / ay' ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if (isset($errors['plan_id'])): ?><div class="invalid-feedback"><?= esc($errors['plan_id']) ?></div><?php endif ?>
                </div>
                <div id="billing-cycle-group">
                    <label class="form-label d-block">Ödeme dönemi</label>
                    <div class="btn-group w-100" role="group" aria-label="Ödeme dönemi">
                        <input type="radio" class="btn-check" name="billing_cycle" id="cycle-monthly" value="monthly"<?= $oldCycle !== 'yearly' ? ' checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="cycle-monthly">Aylık</label>
                        <input type="radio" class="btn-check" name="billing_cycle" id="cycle-yearly" value="yearly"<?= $oldCycle === 'yearly' ? ' checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="cycle-yearly">Yıllık</label>
                    </div>
                    <?php if (isset($errors['billing_cycle'])): ?><div class="text-danger small mt-1"><?= esc($errors['billing_cycle']) ?></div><?php endif ?>
                    <div class="form-text">Ücretli pakette ilk dönemin faturası hemen kesilir.</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Yayın</h5></div>
            <div class="card-body">
                <div class="form-check form-switch form-switch-success mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
                <p class="text-muted small mb-0">Pasif restoranın kullanıcıları giriş yapamaz, menüleri yayında olmaz.</p>

                <hr class="my-4">

                <h6 class="fs-14 mb-3">Logo</h6>
                <div class="text-center mb-3">
                    <img id="logo-preview" src="" alt="Logo" class="img-thumbnail d-none" style="max-height:160px">
                    <div id="logo-placeholder" class="avatar-lg mx-auto">
                        <div class="avatar-title bg-light text-muted rounded fs-1"><i class="ri-image-line"></i></div>
                    </div>
                </div>
                <label class="form-label" for="logo">Logo yükle</label>
                <input class="form-control<?= $invalid('logo') ?>" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp" data-preview="#logo-preview">
                <?php if (isset($errors['logo'])): ?><div class="invalid-feedback"><?= esc($errors['logo']) ?></div><?php endif ?>
                <div class="form-text">jpg, png, webp — en fazla 2 MB.</div>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <a href="<?= $indexUrl ?>" class="btn btn-light w-100">Vazgeç</a>
                    <button type="submit" class="btn btn-success w-100"><i class="ri-add-line align-bottom me-1"></i> Oluştur</button>
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
// Deneme paketinde ödeme dönemi seçimi gizlenir.
(function () {
    var select = document.getElementById('plan_id');
    var group  = document.getElementById('billing-cycle-group');
    if (!select || !group) return;
    var sync = function () {
        var opt = select.options[select.selectedIndex];
        group.classList.toggle('d-none', !!opt && opt.dataset.trial === '1');
    };
    select.addEventListener('change', sync);
    sync();
})();
// Varsayılan seçilen dil kullanılmıyorsa otomatik işaretle.
document.querySelectorAll('input[name=default_language_id][data-use]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        var use = document.querySelector(radio.dataset.use);
        if (radio.checked && use) use.checked = true;
    });
});
</script>
<?= $this->endSection() ?>
