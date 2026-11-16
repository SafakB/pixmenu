<?= $this->extend('layouts/landing') ?>
<?php
/**
 * Tanıtım sayfası (Velzon landing.html). Değişkenler: HomeController::index docblock'u.
 */
$this->setVar('anchorBase', '');

$features = [
    ['ri-qr-code-line', 'QR kodlu menü', 'Her şubenize özel QR kod. Masaya, menü standına veya vitrine koyun; misafiriniz telefon kamerasıyla okutsun, menü anında açılsın.'],
    ['ri-translate-2', 'Çok dilli menü', 'Menünüzü ' . ($stats['languages'] > 1 ? $stats['languages'] . ' dile kadar' : 'birden fazla dilde') . ' yayınlayın. Misafir dilini kendisi seçer; sağdan sola yazılan diller de desteklenir.'],
    ['ri-leaf-line', 'Alerjen bilgisi', 'Ürünlerinize alerjenleri ekleyin; gluten, süt, kuruyemiş gibi bilgiler misafirlerinize ikonlarıyla, açıkça gösterilsin.'],
    ['ri-flashlight-line', 'Anında güncelleme', 'Fiyat, ürün veya kategori değişikliği kaydettiğiniz anda menüde. QR kod aynı kalır, yeniden baskı masrafı yoktur.'],
    ['ri-store-2-line', 'Çoklu şube yönetimi', 'Tüm şubelerinizi tek panelden yönetin. Her şubenin kendi menüsü, kendi QR kodu ve kendi fiyatları olsun.'],
    ['ri-layout-masonry-line', 'Hazır menü şablonları', 'Tek sayfa, kategori kartları veya fotoğraflı modern görünüm: markanıza uygun menü tasarımını seçin.'],
    ['ri-image-2-line', 'Ürün fotoğrafları', 'İştah açan ürün fotoğrafları yükleyin; görseller mobil cihazlarda hızlı açılması için otomatik optimize edilir.'],
    ['ri-global-line', 'Kendi alan adınız', 'Menünüzü menu.restoraniniz.com gibi kendi alan adınızda, kendi markanızla yayınlayın.'],
    ['ri-team-line', 'Ekip ve yetkiler', 'Şube yöneticileri ekleyin; her kullanıcı yalnızca yetkili olduğu şubenin menüsünü düzenlesin.'],
];

$steps = [
    ['ri-user-add-line', '1. Ücretsiz kaydolun', 'E-posta adresiniz veya Google hesabınızla bir dakikada hesap açın; restoranınız ve ilk şubeniz hazır olsun.'],
    ['ri-restaurant-line', '2. Menünüzü oluşturun', 'Kategorilerinizi, ürünlerinizi, fiyatlarınızı ve fotoğraflarınızı ekleyin; dilerseniz diğer dillere çevirin.'],
    ['ri-qr-scan-2-line', '3. QR kodu masaya koyun', 'Şubenizin menü bağlantısını QR kod olarak yazdırıp masalara yerleştirin. Misafirleriniz okuttuğu anda güncel menünüze ulaşsın.'],
];

$colClass = count($plans) >= 4 ? 'col-xl-3 col-md-6' : 'col-lg-4 col-md-6';
?>
<?= $this->section('content') ?>
<!-- Hero -->
<section class="section pb-0 hero-section" id="hero">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6">
                <div class="pt-lg-5">
                    <?php if ($stats['trialDays'] !== null): ?>
                        <span class="badge bg-success-subtle text-success fs-13 rounded-pill px-3 py-2 mb-3">
                            <i class="ri-gift-line align-bottom me-1"></i><?= esc((string) $stats['trialDays']) ?> gün ücretsiz · Kredi kartı gerekmez
                        </span>
                    <?php endif ?>
                    <h1 class="display-6 fw-semibold mb-3 lh-base">QR menü ile restoranınızı <span class="text-success">dakikalar içinde</span> dijitale taşıyın</h1>
                    <p class="lead text-muted lh-base mb-4">
                        PixMenu ile restoranınız, kafeniz veya oteliniz için QR kodlu dijital menü oluşturun.
                        Fiyatları anında güncelleyin, menünüzü çok dilli yayınlayın, alerjen bilgisini misafirlerinize şeffafça sunun.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= esc($signupUrl, 'attr') ?>" class="btn btn-primary btn-lg">Ücretsiz deneyin <i class="ri-arrow-right-line align-middle ms-1"></i></a>
                        <a href="#plans" class="btn btn-soft-secondary btn-lg">Fiyatları inceleyin</a>
                    </div>
                    <ul class="list-inline text-muted mt-4 mb-0">
                        <li class="list-inline-item me-3"><i class="ri-checkbox-circle-fill text-success align-middle me-1"></i>Kurulum gerektirmez</li>
                        <li class="list-inline-item me-3"><i class="ri-checkbox-circle-fill text-success align-middle me-1"></i>Uygulama indirme yok</li>
                        <li class="list-inline-item"><i class="ri-checkbox-circle-fill text-success align-middle me-1"></i>Sınırsız güncelleme</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center pb-5">
                    <img src="<?= asset_url('images/landing/hero-phone.svg') ?>" class="img-fluid hero-img" width="520" height="520"
                         alt="Telefonda açılan PixMenu QR menü örneği ve masaya konan QR kod kartı" fetchpriority="high">
                </div>
            </div>
        </div>
    </div>
    <div class="position-absolute start-0 end-0 bottom-0 hero-shape-svg">
        <svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 1440 120" aria-hidden="true">
            <path d="M 0,118 C 288,98.6 1152,40.4 1440,21L1440 140L0 140z"></path>
        </svg>
    </div>
</section>

<!-- Özellikler -->
<section class="section" id="features">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h2 class="section-title mb-3 ff-secondary fw-semibold lh-base">Dijital menünüz için ihtiyacınız olan her şey</h2>
                    <p class="text-muted">Kâğıt menünün baskı maliyetinden ve güncelleme zahmetinden kurtulun. PixMenu, QR kod menüyü kurmayı, yönetmeyi ve çok dilli sunmayı kolaylaştırır.</p>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($features as [$icon, $heading, $text]): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex p-3">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-sm icon-effect">
                                <div class="avatar-title bg-transparent text-success rounded-circle">
                                    <i class="<?= esc($icon, 'attr') ?> fs-36"></i>
                                </div>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h3 class="fs-18"><?= esc($heading) ?></h3>
                            <p class="text-muted my-3 ff-secondary"><?= esc($text) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<!-- Panel -->
<section class="section bg-light py-5">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6 col-sm-8 mx-auto">
                <img src="<?= asset_url('images/landing/feature-panel.svg') ?>" class="img-fluid mx-auto d-block feature-img" width="560" height="380" loading="lazy"
                     alt="PixMenu yönetim paneli: kategori ve ürün listesi">
            </div>
            <div class="col-lg-6">
                <div class="text-muted">
                    <div class="avatar-sm icon-effect mb-4">
                        <div class="avatar-title bg-transparent rounded-circle text-success h1"><i class="ri-dashboard-line fs-36"></i></div>
                    </div>
                    <h2 class="mb-3 fs-20 text-body">Menünüzü kod bilmeden, dakikalar içinde oluşturun</h2>
                    <p class="mb-4 ff-secondary fs-16">
                        Kategorilerinizi sürükle-bırak ile sıralayın, ürünlere fotoğraf, açıklama ve alerjen ekleyin, tükenen ürünü tek tıkla menüden kaldırın.
                        Yaptığınız her değişiklik QR menünüze anında yansır.
                    </p>
                    <div class="row pt-3">
                        <div class="col-4">
                            <div class="text-center">
                                <p class="h4 text-body"><?= esc((string) $stats['languages']) ?></p>
                                <p class="mb-0">Menü dili</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <p class="h4 text-body"><?= esc((string) $stats['templates']) ?></p>
                                <p class="mb-0">Menü şablonu</p>
                            </div>
                        </div>
                        <?php if ($stats['trialDays'] !== null): ?>
                            <div class="col-4">
                                <div class="text-center">
                                    <p class="h4 text-body"><?= esc((string) $stats['trialDays']) ?> gün</p>
                                    <p class="mb-0">Ücretsiz deneme</p>
                                </div>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-5 bg-primary position-relative">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-sm">
                <p class="h4 text-white mb-0 fw-semibold">Baskı masrafına son verin: dijital menünüz bugün yayında olsun.</p>
            </div>
            <div class="col-sm-auto">
                <a href="<?= esc($signupUrl, 'attr') ?>" class="btn bg-gradient btn-danger"><i class="ri-rocket-line align-middle me-1"></i> Ücretsiz başla</a>
            </div>
        </div>
    </div>
</section>

<!-- Çok dilli -->
<section class="section">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6 order-2 order-lg-1">
                <div class="text-muted">
                    <p class="fs-12 text-uppercase text-success fw-semibold mb-2">Misafir deneyimi</p>
                    <h2 class="mb-3 fs-20 text-body">Yabancı misafirlerinize kendi dillerinde hitap edin</h2>
                    <p class="mb-4 ff-secondary">
                        Turistlerin yoğun olduğu bölgelerde menünün anlaşılır olması siparişi hızlandırır. PixMenu ile her ürünün adını ve
                        açıklamasını farklı dillerde girin; misafiriniz menüdeki dil seçiciyle kendi dilini seçsin.
                    </p>
                    <div class="row">
                        <?php foreach ([['Menü içi dil seçici', 'Sağdan sola dil desteği'], ['Alerjen ikonları', 'Şubeye özel menü']] as $column): ?>
                            <div class="col-sm-6">
                                <div class="vstack gap-2">
                                    <?php foreach ($column as $item): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-2">
                                                <div class="avatar-xs icon-effect">
                                                    <div class="avatar-title bg-transparent text-success rounded-circle h2"><i class="ri-check-fill"></i></div>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1"><p class="fs-14 mb-0 fw-medium text-body"><?= esc($item) ?></p></div>
                                        </div>
                                    <?php endforeach ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <div class="mt-4">
                        <a href="<?= esc($signupUrl, 'attr') ?>" class="btn btn-primary">Hemen deneyin <i class="ri-arrow-right-line align-middle ms-1"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-sm-8 mx-auto order-1 order-lg-2">
                <img src="<?= asset_url('images/landing/feature-languages.svg') ?>" class="img-fluid mx-auto d-block feature-img" width="560" height="380" loading="lazy"
                     alt="Farklı dillerde görüntülenen QR menü: Türkçe, İngilizce, Almanca ve Arapça">
            </div>
        </div>
    </div>
</section>

<!-- Nasıl çalışır -->
<section class="section bg-light" id="how">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h2 class="section-title mb-3 fw-semibold">QR menünüz 3 adımda hazır</h2>
                    <p class="text-muted mb-4 ff-secondary">Teknik bilgi, kurulum veya özel donanım gerekmez. Bir bilgisayar ya da telefon yeterli.</p>
                </div>
            </div>
        </div>
        <div class="row text-center">
            <?php foreach ($steps as [$icon, $heading, $text]): ?>
                <div class="col-lg-4">
                    <div class="process-card mt-4">
                        <div class="avatar-sm icon-effect mx-auto mb-4">
                            <div class="avatar-title bg-transparent text-success rounded-circle h1"><i class="<?= esc($icon, 'attr') ?>"></i></div>
                        </div>
                        <h3 class="fs-16"><?= esc($heading) ?></h3>
                        <p class="text-muted ff-secondary"><?= esc($text) ?></p>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<!-- Fiyatlar -->
<section class="section" id="plans">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h2 class="section-title mb-3 fw-semibold">İşletmenize uygun paketi seçin</h2>
                    <p class="text-muted mb-4">
                        Tüm paketlerde QR menü, çok dilli menü ve alerjen bilgisi yer alır. Gizli ücret yok;
                        fiyatlara KDV dahil değildir (%<?= esc(pix_money($taxRate)) ?>).
                    </p>
                    <div class="d-inline-flex">
                        <ul class="nav nav-pills arrow-navtabs plan-nav rounded mb-3 p-1" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold active" type="button" data-cycle="monthly" aria-selected="true">Aylık</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold" type="button" data-cycle="yearly" aria-selected="false">Yıllık</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="row gy-4 justify-content-center">
            <?php foreach ($plans as $p): ?>
                <?php
                $isTrial = $p['trial_days'] !== null;
                $href    = $signupUrl . '?plan=' . rawurlencode($p['code']) . ($isTrial ? '' : '&cycle=monthly');
                $footer  = $isTrial
                    ? '<a href="' . esc($href, 'attr') . '" class="btn btn-soft-success w-100">Ücretsiz dene</a>'
                    : '<a href="' . esc($href, 'attr') . '" data-cycle-href class="btn btn-' . ((int) $p['is_featured'] === 1 ? 'primary' : 'soft-primary') . ' w-100">Hemen başla</a>';
                ?>
                <div class="<?= $colClass ?> plan-col">
                    <?= view('partials/billing/plan_card', ['plan' => $p, 'startYearly' => false, 'isCurrent' => false, 'footer' => $footer]) ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<!-- SSS -->
<section class="section bg-light" id="faq">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h2 class="section-title mb-3 fw-semibold">Sıkça sorulan sorular</h2>
                    <p class="text-muted mb-4 ff-secondary">Aradığınız cevabı bulamazsanız bize e-posta ile yazın; en kısa sürede dönüş yapalım.</p>
                    <a href="mailto:<?= esc($contact['email'], 'attr') ?>" class="btn btn-primary btn-label rounded-pill"><i class="ri-mail-line label-icon align-middle rounded-pill fs-16 me-2"></i> Bize yazın</a>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="accordion custom-accordionwithicon custom-accordion-border accordion-border-box faq-accordion" id="faq-accordion">
                    <?php foreach ($faq as $i => $item): ?>
                        <div class="accordion-item">
                            <h3 class="accordion-header" id="faq-heading-<?= $i ?>">
                                <button class="accordion-button<?= $i === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse-<?= $i ?>"
                                        aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="faq-collapse-<?= $i ?>">
                                    <?= esc($item['q']) ?>
                                </button>
                            </h3>
                            <div id="faq-collapse-<?= $i ?>" class="accordion-collapse collapse<?= $i === 0 ? ' show' : '' ?>" aria-labelledby="faq-heading-<?= $i ?>" data-bs-parent="#faq-accordion">
                                <div class="accordion-body ff-secondary"><?= esc($item['a']) ?></div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- İletişim -->
<section class="section" id="contact">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h2 class="section-title mb-3 fw-semibold">Bize ulaşın</h2>
                    <p class="text-muted mb-4 ff-secondary">Paketler, toplu şube kurulumu veya kurumsal ihtiyaçlarınız için bizimle iletişime geçin.</p>
                </div>
            </div>
        </div>
        <div class="row gy-4 justify-content-center text-center">
            <?php foreach ([
                ['ri-mail-send-line', 'E-posta', '<a href="mailto:' . esc($contact['email'], 'attr') . '">' . esc($contact['email']) . '</a>'],
                ['ri-phone-line', 'Telefon', '<a href="tel:' . esc(preg_replace('/[^0-9+]/', '', $contact['phone']), 'attr') . '">' . esc($contact['phone']) . '</a>'],
                ['ri-map-pin-2-line', 'Adres', esc($contact['address'])],
            ] as [$icon, $label, $html]): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-body h-100 border shadow-none mb-0">
                        <div class="avatar-sm mx-auto mb-3">
                            <div class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20"><i class="<?= esc($icon, 'attr') ?>"></i></div>
                        </div>
                        <h3 class="fs-13 text-muted text-uppercase"><?= esc($label) ?></h3>
                        <div class="ff-secondary fw-semibold"><?= $html ?></div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<!-- Son CTA -->
<section class="py-5 bg-primary position-relative">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-sm">
                <p class="h4 text-white mb-1 fw-semibold">Dijital menünüzü bugün oluşturun</p>
                <p class="text-white-50 mb-0">Kayıt bir dakika sürer<?= $stats['trialDays'] !== null ? '; ilk ' . esc((string) $stats['trialDays']) . ' gün ücretsiz' : '' ?>.</p>
            </div>
            <div class="col-sm-auto">
                <a href="<?= esc($signupUrl, 'attr') ?>" class="btn bg-gradient btn-danger"><i class="ri-user-add-line align-middle me-1"></i> Ücretsiz kayıt ol</a>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script src="<?= asset_url('js/pix-plan-cycle.js') ?>"></script>
<?= $this->endSection() ?>
