<?= $this->extend('layouts/admin') ?>
<?php
$heading  = $plan === null ? 'Yeni paket' : 'Paketi düzenle';
$indexUrl = site_url('admin/plans');
$errors   = session('errors') ?? [];
$hasOld   = old('translations', null, false) !== null;
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
$value    = static fn (string $field, $default = '') => esc((string) old($field, $default, false), 'attr');
$checked  = static function (string $field, int $default) use ($hasOld, $plan): string {
    $on = $hasOld ? old($field, null, false) === '1' : (int) ($plan[$field] ?? $default) === 1;

    return $on ? ' checked' : '';
};
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Paketler', 'url' => $indexUrl],
    ['label' => $heading],
]) ?>
<?= $this->section('content') ?>
<?= form_open($action) ?>
<div class="row">
    <div class="col-lg-8">
        <?= view('partials/translations', [
            'languages'    => $languages,
            'translations' => $translations,
            'fields'       => ['name' => 'text', 'description' => 'textarea'],
        ]) ?>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Fiyat ve limitler</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="price_monthly">Aylık fiyat <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control<?= $invalid('price_monthly') ?>" id="price_monthly" name="price_monthly" required
                               value="<?= $value('price_monthly', $plan['price_monthly'] ?? '0.00') ?>">
                        <?= $feedback('price_monthly') ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="price_yearly">Yıllık fiyat <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control<?= $invalid('price_yearly') ?>" id="price_yearly" name="price_yearly" required
                               value="<?= $value('price_yearly', $plan['price_yearly'] ?? '0.00') ?>">
                        <?= $feedback('price_yearly') ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="currency_code">Para birimi <span class="text-danger">*</span></label>
                        <?php $selCur = (string) old('currency_code', $plan['currency_code'] ?? 'TRY', false) ?>
                        <select class="form-select<?= $invalid('currency_code') ?>" id="currency_code" name="currency_code" required>
                            <?php foreach ($currencies as $c): ?>
                                <option value="<?= esc($c['code'], 'attr') ?>"<?= $selCur === $c['code'] ? ' selected' : '' ?>><?= esc($c['code'] . ' (' . $c['symbol'] . ')') ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= $feedback('currency_code') ?>
                    </div>
                </div>
                <p class="text-muted small">Fiyatlar KDV hariçtir (KDV oranı: %<?= esc(pix_money(config('Billing')->taxRate)) ?>). Fiyat değişikliği mevcut abonelikleri etkilemez; yeni atamalarda geçerli olur.</p>
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label" for="max_branches">Şube limiti</label>
                        <input type="number" min="1" max="9999" class="form-control<?= $invalid('max_branches') ?>" id="max_branches" name="max_branches" placeholder="Sınırsız"
                               value="<?= $value('max_branches', $plan['max_branches'] ?? '') ?>">
                        <?= $feedback('max_branches') ?>
                        <div class="form-text">Boş = sınırsız.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="trial_days">Deneme süresi (gün)</label>
                        <input type="number" min="1" max="9999" class="form-control<?= $invalid('trial_days') ?>" id="trial_days" name="trial_days" placeholder="Ücretli paket"
                               value="<?= $value('trial_days', $plan['trial_days'] ?? '') ?>">
                        <?= $feedback('trial_days') ?>
                        <div class="form-text">Doluysa deneme paketidir: fatura kesilmez, süre bitince restoran askıya alınır.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Genel bilgiler</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="code">Kod <span class="text-danger">*</span></label>
                    <input type="text" class="form-control font-monospace<?= $invalid('code') ?>" id="code" name="code" maxlength="30" required placeholder="ör. pro"
                           value="<?= $value('code', $plan['code'] ?? '') ?>">
                    <?= $feedback('code') ?>
                    <div class="form-text">Küçük harf, rakam ve alt çizgi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="badge_class">Rozet class'ı</label>
                    <input type="text" class="form-control font-monospace<?= $invalid('badge_class') ?>" id="badge_class" name="badge_class" maxlength="100" placeholder="ör. bg-primary"
                           value="<?= $value('badge_class', $plan['badge_class'] ?? '') ?>">
                    <?= $feedback('badge_class') ?>
                    <div class="form-text">Listelerde paket adı bu class ile rozet olarak gösterilir. Boş = <code>bg-primary</code>.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="sort_order">Sıra</label>
                    <input type="number" class="form-control<?= $invalid('sort_order') ?>" id="sort_order" name="sort_order" min="0" max="65535"
                           value="<?= $value('sort_order', $plan['sort_order'] ?? 0) ?>">
                    <?= $feedback('sort_order') ?>
                </div>

                <h6 class="fs-14 mt-4 mb-3">Paket hakları</h6>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="allow_custom_domain" name="allow_custom_domain" value="1"<?= $checked('allow_custom_domain', 0) ?>>
                    <label class="form-check-label" for="allow_custom_domain">Özel alan adı</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="allow_custom_design" name="allow_custom_design" value="1"<?= $checked('allow_custom_design', 0) ?>>
                    <label class="form-check-label" for="allow_custom_design">Özel menü tasarımı</label>
                </div>

                <h6 class="fs-14 mt-4 mb-3">Yayın</h6>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1"<?= $checked('is_featured', 0) ?>>
                    <label class="form-check-label" for="is_featured">"Popüler" olarak öne çıkar</label>
                </div>
                <div class="form-check form-switch form-switch-success mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $checked('is_active', 1) ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
                <p class="text-muted small mt-2 mb-0">Pasif paket yeni restoranlara atanamaz ve karşılaştırma sayfasında görünmez.</p>
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
