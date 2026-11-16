<?php
/**
 * @var string      $action    login POST yolu
 * @var string|null $error     429 gibi doğrudan render edilen hata
 * @var string|null $googleUrl "Google ile giriş yap" düğmesi (yalnızca panel; Google yapılandırılmışsa)
 * @var string|null $signupUrl "Kayıt olun" linki (yalnızca panel)
 */
?>
<?php if (! empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
<?php endif ?>
<?= view('partials/flash') ?>
<?= form_open($action) ?>
    <div class="mb-3">
        <label for="login-email" class="form-label">E-posta</label>
        <input type="email" class="form-control" id="login-email" name="email" value="<?= esc((string) old('email', null, false), 'attr') ?>" placeholder="ornek@alanadi.com" required autofocus autocomplete="username">
    </div>

    <div class="mb-3">
        <label class="form-label" for="password-input">Şifre</label>
        <div class="position-relative auth-pass-inputgroup">
            <input type="password" class="form-control pe-5 password-input" id="password-input" name="password" placeholder="Şifrenizi girin" required autocomplete="current-password">
            <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none" type="button" id="password-addon" aria-label="Şifreyi göster/gizle"><i class="ri-eye-fill align-middle"></i></button>
        </div>
    </div>

    <div class="mt-4">
        <button class="btn btn-success w-100" type="submit">Giriş yap</button>
    </div>
<?= form_close() ?>
<?php if (! empty($googleUrl)): ?>
    <div class="mt-4 text-center">
        <div class="signin-other-title">
            <h5 class="fs-13 mb-4 title">veya</h5>
        </div>
        <a href="<?= esc($googleUrl, 'attr') ?>" class="btn btn-light w-100 border">
            <i class="ri-google-fill fs-16 align-middle text-danger me-1"></i> Google ile giriş yap
        </a>
    </div>
<?php endif ?>
<?php if (! empty($signupUrl)): ?>
    <p class="mt-4 mb-0 text-center text-muted">Hesabınız yok mu? <a href="<?= esc($signupUrl, 'attr') ?>" class="fw-semibold text-primary text-decoration-underline">Ücretsiz kayıt olun</a></p>
<?php endif ?>
