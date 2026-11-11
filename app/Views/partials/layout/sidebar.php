<?php
/**
 * Kenar menü. Menü öğeleri:
 *   ['title' => 'Başlık']                                   → bölüm başlığı
 *   ['label' => '', 'path' => 'admin/x', 'icon' => 'ri-…', 'match' => ['admin/x*']]
 *   match: url_is() desenleri; verilmezse path'in kendisi.
 *
 * @var list<array<string, mixed>>               $menu
 * @var array{name?: string, email?: string}     $user
 * @var string                                   $roleLabel
 * @var string                                   $homeUrl
 * @var string|null                              $profileUrl
 * @var string                                   $logoutAction
 */
$isActive = static function (array $item): bool {
    foreach ($item['match'] ?? [$item['path']] as $pattern) {
        if (url_is($pattern)) {
            return true;
        }
    }

    return false;
};
?>
<div class="app-menu navbar-menu">
    <?= view('partials/layout/logo', ['homeUrl' => $homeUrl, 'extraClass' => '']) ?>

    <div class="dropdown sidebar-user m-1 rounded">
        <button type="button" class="btn material-shadow-none" id="page-header-user-dropdown-sidebar" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <span class="d-flex align-items-center gap-2">
                <img class="rounded header-profile-user" src="<?= asset_url('images/users/user-dummy-img.jpg') ?>" alt="">
                <span class="text-start">
                    <span class="d-block fw-medium sidebar-user-name-text"><?= esc($user['name'] ?? '') ?></span>
                    <span class="d-block fs-14 sidebar-user-name-sub-text"><?= esc($roleLabel) ?></span>
                </span>
            </span>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
            <?= view('partials/layout/user_menu', ['user' => $user, 'profileUrl' => $profileUrl, 'logoutAction' => $logoutAction]) ?>
        </div>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">
            <div id="two-column-menu"></div>
            <ul class="navbar-nav" id="navbar-nav">
                <?php foreach ($menu as $item): ?>
                    <?php if (isset($item['title'])): ?>
                        <li class="menu-title"><span><?= esc($item['title']) ?></span></li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link menu-link<?= $isActive($item) ? ' active' : '' ?>" href="<?= site_url($item['path']) ?>">
                                <i class="<?= esc($item['icon'], 'attr') ?>"></i> <span><?= esc($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endif ?>
                <?php endforeach ?>
            </ul>
        </div>
    </div>

    <div class="sidebar-background"></div>
</div>
<div class="vertical-overlay"></div>
