<?php
/**
 * Giriş ve kayıt sayfaları layout'u (Velzon auth-signin-basic).
 * Section'lar: content (kart içi), heading (kart başlığı), subtitle (kart alt yazısı),
 *              css (head sonu, ops.), js (çekirdek script'lerden sonra, ops.).
 * Değişkenler: title (ops.), authColClass (ops.; kart sütunu — varsayılan dar giriş kartı, kayıt geniş verir).
 */
?>
<!doctype html>
<html lang="tr" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">
<head>
<?= view('partials/layout/head', ['pageTitle' => ($title ?? 'Giriş') . ' | PixMenu', 'withCsrfMeta' => false]) ?>
<?= $this->renderSection('css') ?>
</head>
<body>
<div class="auth-page-wrapper pt-5">
    <div class="auth-one-bg-position auth-one-bg" id="auth-particles">
        <div class="bg-overlay"></div>
        <div class="shape">
            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 1440 120" aria-hidden="true">
                <path d="M 0,36 C 144,53.6 432,123.2 720,124 C 1008,124.8 1296,56.8 1440,40L1440 140L0 140z"></path>
            </svg>
        </div>
    </div>

    <div class="auth-page-content">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-center mt-sm-5 mb-4 text-white-50">
                        <a href="<?= base_url() ?>" class="d-inline-flex align-items-center gap-2 fs-24 fw-semibold text-white">
                            <i class="ri-restaurant-2-line"></i>PixMenu
                        </a>
                        <p class="mt-3 fs-15 fw-medium">Dijital Menü Yönetimi</p>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="<?= esc($authColClass ?? 'col-md-8 col-lg-6 col-xl-5', 'attr') ?>">
                    <div class="card mt-4 card-bg-fill">
                        <div class="card-body p-4">
                            <div class="text-center mt-2">
                                <h5 class="text-primary"><?= $this->renderSection('heading') ?></h5>
                                <p class="text-muted"><?= $this->renderSection('subtitle') ?></p>
                            </div>
                            <div class="p-2 mt-4">
                                <?= $this->renderSection('content') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-center">
                        <p class="mb-0 text-muted">&copy; <?= date('Y') ?> PixMenu</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</div>

<script src="<?= asset_url('libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset_url('libs/particles.js/particles.js') ?>"></script>
<script src="<?= asset_url('js/pages/particles.app.js') ?>"></script>
<script src="<?= asset_url('js/pages/password-addon.init.js') ?>"></script>
<?= $this->renderSection('js') ?>
</body>
</html>
