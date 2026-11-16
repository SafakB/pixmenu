<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Herkese açık tanıtım sitesi (landing) içerikleri. .env ile ezilebilir: `site.contactEmail = '...'`.
 * TODO(icerik): iletişim bilgileri yer tutucudur; yayına almadan önce gerçek bilgilerle değiştirin.
 */
final class Site extends BaseConfig
{
    public string $name = 'PixMenu';

    /** Arama sonuçlarında görünen başlık (≈60 karakter). */
    public string $title = 'PixMenu — QR Menü ve Dijital Menü Sistemi | 14 Gün Ücretsiz';

    /** Meta description (≈155 karakter). */
    public string $description = 'Restoran, kafe ve oteller için QR kodlu dijital menü. Çok dilli menü, alerjen bilgisi ve çoklu şube yönetimi. Kurulum yok, 14 gün ücretsiz deneyin.';

    public string $contactEmail = 'merhaba@pixmenu.com';

    public string $contactPhone = '+90 (850) 000 00 00';

    public string $contactAddress = 'İstanbul, Türkiye';

    /**
     * Sıkça sorulan sorular — landing accordion'u ve FAQPage yapılandırılmış verisi aynı kaynaktan beslenir.
     *
     * @var list<array{q: string, a: string}>
     */
    public array $faq = [
        [
            'q' => 'QR menü nedir, nasıl çalışır?',
            'a' => 'QR menü, misafirlerinizin masadaki QR kodu telefon kamerasıyla okutarak menünüzü anında görüntülemesini sağlar. Uygulama indirmeye gerek yoktur; menü telefonun tarayıcısında açılır.',
        ],
        [
            'q' => 'Ücretsiz deneme için kredi kartı gerekiyor mu?',
            'a' => 'Hayır. Demo paketiyle ücretsiz deneme sürenizi kredi kartı bilgisi vermeden başlatırsınız. Süre bitmeden dilediğiniz zaman ücretli bir pakete geçebilirsiniz.',
        ],
        [
            'q' => 'Menümü kaç dilde yayınlayabilirim?',
            'a' => 'Türkçe, İngilizce, Almanca, Rusça ve Arapça gibi sistemde tanımlı dillerde menü yayınlayabilirsiniz. Misafirleriniz dili menü üzerinden kendileri seçer; sağdan sola yazılan diller de desteklenir.',
        ],
        [
            'q' => 'Fiyat veya ürün değişikliği menüye ne zaman yansır?',
            'a' => 'Panelden yaptığınız her değişiklik menünüze anında yansır. QR kodunuz değişmez; yeniden baskı gerekmez.',
        ],
        [
            'q' => 'Birden fazla şubem var, hepsini tek hesaptan yönetebilir miyim?',
            'a' => 'Evet. Açabileceğiniz şube sayısı paketinize göre değişir (fiyat tablosunda görebilirsiniz); her şubeye ayrı menü, QR kod ve şube yöneticisi tanımlayabilirsiniz.',
        ],
        [
            'q' => 'Menümü kendi alan adımda yayınlayabilir miyim?',
            'a' => 'Özel alan adı içeren paketlerde menünüzü kendi alan adınızdan (ör. menu.restoraniniz.com) yayınlayabilirsiniz.',
        ],
        [
            'q' => 'Aboneliğimi nasıl iptal ederim?',
            'a' => 'Aboneliğinizin dönem sonunda yenilenmemesini istediğinizde bizimle iletişime geçmeniz yeterlidir; menü verileriniz silinmez.',
        ],
    ];
}
