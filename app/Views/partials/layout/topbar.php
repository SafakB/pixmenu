<?php
/**
 * @var array{name?: string, email?: string} $user
 * @var string      $roleLabel
 * @var string      $homeUrl
 * @var string|null $profileUrl
 * @var string      $logoutAction
 */
?>
<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <?= view('partials/layout/logo', ['homeUrl' => $homeUrl, 'extraClass' => 'horizontal-logo']) ?>

                <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger material-shadow-none" id="topnav-hamburger-icon" aria-label="Menüyü aç/kapat">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
            </div>

            <div class="d-flex align-items-center">
                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle" data-toggle="fullscreen" aria-label="Tam ekran">
                        <i class="bx bx-fullscreen fs-22"></i>
                    </button>
                </div>

                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle light-dark-mode" aria-label="Açık/koyu mod">
                        <i class="bx bx-moon fs-22"></i>
                    </button>
                </div>

                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn material-shadow-none" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <img class="rounded-circle header-profile-user" src="<?= asset_url('images/users/user-dummy-img.jpg') ?>" alt="">
                            <span class="text-start ms-xl-2">
                                <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text"><?= esc($user['name'] ?? '') ?></span>
                                <span class="d-none d-xl-block ms-1 fs-12 user-name-sub-text"><?= esc($roleLabel) ?></span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <?= view('partials/layout/user_menu', ['user' => $user, 'profileUrl' => $profileUrl, 'logoutAction' => $logoutAction]) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
