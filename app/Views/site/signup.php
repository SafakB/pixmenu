<?= $this->extend('layouts/auth') ?>
<?php
/**
 * Self-servis kayıt sihirbazı (Velzon forms-wizard step-arrow-nav + auth-signup-basic).
 * Değişkenler: SignupController::new docblock'u. Tek form, tek POST; adımlar istemci tarafındadır.
 * Sunucu hatasında ilk hatalı alanın adımı açılır (data-start-step).
 */
$this->setVar('title', 'Kayıt ol');
$this->setVar('authColClass', 'col-lg-10 col-xl-9');

$errors   = session('errors') ?? [];
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . esc($errors[$field]) . '</div>' : '';
$value    = static fn (string $field, string $default = ''): string => esc((string) old($field, $default, false), 'attr');

$stepFields = [
    1 => ['name', 'email', 'password', 'password_confirm', 'terms'],
    2 => ['restaurant_name', 'branch_name', 'phone', 'address', 'currency_code', 'language_ids'],
    3 => ['plan_id', 'billing_cycle'],
];
$startStep = 1;

foreach ([3, 2, 1] as $step) {
    if (array_intersect(array_keys($errors), $stepFields[$step]) !== []) {
        $startStep = $step;
    }
}

$oldLanguages = array_map('intval', (array) (old('language_ids', [], false) ?? []));
$oldCurrency  = (string) old('currency_code', 'TRY', false);
$selectedPlan = (int) old('plan_id', (string) ($preselect['plan_id'] ?? ''), false);
$cycle        = (string) old('billing_cycle', $preselect['billing_cycle'], false) === 'yearly' ? 'yearly' : 'monthly';
$planCol      = 'col-md-6';
?>
<?= $this->section('heading') ?>Ücretsiz hesabınızı oluşturun<?= $this->endSection() ?>
<?= $this->section('subtitle') ?>Dijital menünüz birkaç dakika içinde hazır. Zaten hesabınız var mı? <a href="<?= esc($loginUrl, 'attr') ?>" class="fw-semibold text-primary text-decoration-underline">Giriş yapın</a><?= $this->endSection() ?>

<?= $this->section('css') ?>
<style>
    #signup-form .step-arrow-nav .nav-link { cursor: default; }
    #signup-form .step-arrow-nav .nav-link.done { cursor: pointer; }
    #signup-form .pricing-box.is-selected { border: 1px solid var(--vz-primary) !important; box-shadow: 0 0 0 .2rem rgba(var(--vz-primary-rgb), .15); }
    #signup-form .lang-choice .form-check-input:disabled ~ .form-check-label { opacity: 1; }
    #signup-form .btn-check + .btn .when-on,
    #signup-form .btn-check:checked + .btn .when-off { display: none; }
    #signup-form .btn-check:checked + .btn .when-on { display: inline; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
<?php endif ?>
<?= view('partials/flash') ?>

<?= form_open('kayit', ['id' => 'signup-form', 'class' => 'form-steps', 'data-start-step' => (string) $startStep, 'autocomplete' => 'on']) ?>
    <?php if ($google !== null): ?>
        <input type="hidden" name="via" value="google">
    <?php endif ?>

    <div class="step-arrow-nav mb-4">
        <ul class="nav nav-pills custom-nav nav-justified" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-step-tab="1" role="tab">1. Hesap</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-step-tab="2" role="tab">2. İşletme</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-step-tab="3" role="tab">3. Paket</button></li>
        </ul>
    </div>

    <!-- Adım 1: Hesap -->
    <div class="signup-step" data-step="1" role="tabpanel">
        <?php if ($google !== null): ?>
            <div class="alert alert-light border d-flex align-items-center gap-3 mb-4" role="status">
                <i class="ri-google-fill fs-24 text-danger"></i>
                <div class="flex-grow-1">
                    <p class="mb-0 fw-medium">Google hesabınızla kaydoluyorsunuz</p>
                    <p class="mb-0 text-muted"><?= esc($google['email']) ?> — şifre gerekmez; girişte "Google ile giriş yap" düğmesini kullanacaksınız.</p>
                </div>
                <a href="<?= site_url('kayit?google=0') ?>" class="btn btn-sm btn-link text-nowrap">Farklı yöntem</a>
            </div>
            <div class="mb-3">
                <label class="form-label" for="name">Ad soyad <span class="text-danger">*</span></label>
                <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="100" required autocomplete="name"
                       value="<?= $value('name', $google['name']) ?>">
                <?= $feedback('name') ?>
            </div>
            <?= $feedback('email') ?>
        <?php else: ?>
            <?php if ($googleUrl !== null): ?>
                <a href="<?= esc($googleUrl, 'attr') ?>" class="btn btn-light border w-100 mb-3">
                    <i class="ri-google-fill fs-16 align-middle text-danger me-1"></i> Google ile devam et
                </a>
                <div class="signin-other-title text-center mb-3">
                    <h5 class="fs-13 mb-0 title">veya e-posta ile</h5>
                </div>
            <?php endif ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Ad soyad <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="100" required autocomplete="name"
                           placeholder="Adınız ve soyadınız" value="<?= $value('name') ?>">
                    <?= $feedback('name') ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="email">E-posta <span class="text-danger">*</span></label>
                    <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email" maxlength="190" required autocomplete="email"
                           placeholder="ornek@restoraniniz.com" value="<?= $value('email') ?>">
                    <?= $feedback('email') ?>
                    <div class="form-text">Panele giriş için kullanılır.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="password">Şifre <span class="text-danger">*</span></label>
                    <div class="position-relative auth-pass-inputgroup">
                        <input type="password" class="form-control pe-5 password-input<?= $invalid('password') ?>" id="password" name="password" required
                               minlength="10" autocomplete="new-password" placeholder="En az 10 karakter">
                        <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none" type="button" aria-label="Şifreyi göster/gizle"><i class="ri-eye-fill align-middle"></i></button>
                    </div>
                    <?= $feedback('password') ?>
                    <div class="form-text">En az 10 karakter; harf ve rakam içermeli.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="password_confirm">Şifre (tekrar) <span class="text-danger">*</span></label>
                    <div class="position-relative auth-pass-inputgroup">
                        <input type="password" class="form-control pe-5 password-input<?= $invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" required
                               minlength="10" autocomplete="new-password">
                        <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none" type="button" aria-label="Şifreyi göster/gizle"><i class="ri-eye-fill align-middle"></i></button>
                    </div>
                    <?= $feedback('password_confirm') ?>
                </div>
            </div>
        <?php endif ?>

        <div class="form-check mb-1">
            <input class="form-check-input<?= $invalid('terms') ?>" type="checkbox" id="terms" name="terms" value="1" required<?= old('terms', null, false) === '1' ? ' checked' : '' ?>>
            <label class="form-check-label" for="terms">
                <a href="<?= site_url('kullanim-kosullari') ?>" target="_blank" rel="noopener">Kullanım koşullarını</a> ve
                <a href="<?= site_url('kvkk') ?>" target="_blank" rel="noopener">KVKK aydınlatma metnini</a> okudum, kabul ediyorum.
            </label>
            <?= $feedback('terms') ?>
        </div>

        <div class="d-flex align-items-start gap-3 mt-4">
            <button type="button" class="btn btn-success btn-label right ms-auto" data-step-next><i class="ri-arrow-right-line label-icon align-middle fs-16 ms-2"></i>İşletme bilgileri</button>
        </div>
    </div>

    <!-- Adım 2: İşletme -->
    <div class="signup-step d-none" data-step="2" role="tabpanel">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="restaurant_name">Restoran / işletme adı <span class="text-danger">*</span></label>
                <input type="text" class="form-control<?= $invalid('restaurant_name') ?>" id="restaurant_name" name="restaurant_name" maxlength="150" required
                       autocomplete="organization" placeholder="Ör. Lezzet Durağı" value="<?= $value('restaurant_name') ?>">
                <?= $feedback('restaurant_name') ?>
                <div class="form-text">Menü adresiniz bu addan oluşturulur.</div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="branch_name">İlk şube adı <span class="text-danger">*</span></label>
                <input type="text" class="form-control<?= $invalid('branch_name') ?>" id="branch_name" name="branch_name" maxlength="150" required
                       value="<?= $value('branch_name', 'Merkez') ?>">
                <?= $feedback('branch_name') ?>
                <div class="form-text">Tek şubeniz varsa "Merkez" olarak bırakabilirsiniz.</div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="phone">Şube telefonu</label>
                <input type="tel" class="form-control<?= $invalid('phone') ?>" id="phone" name="phone" maxlength="30" autocomplete="tel"
                       placeholder="0 (212) 000 00 00" value="<?= $value('phone') ?>">
                <?= $feedback('phone') ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="currency_code">Menü para birimi <span class="text-danger">*</span></label>
                <select class="form-select<?= $invalid('currency_code') ?>" id="currency_code" name="currency_code" required>
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?= esc((string) $c['code'], 'attr') ?>"<?= $c['code'] === $oldCurrency ? ' selected' : '' ?>>
                            <?= esc($c['code'] . ' — ' . $c['name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <?= $feedback('currency_code') ?>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label" for="address">Şube adresi</label>
                <textarea class="form-control<?= $invalid('address') ?>" id="address" name="address" rows="2" maxlength="500" autocomplete="street-address"
                          placeholder="Mahalle, cadde, ilçe / il"><?= esc((string) old('address', '', false)) ?></textarea>
                <?= $feedback('address') ?>
            </div>
            <div class="col-12 mb-1">
                <span class="form-label d-block">Menü dilleri</span>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($languages as $lang): ?>
                        <?php $isDefault = (int) $lang['id'] === (int) $defaultLanguageId ?>
                        <div class="form-check lang-choice">
                            <input class="form-check-input" type="checkbox" id="lang-<?= (int) $lang['id'] ?>"
                                   <?= $isDefault ? 'checked disabled' : 'name="language_ids[]" value="' . (int) $lang['id'] . '"' . (in_array((int) $lang['id'], $oldLanguages, true) ? ' checked' : '') ?>>
                            <label class="form-check-label" for="lang-<?= (int) $lang['id'] ?>">
                                <?= esc((string) ($lang['native_name'] ?: $lang['name'])) ?><?= $isDefault ? ' <span class="text-muted">(varsayılan)</span>' : '' ?>
                            </label>
                        </div>
                    <?php endforeach ?>
                </div>
                <?= $feedback('language_ids') ?>
                <div class="form-text"><i class="ri-information-line align-bottom"></i> Menü dilleri ve para birimi daha sonra yalnızca destek ekibimiz aracılığıyla değiştirilebilir.</div>
            </div>
        </div>

        <div class="d-flex align-items-start gap-3 mt-4">
            <button type="button" class="btn btn-light btn-label" data-step-prev><i class="ri-arrow-left-line label-icon align-middle fs-16 me-2"></i>Geri</button>
            <button type="button" class="btn btn-success btn-label right ms-auto" data-step-next><i class="ri-arrow-right-line label-icon align-middle fs-16 ms-2"></i>Paket seçimi</button>
        </div>
    </div>

    <!-- Adım 3: Paket -->
    <div class="signup-step d-none" data-step="3" role="tabpanel">
        <div class="text-center">
            <p class="text-muted mb-3">Fiyatlara KDV dahil değildir (%<?= esc(pix_money($taxRate)) ?>). Paketinizi daha sonra panelden yükseltebilirsiniz.</p>
            <ul class="nav nav-pills arrow-navtabs plan-nav rounded mb-3 p-1 d-inline-flex" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold<?= $cycle === 'monthly' ? ' active' : '' ?>" type="button" data-cycle="monthly" aria-selected="<?= $cycle === 'monthly' ? 'true' : 'false' ?>">Aylık</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold<?= $cycle === 'yearly' ? ' active' : '' ?>" type="button" data-cycle="yearly" aria-selected="<?= $cycle === 'yearly' ? 'true' : 'false' ?>">Yıllık</button>
                </li>
            </ul>
            <input type="hidden" name="billing_cycle" value="<?= esc($cycle, 'attr') ?>" data-cycle-input>
        </div>
        <?= $feedback('plan_id') ?>
        <?= $feedback('billing_cycle') ?>
        <div class="row g-3 mt-1">
            <?php foreach ($plans as $p): ?>
                <?php
                $id      = (int) $p['id'];
                $checked = $id === $selectedPlan;
                $footer  = '<input type="radio" class="btn-check" name="plan_id" id="plan-' . $id . '" value="' . $id . '" required'
                    . ' data-paid="' . ($p['trial_days'] === null ? '1' : '0') . '"' . ($checked ? ' checked' : '') . '>'
                    . '<label class="btn btn-outline-primary w-100" for="plan-' . $id . '">'
                    . '<span class="when-off">Bu paketi seç</span><span class="when-on"><i class="ri-check-line align-bottom me-1"></i>Seçildi</span></label>';
                ?>
                <div class="<?= $planCol ?>">
                    <?= view('partials/billing/plan_card', ['plan' => $p, 'startYearly' => $cycle === 'yearly', 'isCurrent' => false, 'footer' => $footer]) ?>
                </div>
            <?php endforeach ?>
        </div>
        <p class="text-muted fs-13 mt-3 mb-0" data-paid-note>
            <i class="ri-bank-card-line align-bottom me-1"></i>
            Ücretli paketlerde ilk dönem faturanız oluşturulur ve bir sonraki adımda kart ile ödeme ekranına yönlendirilirsiniz.
        </p>

        <div class="d-flex align-items-start gap-3 mt-4">
            <button type="button" class="btn btn-light btn-label" data-step-prev><i class="ri-arrow-left-line label-icon align-middle fs-16 me-2"></i>Geri</button>
            <button type="submit" class="btn btn-success btn-label right ms-auto" data-submit
                    data-label-trial="Hesabı oluştur" data-label-paid="Hesabı oluştur ve ödemeye geç">
                <i class="ri-check-double-line label-icon align-middle fs-16 ms-2"></i><span data-submit-label>Hesabı oluştur</span>
            </button>
        </div>
    </div>
<?= form_close() ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script src="<?= asset_url('js/pix-plan-cycle.js') ?>"></script>
<script src="<?= asset_url('js/pages/signup.init.js') ?>"></script>
<?= $this->endSection() ?>
