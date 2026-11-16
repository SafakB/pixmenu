<?php
/**
 * Landing footer (Velzon .custom-footer). Parametreler: signupUrl, loginUrl, anchorBase, contact{email, phone, address}
 */
?>
<footer class="custom-footer bg-dark py-5 position-relative">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mt-4">
                <a href="<?= base_url() ?>" class="d-inline-flex align-items-center gap-2 fs-20 fw-semibold text-white">
                    <i class="ri-restaurant-2-line"></i>PixMenu
                </a>
                <div class="mt-4 fs-13">
                    <p>QR menü ve dijital menü yönetim sistemi.</p>
                    <p class="ff-secondary">Restoran, kafe, bar ve oteller için çok dilli, alerjen bilgili, anında güncellenen QR kodlu menüler.</p>
                </div>
            </div>

            <div class="col-lg-7 ms-lg-auto">
                <div class="row">
                    <div class="col-sm-4 mt-4">
                        <h2 class="text-white fs-16 mb-0">Ürün</h2>
                        <div class="text-muted mt-3">
                            <ul class="list-unstyled ff-secondary footer-list">
                                <li><a href="<?= esc($anchorBase . '#features', 'attr') ?>">Özellikler</a></li>
                                <li><a href="<?= esc($anchorBase . '#plans', 'attr') ?>">Fiyatlar</a></li>
                                <li><a href="<?= esc($anchorBase . '#faq', 'attr') ?>">Sıkça sorulan sorular</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-sm-4 mt-4">
                        <h2 class="text-white fs-16 mb-0">Hesap</h2>
                        <div class="text-muted mt-3">
                            <ul class="list-unstyled ff-secondary footer-list">
                                <li><a href="<?= esc($signupUrl, 'attr') ?>">Ücretsiz kayıt ol</a></li>
                                <li><a href="<?= esc($loginUrl, 'attr') ?>">Restoran paneline giriş</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-sm-4 mt-4">
                        <h2 class="text-white fs-16 mb-0">Destek</h2>
                        <div class="text-muted mt-3">
                            <ul class="list-unstyled ff-secondary footer-list">
                                <li><a href="<?= site_url('kvkk') ?>">KVKK aydınlatma metni</a></li>
                                <li><a href="<?= site_url('kullanim-kosullari') ?>">Kullanım koşulları</a></li>
                                <li><a href="mailto:<?= esc($contact['email'], 'attr') ?>"><?= esc($contact['email']) ?></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row text-center text-sm-start align-items-center mt-5">
            <div class="col-sm-6">
                <p class="copy-rights mb-0">&copy; <?= date('Y') ?> PixMenu. Tüm hakları saklıdır.</p>
            </div>
            <div class="col-sm-6">
                <p class="text-sm-end mt-3 mt-sm-0 mb-0 text-muted fs-13">QR kod menü · Dijital menü · Karekod menü</p>
            </div>
        </div>
    </div>
</footer>
