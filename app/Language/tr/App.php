<?php

declare(strict_types=1);

/**
 * Admin ve restoran paneli arayüz metinleri (Türkçe).
 * Menü içeriğinin çok dilliliği bundan bağımsızdır (çeviri tabloları).
 */
return [
    'saved'            => 'Kaydedildi.',
    'created'          => 'Kayıt oluşturuldu.',
    'deleted'          => 'Kayıt silindi.',
    'statusChanged'    => 'Durum güncellendi.',
    'notFound'         => 'Kayıt bulunamadı.',
    'validationFailed' => 'Doğrulama hatası.',
    'saveFailed'       => 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.',

    'auth' => [
        'invalid'   => 'E-posta veya şifre hatalı.',
        'throttled' => 'Çok fazla deneme yapıldı. Lütfen bir dakika sonra tekrar deneyin.',
        'required'  => 'Oturum açmanız gerekiyor.',
        'forbidden' => 'Bu işlem için yetkiniz yok.',
        'loggedOut' => 'Oturum kapatıldı.',
    ],

    'reorder' => [
        'invalid' => 'Sıralama verisi geçersiz.',
        'foreign' => 'Sıralama listesi bu şubeye ait olmayan kayıtlar içeriyor.',
        'saved'   => 'Sıralama kaydedildi.',
    ],

    'upload' => [
        'failed'        => 'Dosya yüklenemedi.',
        'tooLarge'      => 'Dosya boyutu en fazla {0} KB olabilir.',
        'tooBig'        => 'Görsel en fazla {0} × {0} piksel olabilir.',
        'invalidType'   => 'Geçersiz dosya türü. İzin verilenler: {0}.',
        'invalidSvg'    => 'SVG dosyası geçersiz veya güvenli değil.',
        'unprocessable' => 'Görsel işlenemedi.',
        'imageDeleted'  => 'Görsel silindi.',
    ],

    'translation' => [
        'nameRequired' => '{0} dilinde ad zorunludur.',
        'tooLong'      => '{0} dilindeki {1} alanı en fazla {2} karakter olabilir.',
    ],

    'language' => [
        'codeInvalid'      => 'Dil kodu geçersiz (ör. tr, en, pt-BR).',
        'codeTaken'        => 'Bu dil kodu zaten kullanılıyor.',
        'inUseDeactivate'  => 'Bu dil kullanımda olduğu için silinemez; yalnızca pasifleştirilebilir.',
        'defaultInUse'     => 'Bu dil bir restoranın veya şubenin varsayılan dili olduğu için pasifleştirilemez.',
        'notInRestaurant'  => 'Seçilen dil restoranın aktif dilleri arasında değil.',
        'atLeastOne'       => 'En az bir dil seçilmelidir.',
        'invalidSelection' => 'Seçilen dillerden biri geçersiz veya pasif.',
        'defaultMustBeIn'  => 'Varsayılan dil, seçilen diller arasında olmalıdır.',
        'removeIsDefault'  => '{0} dili restoranın veya bir şubesinin varsayılan dili olduğu için çıkarılamaz.',
        'synced'           => 'Restoran dilleri güncellendi.',
    ],

    'allergen' => [
        'codeInvalid' => 'Alerjen kodu yalnızca küçük harf, rakam ve alt çizgi içerebilir.',
        'codeTaken'   => 'Bu alerjen kodu zaten kullanılıyor.',
        'inUse'       => 'Bu alerjen {0} üründe kullanıldığı için silinemez; yalnızca pasifleştirilebilir.',
        'invalid'     => 'Seçilen alerjenlerden biri geçersiz veya pasif.',
        'levelInvalid' => 'Alerjen düzeyi geçersiz.',
    ],

    'template' => [
        'codeInvalid'   => 'Şablon kodu yalnızca küçük harf, rakam ve tire içerebilir (en fazla 30 karakter).',
        'codeTaken'     => 'Bu şablon kodu zaten kullanılıyor.',
        'inUse'         => 'Bu şablon {0} şubede kullanıldığı için silinemez veya pasifleştirilemez. Önce şubelere başka şablon atayın.',
        'defaultLocked' => 'Standart şablon silinemez ve pasifleştirilemez.',
        'notSelectable' => 'Geçerli bir menü şablonu seçin.',
    ],

    'restaurant' => [
        'slugTaken'     => 'Bu kısa ad (slug) zaten kullanılıyor.',
        'slugInvalid'     => 'Kısa ad yalnızca küçük harf, rakam ve tire içerebilir.',
        'slugReserved'    => 'Bu kısa ad sistem tarafından ayrılmıştır; menü alt alan adı olarak kullanılamaz.',
        'currencyInvalid' => 'Geçerli bir para birimi seçin.',
        'currencyLocked'  => 'Restoranın ürünleri varken para birimi değiştirilemez; fiyatlar dönüştürülmez.',
        'logoDeleted'     => 'Logo silindi.',
    ],

    'branch' => [
        'slugTaken'       => 'Bu kısa ad (slug) bu restoranda zaten kullanılıyor.',
        'codeRegenerated' => 'Menü kodu yenilendi. Eski QR kodları artık çalışmaz.',
        'invalid'         => 'Seçilen şubelerden biri geçersiz.',
        'atLeastOne'      => 'Şube yöneticisi için en az bir şube seçilmelidir.',
    ],

    'user' => [
        'emailTaken'      => 'Bu e-posta adresi zaten kullanılıyor.',
        'emailInvalid'    => 'Geçerli bir e-posta adresi girin.',
        'nameRequired'    => 'Ad zorunludur.',
        'roleInvalid'     => 'Rol geçersiz.',
        'lastOwner'       => 'Restoranın son aktif sahibi silinemez, pasifleştirilemez veya yöneticiliğe düşürülemez.',
        'cannotSelf'      => 'Kendi hesabınız üzerinde bu işlemi yapamazsınız.',
        'passwordWeak'    => 'Şifre en az 10 karakter olmalı, en az bir harf ve bir rakam içermelidir.',
        'passwordConfirm' => 'Şifre tekrarı eşleşmiyor.',
        'passwordCurrent' => 'Mevcut şifre hatalı.',
        'passwordChanged' => 'Şifre değiştirildi. Diğer cihazlardaki oturumlar kapatıldı.',
    ],

    'category' => [
        'hasProducts' => 'Bu kategoride {0} ürün olduğu için silinemez. Önce ürünleri taşıyın veya silin.',
        'invalid'     => 'Seçilen kategori bu şubeye ait değil.',
    ],

    'product' => [
        'priceInvalid' => 'Fiyat 0 veya daha büyük, geçerli bir sayı olmalıdır.',
        'priceTooBig'  => 'Fiyat çok büyük.',
    ],

    'field' => [
        'sortOrder' => 'Sıra 0 ile 65535 arasında bir tam sayı olmalıdır.',
        'phone'     => 'Telefon en fazla 30 karakter olabilir.',
    ],

    'plan' => [
        'codeInvalid'             => 'Paket kodu küçük harfle başlamalı; yalnızca küçük harf, rakam ve alt çizgi içerebilir (2–30 karakter).',
        'codeTaken'               => 'Bu paket kodu zaten kullanılıyor.',
        'badgeClassInvalid'       => "Rozet class'ı yalnızca harf, rakam, boşluk, tire, alt çizgi ve iki nokta içerebilir (en fazla 100 karakter).",
        'priceInvalid'            => 'Fiyat 0 veya daha büyük, geçerli bir tutar olmalıdır (ör. 499,90).',
        'maxBranchesInvalid'      => 'Şube limiti 1–9999 arasında olmalı; sınırsız için boş bırakın.',
        'trialDaysInvalid'        => 'Deneme süresi 1–9999 gün arasında olmalı; ücretli paket için boş bırakın.',
        'inUse'                   => 'Bu paket bir abonelikte veya faturada kullanıldığı için silinemez; yalnızca pasifleştirilebilir.',
        'defaultCannotDeactivate' => 'Varsayılan deneme paketi pasifleştirilemez veya silinemez.',
    ],

    'subscription' => [
        'none'               => 'Restoranın güncel bir aboneliği yok.',
        'alreadyCurrent'     => 'Restoranın zaten güncel bir aboneliği var; paket değişimi kullanın.',
        'planInvalid'        => 'Seçilen paket geçersiz veya pasif.',
        'cycleInvalid'       => 'Ödeme dönemi aylık veya yıllık olmalıdır.',
        'graceInvalid'       => 'Gecikme eşiği 0–365 gün arasında olmalıdır.',
        'branchLimit'        => 'Paketinizin şube limiti ({0}) dolu. Yeni şube açmak veya şubeyi aktifleştirmek için paketinizi yükseltin ya da başka bir şubeyi pasifleştirin.',
        'planBranchesExceed' => 'Restoranın {0} aktif şubesi var; seçilen paket en fazla {1} şubeye izin veriyor. Önce fazla şubeleri pasifleştirin.',
        'planNoDomain'       => 'Seçilen paket özel alan adını kapsamıyor. Önce restoranın alan adını kaldırın.',
        'changed'            => 'Paket değiştirildi.',
        'changedOpenInvoices' => 'Paket değiştirildi. Eski aboneliğe ait {0} açık fatura var; gerekiyorsa faturalar ekranından iptal edin.',
        'cancelScheduled'    => 'Abonelik dönem sonunda sonlandırılacak.',
        'cancelUndone'       => 'Dönem sonu iptali geri alındı.',
        'suspended'          => 'Restoran askıya alındı.',
        'resumed'            => 'Askı kaldırıldı.',
        'stillSuspended'     => 'Elle askı kaldırıldı ancak restoran başka bir nedenle askıda kalıyor: {0}.',
        'graceSaved'         => 'Gecikme eşiği kaydedildi.',
        'discountSaved'      => 'Restoran indirimi kaydedildi; sonraki faturalara uygulanacak.',
        'discountRemoved'    => 'Restoran indirimi kaldırıldı.',
        'discountPercentInvalid' => 'İndirim oranı 0’dan büyük ve en fazla 100 olmalıdır.',
        'discountUntilInvalid'   => 'Bitiş tarihi geçersiz.',
        'suspendedPanel'     => 'Aboneliğiniz askıda olduğu için bu sayfaya erişilemiyor.',
        'cycle' => [
            'trial'   => 'Deneme',
            'monthly' => 'Aylık',
            'yearly'  => 'Yıllık',
        ],
        'status' => [
            'trialing'  => 'Deneme',
            'active'    => 'Aktif',
            'past_due'  => 'Ödeme gecikti',
            'suspended' => 'Askıda',
            'ended'     => 'Sona erdi',
        ],
        'reason' => [
            'overdue'         => 'Vadesi geçmiş fatura',
            'trial_expired'   => 'Deneme süresi doldu',
            'no_subscription' => 'Aktif abonelik yok',
            'manual'          => 'Yönetici tarafından',
        ],
    ],

    'invoice' => [
        'discountInvalid'  => 'İndirim 0 ile dönem fiyatı arasında olmalıdır.',
        'notPending'       => 'Yalnızca ödenmemiş faturalarda bu işlem yapılabilir.',
        'methodInvalid'    => 'Ödeme yöntemi geçersiz.',
        'dateInvalid'      => 'Tarih geçersiz.',
        'referenceTooLong' => 'Ödeme referansı en fazla 100 karakter olabilir.',
        'paid'             => 'Fatura ödendi olarak işaretlendi.',
        'voided'           => 'Fatura iptal edildi.',
        'discountUpdated'  => 'Fatura indirimi güncellendi.',
        'status' => [
            'pending' => 'Ödenmedi',
            'overdue' => 'Gecikmiş',
            'paid'    => 'Ödendi',
            'void'    => 'İptal',
        ],
    ],

    'domain' => [
        'invalid'    => 'Geçerli bir alan adı girin (ör. menu.ornekrestoran.com).',
        'reserved'   => 'Bu alan adı kullanılamaz.',
        'taken'      => 'Bu alan adı başka bir restoranda tanımlı.',
        'notAllowed' => 'Restoranın paketi özel alan adını kapsamıyor.',
        'none'       => 'Restoranın tanımlı bir alan adı yok.',
        'saved'      => 'Alan adı kaydedildi.',
        'removed'    => 'Alan adı kaldırıldı.',
        'verified'   => 'Alan adı doğrulandı olarak işaretlendi.',
        'unverified' => 'Alan adı doğrulaması kaldırıldı.',
    ],

    // İşlem kayıtları ekranı: eylem kodu `nesne.fiil` (ör. invoice.pay). Önce `action`
    // altında tam eşleşme aranır; yoksa "nesne: fiil" olarak birleştirilir.
    'activity' => [
        'action' => [
            'auth' => ['login' => 'Giriş yaptı', 'logout' => 'Çıkış yaptı'],
        ],
        'subject' => [
            'language'           => 'Dil',
            'allergen'           => 'Alerjen',
            'menutemplate'       => 'Menü şablonu',
            'plan'               => 'Paket',
            'invoice'            => 'Fatura',
            'subscription'       => 'Abonelik',
            'restaurant'         => 'Restoran',
            'domain'             => 'Alan adı',
            'restaurantlanguage' => 'Restoran dilleri',
            'restaurantuser'     => 'Restoran kullanıcısı',
            'branch'             => 'Şube',
            'user'               => 'Kullanıcı',
            'category'           => 'Kategori',
            'product'            => 'Ürün',
            'profile'            => 'Profil',
        ],
        'verb' => [
            'create'         => 'Oluşturma',
            'update'         => 'Güncelleme',
            'delete'         => 'Silme',
            'toggle'         => 'Aktif/pasif',
            'reorder'        => 'Sıralama',
            'sync'           => 'Güncelleme',
            'save'           => 'Kaydetme',
            'verify'         => 'Doğrulama',
            'pay'            => 'Ödendi işaretleme',
            'void'           => 'İptal',
            'discount'       => 'İndirim',
            'change'         => 'Paket değişimi',
            'grace'          => 'Ek süre',
            'cancel'         => 'Dönem sonu iptali',
            'suspend'        => 'Askıya alma',
            'resume'         => 'Askıdan çıkarma',
            'password'       => 'Parola değişimi',
            'deleteImage'    => 'Görsel silme',
            'deleteIcon'     => 'İkon silme',
            'deleteLogo'     => 'Logo silme',
            'regenerateCode' => 'QR kodu yenileme',
        ],
    ],
];
