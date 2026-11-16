<?php
/**
 * Landing navbar (Velzon .navbar-landing). Parametreler: signupUrl, loginUrl, anchorBase ('' = ana sayfadayız).
 */
$links = [
    'features' => 'Özellikler',
    'how'      => 'Nasıl çalışır',
    'plans'    => 'Fiyatlar',
    'faq'      => 'SSS',
    'contact'  => 'İletişim',
];
?>
<nav class="navbar navbar-expand-lg navbar-landing fixed-top" id="navbar" aria-label="Ana menü">
    <div class="container">
        <a class="navbar-brand d-inline-flex align-items-center gap-2 fs-20 fw-semibold" href="<?= base_url() ?>">
            <i class="ri-restaurant-2-line text-primary fs-24"></i>PixMenu
        </a>
        <button class="navbar-toggler py-0 fs-20 text-body" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Menüyü aç/kapat">
            <i class="mdi mdi-menu"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mx-auto mt-2 mt-lg-0" id="navbar-example">
                <?php foreach ($links as $id => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= esc($anchorBase . '#' . $id, 'attr') ?>"><?= esc($label) ?></a>
                    </li>
                <?php endforeach ?>
            </ul>

            <div class="d-flex gap-2 pb-3 pb-lg-0">
                <a href="<?= esc($loginUrl, 'attr') ?>" class="btn btn-link fw-medium text-decoration-none text-body">Giriş yap</a>
                <a href="<?= esc($signupUrl, 'attr') ?>" class="btn btn-primary">Ücretsiz dene</a>
            </div>
        </div>
    </div>
</nav>
<div class="vertical-overlay" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent.show"></div>
