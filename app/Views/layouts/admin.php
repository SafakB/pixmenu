<?php
/**
 * Admin layout (Velzon). Section'lar: content, css (ops.), js (ops.).
 * Değişkenler: currentAdmin, title (ops.), breadcrumbs (ops.).
 */
$shell = [
    'user'         => $currentAdmin ?? [],
    'roleLabel'    => 'Sistem Yöneticisi',
    'homeUrl'      => site_url('admin'),
    'profileUrl'   => null,
    'logoutAction' => 'admin/logout',
    'menu'         => [
        ['title' => 'Menü'],
        ['label' => 'Gösterge Paneli', 'path' => 'admin', 'icon' => 'ri-dashboard-2-line'],
        ['label' => 'Restoranlar', 'path' => 'admin/restaurants', 'icon' => 'ri-store-2-line', 'match' => ['admin/restaurants', 'admin/restaurants/*']],
        ['title' => 'Tanımlar'],
        ['label' => 'Diller', 'path' => 'admin/languages', 'icon' => 'ri-translate-2', 'match' => ['admin/languages', 'admin/languages/*']],
        ['label' => 'Alerjenler', 'path' => 'admin/allergens', 'icon' => 'ri-error-warning-line', 'match' => ['admin/allergens', 'admin/allergens/*']],
    ],
];
?>
<!doctype html>
<html lang="tr" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">
<head>
<?= view('partials/layout/head', ['pageTitle' => ($title ?? 'Yönetim') . ' | PixMenu Admin', 'withCsrfMeta' => true]) ?>
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
