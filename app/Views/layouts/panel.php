<?php
/**
 * Restoran paneli layout'u (Velzon). Section'lar: content, css (ops.), js (ops.).
 * Değişkenler: currentUser, isOwner, title (ops.), breadcrumbs (ops.).
 */
$isOwner = ! empty($isOwner);

$menu = [
    ['title' => 'Menü'],
    ['label' => 'Gösterge Paneli', 'path' => 'panel', 'icon' => 'ri-dashboard-2-line'],
    ['label' => 'Şubeler', 'path' => 'panel/branches', 'icon' => 'ri-store-2-line', 'match' => ['panel/branches', 'panel/branches/*']],
];

if ($isOwner) {
    $menu[] = ['title' => 'Restoran'];
    $menu[] = ['label' => 'Restoran Bilgileri', 'path' => 'panel/restaurant', 'icon' => 'ri-restaurant-2-line'];
    $menu[] = ['label' => 'Kullanıcılar', 'path' => 'panel/users', 'icon' => 'ri-team-line', 'match' => ['panel/users', 'panel/users/*']];
}

$menu[] = ['title' => 'Hesap'];
$menu[] = ['label' => 'Profil', 'path' => 'panel/profile', 'icon' => 'ri-user-settings-line'];

$shell = [
    'user'         => $currentUser ?? [],
    'roleLabel'    => $isOwner ? 'Restoran Sahibi' : 'Şube Yöneticisi',
    'homeUrl'      => site_url('panel'),
    'profileUrl'   => site_url('panel/profile'),
    'logoutAction' => 'panel/logout',
    'menu'         => $menu,
];
?>
<!doctype html>
<html lang="tr" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">
<head>
<?= view('partials/layout/head', ['pageTitle' => ($title ?? 'Panel') . ' | PixMenu', 'withCsrfMeta' => true]) ?>
    <?= $this->renderSection('css') ?>
</head>
<body>
<div id="layout-wrapper">
    <?= view('partials/layout/topbar', $shell) ?>
    <?= view('partials/layout/sidebar', $shell) ?>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <?= view('partials/layout/page_title', ['title' => $title ?? null, 'breadcrumbs' => $breadcrumbs ?? []]) ?>
                <?= $this->include('partials/flash') ?>
                <?= $this->renderSection('content') ?>
            </div>
        </div>
        <?= view('partials/layout/footer') ?>
    </div>
</div>

<?= view('partials/layout/shell_end') ?>
<?= view('partials/layout/scripts') ?>
<?= $this->renderSection('js') ?>
<script src="<?= asset_url('js/app.js') ?>"></script>
</body>
</html>
