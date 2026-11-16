<?= $this->extend('layouts/landing') ?>
<?php
/**
 * Yasal metinler: KVKK aydınlatma metni / kullanım koşulları.
 * Değişkenler: page ('privacy'|'terms'), title, description, updatedAt, contact, signupUrl, loginUrl, seo, jsonLd
 *
 * TODO(hukuk): Aşağıdaki metinler YER TUTUCUDUR ve hukuki danışmanlık yerine geçmez.
 * Yayına almadan önce veri sorumlusu bilgileri ve hukuk danışmanınızın onayladığı metinle değiştirin.
 */
$this->setVar('anchorBase', base_url());
?>
<?= $this->section('content') ?>
<section class="section pb-4 bg-light" style="padding-top: 130px;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="<?= base_url() ?>">Ana sayfa</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= esc($title) ?></li>
                    </ol>
                </nav>
                <h1 class="fs-24 fw-semibold mb-1"><?= esc($title) ?></h1>
                <p class="text-muted mb-0">Son güncelleme: <?= esc($updatedAt) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="alert alert-warning" role="note">
                    <i class="ri-draft-line align-bottom me-1"></i> Bu metin taslaktır ve yayın öncesinde hukuki olarak gözden geçirilecektir.
                </div>
                <div class="legal-content text-muted ff-secondary fs-15">
                    <?php if ($page === 'privacy'): ?>
                        <p>Bu aydınlatma metni, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") kapsamında, PixMenu hizmetini kullanan restoran yetkilileri ve kullanıcılarının kişisel verilerinin işlenmesine ilişkin olarak veri sorumlusu sıfatıyla bilgilendirme amacıyla hazırlanmıştır.</p>

                        <h2 class="text-body">1. İşlenen kişisel veriler</h2>
                        <p>Kayıt ve hizmet kullanımı sırasında; ad soyad, e-posta adresi, telefon numarası, işletme ve şube bilgileri, giriş kayıtları (IP adresi, tarih/saat) ile Google ile giriş yapılması hâlinde Google hesabınızın kimlik numarası, adı ve e-posta adresi işlenir. Kart bilgileri PixMenu sunucularında saklanmaz; ödeme altyapısı sağlayıcısı tarafından işlenir.</p>

                        <h2 class="text-body">2. İşleme amaçları</h2>
                        <p>Kişisel verileriniz; üyelik hesabının oluşturulması ve yönetimi, dijital menü hizmetinin sunulması, abonelik ve faturalama süreçlerinin yürütülmesi, hizmetle ilgili bildirimlerin gönderilmesi, bilgi güvenliğinin sağlanması ve yasal yükümlülüklerin yerine getirilmesi amaçlarıyla işlenir.</p>

                        <h2 class="text-body">3. Hukuki sebepler</h2>
                        <p>Veriler, KVKK m.5/2 kapsamında bir sözleşmenin kurulması ve ifası, veri sorumlusunun hukuki yükümlülüğünü yerine getirmesi ve meşru menfaati hukuki sebeplerine dayanılarak işlenir.</p>

                        <h2 class="text-body">4. Aktarım</h2>
                        <p>Kişisel verileriniz; barındırma, e-posta gönderimi ve ödeme hizmeti aldığımız iş ortaklarına, yalnızca hizmetin sunulması için gerekli ölçüde ve yetkili kamu kurumlarına yasal zorunluluk hâlinde aktarılabilir.</p>

                        <h2 class="text-body">5. Haklarınız</h2>
                        <p>KVKK m.11 uyarınca; verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, düzeltilmesini veya silinmesini isteme ve diğer haklarınızı kullanmak için <a href="mailto:<?= esc($contact['email'], 'attr') ?>"><?= esc($contact['email']) ?></a> adresine başvurabilirsiniz.</p>
                    <?php else: ?>
                        <p>Bu kullanım koşulları, PixMenu dijital menü hizmetine ("Hizmet") kaydolan ve Hizmeti kullanan işletmeler ("Kullanıcı") ile PixMenu arasındaki ilişkiyi düzenler. Hizmete kaydolarak bu koşulları kabul etmiş sayılırsınız.</p>

                        <h2 class="text-body">1. Hizmetin kapsamı</h2>
                        <p>PixMenu; restoran, kafe ve benzeri işletmelerin QR kod ile erişilen dijital menülerini oluşturup yönetmesini sağlayan bir yazılım hizmetidir. Paket kapsamları fiyatlandırma sayfasında belirtilir.</p>

                        <h2 class="text-body">2. Hesap ve güvenlik</h2>
                        <p>Kullanıcı, kayıt sırasında verdiği bilgilerin doğruluğundan ve hesap bilgilerinin gizliliğinden sorumludur. Hesap üzerinden yapılan işlemler Kullanıcı tarafından yapılmış sayılır.</p>

                        <h2 class="text-body">3. İçerik sorumluluğu</h2>
                        <p>Menüde yer alan ürün, fiyat, alerjen ve görsel içeriklerinin doğruluğu ve mevzuata uygunluğu Kullanıcı'nın sorumluluğundadır.</p>

                        <h2 class="text-body">4. Ücretlendirme ve deneme</h2>
                        <p>Deneme paketi süresince Hizmet ücretsizdir. Ücretli paketlerde ücretler seçilen döneme (aylık/yıllık) göre peşin faturalandırılır; fiyatlara KDV dahil değildir. Vadesinde ödenmeyen faturalarda Hizmet askıya alınabilir.</p>

                        <h2 class="text-body">5. Sona erme</h2>
                        <p>Kullanıcı aboneliğinin dönem sonunda yenilenmemesini talep edebilir. PixMenu, bu koşulların ihlali hâlinde Hizmeti askıya alma veya sonlandırma hakkını saklı tutar.</p>

                        <h2 class="text-body">6. İletişim</h2>
                        <p>Sorularınız için <a href="mailto:<?= esc($contact['email'], 'attr') ?>"><?= esc($contact['email']) ?></a> adresinden bize ulaşabilirsiniz.</p>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
