<?= $this->extend('layouts/panel') ?>
<?php
$errors   = session('errors') ?? [];
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
?>
<?php $this->setVar('title', 'Profil') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Profil']]) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <div class="avatar-xs flex-shrink-0 me-2">
                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-16"><i class="ri-user-3-line"></i></span>
                </div>
                <h5 class="card-title mb-0 flex-grow-1">Profil bilgileri</h5>
            </div>
            <?= form_open('panel/profile') ?>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="name">Ad soyad <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="100" required
                           value="<?= esc((string) old('name', $user['name'], false), 'attr') ?>">
                    <?= $feedback('name') ?>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="email">E-posta <span class="text-danger">*</span></label>
                    <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email" maxlength="190" required autocomplete="email"
                           value="<?= esc((string) old('email', $user['email'], false), 'attr') ?>">
                    <?= $feedback('email') ?>
                    <div class="form-text">Giriş için kullanılır.</div>
                    <?php if ($googleLinked): ?>
                        <div class="form-text"><i class="ri-google-fill align-bottom text-danger me-1"></i>Hesabınız Google ile bağlı; "Google ile giriş yap" düğmesini kullanabilirsiniz.</div>
                    <?php endif ?>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <div class="avatar-xs flex-shrink-0 me-2">
                    <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-16"><i class="ri-lock-password-line"></i></span>
                </div>
                <h5 class="card-title mb-0 flex-grow-1"><?= $hasPassword ? 'Şifre değiştir' : 'Şifre belirle' ?></h5>
            </div>
            <?= form_open('panel/profile/password') ?>
            <div class="card-body">
                <?php if ($hasPassword): ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Mevcut şifre <span class="text-danger">*</span></label>
                        <input type="password" class="form-control<?= $invalid('current_password') ?>" id="current_password" name="current_password" required autocomplete="current-password">
                        <?= $feedback('current_password') ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-3" role="note">
                        Hesabınızın şifresi yok; Google ile giriş yapıyorsunuz. İsterseniz e-posta ve şifreyle de girebilmek için bir şifre belirleyin.
                    </div>
                <?php endif ?>
                <div class="mb-3">
                    <label class="form-label" for="password">Yeni şifre <span class="text-danger">*</span></label>
                    <input type="password" class="form-control<?= $invalid('password') ?>" id="password" name="password" required autocomplete="new-password">
                    <?= $feedback('password') ?>
                    <div class="form-text">En az 10 karakter, harf ve rakam içermeli.</div>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="password_confirm">Yeni şifre (tekrar) <span class="text-danger">*</span></label>
                    <input type="password" class="form-control<?= $invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                    <?= $feedback('password_confirm') ?>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary"><i class="ri-lock-line align-bottom me-1"></i> <?= $hasPassword ? 'Şifreyi değiştir' : 'Şifreyi kaydet' ?></button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
