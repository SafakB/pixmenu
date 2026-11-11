<?php
/**
 * @var string      $action login POST yolu
 * @var string|null $error  429 gibi doğrudan render edilen hata
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
