<?php
/**
 * Kullanıcı dropdown öğeleri (topbar + sidebar ortak).
 *
 * @var array{name?: string, email?: string} $user
 * @var string|null $profileUrl
 * @var string      $logoutAction
 */
?>
<h6 class="dropdown-header text-truncate"><?= esc($user['email'] ?? '') ?></h6>
<?php if (! empty($profileUrl)): ?>
    <a class="dropdown-item" href="<?= esc($profileUrl, 'attr') ?>"><i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Profil</span></a>
    <div class="dropdown-divider"></div>
<?php endif ?>
<?= form_open($logoutAction, ['class' => 'm-0']) ?>
    <button type="submit" class="dropdown-item"><i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> <span class="align-middle">Çıkış yap</span></button>
<?= form_close() ?>
