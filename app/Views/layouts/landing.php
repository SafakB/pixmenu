<?php
/**
 * Herkese açık site layout'u (Velzon landing.html): tanıtım sayfası ve yasal metinler.
 * partials/layout/head KULLANILMAZ (o admin/panel için `noindex` basar); SEO etiketleri burada.
 *
 * Section'lar: content, js (bootstrap'tan sonra, landing.init.js'ten önce).
 * Değişkenler: seo{title, description, canonical, ogImage}, jsonLd (list<array>; boş olabilir),
 *              signupUrl, loginUrl, contact{email, phone, address}, anchorBase ('' = ana sayfa; değilse base_url())
 */
$anchorBase ??= '';
?>
<!doctype html>
<html lang="tr" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($seo['title']) ?></title>
    <meta name="description" content="<?= esc($seo['description'], 'attr') ?>">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="<?= esc($seo['canonical'], 'attr') ?>">
    <meta name="theme-color" content="#405189">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="PixMenu">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:title" content="<?= esc($seo['title'], 'attr') ?>">
    <meta property="og:description" content="<?= esc($seo['description'], 'attr') ?>">
    <meta property="og:url" content="<?= esc($seo['canonical'], 'attr') ?>">
    <meta property="og:image" content="<?= esc($seo['ogImage'], 'attr') ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= esc($seo['title'], 'attr') ?>">
    <meta name="twitter:description" content="<?= esc($seo['description'], 'attr') ?>">
    <meta name="twitter:image" content="<?= esc($seo['ogImage'], 'attr') ?>">

    <link rel="icon" href="<?= asset_url('images/favicon.ico') ?>">
    <link href="<?= asset_url('css/bootstrap.min.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= asset_url('css/icons.min.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= asset_url('css/app.min.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= asset_url('css/custom.min.css') ?>" rel="stylesheet" type="text/css">
    <style>
        .landing .navbar-brand { color: var(--vz-heading-color); }
        .landing .hero-section { padding-top: 140px; }
        .landing .hero-img { max-height: 520px; }
        .landing .section-title { font-size: calc(1.3rem + .6vw); }
        @media (min-width: 1200px) { .landing .section-title { font-size: 1.75rem; } }
        .landing .feature-img { max-height: 380px; }
        .landing .faq-accordion .accordion-button { font-weight: 600; }
        .landing .legal-content h2 { font-size: 1.15rem; margin-top: 2rem; }
        .landing .plan-col .pricing-box { margin-bottom: 0; }
    </style>
    <?php foreach ($jsonLd as $block): ?>
    <script type="application/ld+json"><?= json_encode($block, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php endforeach ?>
</head>

<body data-bs-spy="scroll" data-bs-target="#navbar-example" data-bs-offset="90">
<div class="layout-wrapper landing">
    <?= view('site/partials/navbar', ['signupUrl' => $signupUrl, 'loginUrl' => $loginUrl, 'anchorBase' => $anchorBase]) ?>

    <main>
        <?= $this->renderSection('content') ?>
    </main>

    <?= view('site/partials/footer', ['signupUrl' => $signupUrl, 'loginUrl' => $loginUrl, 'anchorBase' => $anchorBase, 'contact' => $contact]) ?>

    <button type="button" class="btn btn-danger btn-icon landing-back-top" id="back-to-top" aria-label="Sayfa başına dön">
        <i class="ri-arrow-up-line"></i>
    </button>
</div>

<script src="<?= asset_url('libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<?= $this->renderSection('js') ?>
<script src="<?= asset_url('js/pages/landing.init.js') ?>"></script>
</body>
</html>
