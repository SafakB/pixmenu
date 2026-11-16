# PixMenu — Abonelik, Faturalama ve Özel Alan Adı Planı

## 1. Context

PixMenu'de restoranlar şu an admin tarafından sınırsız ve süresiz açılıyor; paket, dönem, fatura veya tahsilat takibi yok. Restoranı kapatmanın tek yolu `restaurants.is_active = 0` (menü 404 + panel girişi tamamen kapalı). Özel alan adı özelliği de yok; menü yalnızca `/m/{public_code}` ile açılıyor.

Hedef: restoranlara paket tanımlamak, dönemsel fatura kesmek, ödenmeyen faturada gecikme eşiği sonunda restoranı **askıya almak** (menü kapanır, panel yalnızca abonelik/fatura sayfalarına açık kalır) ve paket haklarını (şube limiti, özel alan adı) sistemde zorlamak.

### Alınan kararlar

| Konu | Karar |
|---|---|
| Tahsilat | Manuel. Sistem fatura keser; admin "ödendi" işaretler. Owner'ın ödenmemiş faturası için panelde kart ödeme **ekranı** vardır (`panel/subscription/invoices/{id}/pay`); sanal POS (PayTR) henüz bağlı değildir — **TODO(PayTR)**: ödeme onayı `InvoiceService::markPaid(…, 'card', merchant_oid)` ile işlenecek. |
| Fatura niteliği | Sistem içi tahsilat kaydı + yazdırılabilir görünüm. Resmi e-fatura dışarıda kesilir. PDF yok. |
| Dönem | Aylık + yıllık, cron ile otomatik yenileme. |
| Demo | Süreli ücretsiz deneme (varsayılan 14 gün), fatura kesilmez; süre bitince askıya alınır. |
| Askıya alma | Menü kapanır, panel kısıtlı açık. `is_active`'ten ayrı bir durum. |
| Paket yönetimi | Paket tanımları yalnızca admin'de. Owner self-servis olarak yalnızca **deneme → ücretli** geçiş yapar (kayıt sırasında veya `panel/subscription/plans`'tan; `SubscriptionService::upgradeFromTrial`). Ücretli → ücretli değişiklik admin'dedir. |
| Paket değişimi | Hemen geçer, bugünden yeni dönem başlar, tam fatura kesilir (admin elle indirim girebilir). |
| Limit aşımı | Geçiş engellenir (fazla şube pasifleştirilmeden / alan adı kaldırılmadan paket düşürülemez). |
| Özel alan adı | Altyapı bu plana dahil. Restoran başına 1 alan adı; restorana bağlanır. |
| Özel menü tasarımı | Yalnızca paket bayrağı (`allow_custom_design`); teknik altyapısı ayrı plan. |
| Bildirim | E-posta + panel uyarı bandı. |

### Varsayımlar (değişirse ilgili bölüm güncellenir)

1. Fiyatlar KDV hariç tutulur; KDV oranı config'ten gelir (varsayılan %20). Seed fiyatları **yer tutucudur**, admin panelden düzenler.
2. Mevcut restoranlara migration sırasında bugünden başlayan Demo denemesi atanır; `DemoSeeder` restoranı Pro/aktif olur.
3. Askıdaki restoranın menüsü mevcut davranışla tutarlı olarak 404 döner (özel "geçici olarak kapalı" sayfası yok).
4. Faturalar silinmez; yalnızca iptal (`void`) edilir.
5. DNS yönlendirmesi ve SSL sertifikası sunucu/operasyon işidir (bkz. §9.4), kod kapsamı dışındadır.

---

## 2. Paketler (varsayılan seed)

| code | Şube limiti | Özel alan adı | Özel tasarım | Deneme | Aylık / Yıllık (yer tutucu) |
|---|---|---|---|---|---|
| `demo` | 1 | — | — | 14 gün | ücretsiz |
| `standard` | 1 | ✓ | — | — | admin belirler |
| `pro` | sınırsız | ✓ | — | — | admin belirler |
| `enterprise` | sınırsız | ✓ | ✓ | — | admin belirler |

Her pakette 1 restoran vardır (abonelik zaten restorana bağlıdır). Paketler veritabanında tutulur; admin fiyat/limit/ad düzenleyebilir, yeni paket ekleyebilir. Abonelikte kullanılan paket silinemez, yalnızca pasifleştirilir (mevcut dil/alerjen kuralıyla aynı).

---

## 3. Veritabanı

Yeni migration'lar `App\Database\BaseMigration` yardımcılarını (`idField`, `refField`, `flagField`, `timestampFields`, `createTranslationTable`) kullanır; mevcut bileşik FK deseni (`(id, restaurant_id)`) korunur.

### `plans` (global, admin)
`id`, `code` VARCHAR(30) UNIQUE, `max_branches` SMALLINT NULL (NULL = sınırsız), `allow_custom_domain` flag, `allow_custom_design` flag, `trial_days` SMALLINT NULL (dolu ise deneme paketi), `price_monthly` DECIMAL(10,2), `price_yearly` DECIMAL(10,2), `currency_code` CHAR(3), `is_featured` flag (pricing kartında "Popüler" şeridi), `is_active`, `sort_order`, timestamps.

### `plan_translations`
`createTranslationTable` ile: `name` VARCHAR(100), `description` TEXT NULL. `TranslationService` yeniden kullanılır.

### `subscriptions` (tenant)
`id`, `restaurant_id`, `plan_id` (RESTRICT), `billing_cycle` ENUM(`trial`,`monthly`,`yearly`), `status` ENUM(`trialing`,`active`,`past_due`,`suspended`,`ended`), `is_current` TINYINT NULL (güncel abonelikte 1, eskilerde NULL), `price` DECIMAL(10,2) (atama anındaki dönem fiyatı — paket fiyatı sonradan değişse de sabit), `currency_code`, `started_at` DATE, `current_period_start` DATE, `current_period_end` DATE, `cancel_at_period_end` flag(0), `grace_days` SMALLINT NULL (restorana özel gecikme eşiği; NULL = config), `suspended_at`, `ended_at`, `trial_reminder_sent_at`, `notes`, timestamps.
Anahtarlar: UNIQUE(`restaurant_id`,`is_current`) → restoran başına tek güncel abonelik DB seviyesinde garanti; UNIQUE(`id`,`restaurant_id`) (bileşik FK hedefi).

### `invoices` (tenant)
`id`, `restaurant_id`, `subscription_id`, `number` VARCHAR(30) UNIQUE NULL (insert sonrası `PM-{YYYY}-{id 6 hane}`), `plan_id`, `description` VARCHAR(255) (paket adı + dönem anlık görüntüsü), `period_start` DATE, `period_end` DATE, `subtotal`, `discount`, `tax_rate` DECIMAL(5,2), `tax_amount`, `total` DECIMAL(10,2), `currency_code`, `status` ENUM(`pending`,`paid`,`void`), `issued_at` DATE, `due_date` DATE, `paid_at` DATETIME NULL, `payment_method` ENUM(`bank_transfer`,`cash`,`card`,`other`) NULL, `payment_reference` VARCHAR(100) NULL, `paid_marked_by` (admins.id) NULL, `issued_notified_at`, `reminder_notified_at`, `overdue_notified_at`, `notes`, timestamps.
Anahtarlar: FK(`subscription_id`,`restaurant_id`) → `subscriptions(id, restaurant_id)`; UNIQUE(`subscription_id`,`period_start`) → cron aynı dönemi iki kez faturalayamaz. Soft delete yok.
"Gecikmiş" ayrı bir durum değildir: `status = pending AND due_date < bugün`.

### `restaurant_domains` (tenant)
`id`, `restaurant_id` UNIQUE (restoran başına 1), `domain` VARCHAR(190) UNIQUE (küçük harf, şemasız, `www`'siz saklanır), `is_active`, `verified_at` NULL, timestamps.

### `restaurants` (değişiklik)
`suspended_at` DATETIME NULL, `suspension_reason` ENUM(`overdue`,`trial_expired`,`no_subscription`,`manual`) NULL. Tek yazan: `SubscriptionService`. Denormalize edilmesinin nedeni: `MenuService::build` ve `PanelAuth` zaten `restaurants` satırını okuyor; ek join gerekmez.

---

## 4. Yapılandırma — `app/Config/Billing.php`

| Ayar | Varsayılan | Anlam |
|---|---|---|
| `graceDays` | 7 | **Gecikme eşiği**: vadeden kaç gün sonra askıya alınır |
| `paymentTermDays` | 7 | Vade = kesim tarihi + bu değer |
| `invoiceLeadDays` | 7 | Yenileme faturası dönem bitiminden kaç gün önce kesilir (vade = dönem sonu olur) |
| `reminderDaysBeforeDue` | 3 | Vade hatırlatma e-postası |
| `trialReminderDays` | 3 | Deneme bitiş hatırlatması |
| `taxRate` | 20.00 | KDV % |
| `invoicePrefix` | `PM` | Fatura no öneki |
| `billingEmailBcc` | `''` | Bildirimlerin kopyası (admin) |

E-posta için `.env`'de SMTP (`email.fromEmail`, `email.SMTPHost` …) tanımlanır; `app/Config/Email.php` şu an boş.

---

## 5. Durum makinesi ve zaman çizelgesi

```
(yeni restoran) ──► trialing ──(deneme bitti, ücretli pakete geçilmedi)──► suspended [trial_expired]
                        │
       admin paket atar ▼
                     active ──(vadesi geçmiş açık fatura)──► past_due ──(vade + graceDays aşıldı)──► suspended [overdue]
                        ▲                                        │                                        │
                        └────────── tüm gecikmiş faturalar ödendi ◄────────────────────────────────────────┘
herhangi biri ──(paket değişimi / iptal)──► ended  (is_current = NULL)
```

Ücretli dönem örneği (aylık, varsayılan ayarlar): dönem 1–30 Kasım → 23 Kasım'da Aralık faturası kesilir (e-posta) → 27 Kasım hatırlatma → vade 30 Kasım → 1 Aralık'ta dönem ilerler, fatura hâlâ açıksa `past_due` + gecikme e-postası → 8 Aralık'ta (vade + 7) `suspended`.

Kurallar:
- Dönem ilerlemesi **zamana** bağlıdır (cron, dönem bitince bir sonrakine geçirir); ödeme yalnızca durumu etkiler.
- Askıya alma kontrolü **restoran düzeyindedir**: eski abonelikten kalan açık fatura da sayılır.
- Askıdaki abonelik için yenileme faturası kesilmez. Ödemeyle yeniden aktifleşince, dönem bu arada bitmişse cron bir sonraki çalışmada bugünden başlayan yeni dönemin faturasını keser.
- `cancel_at_period_end = 1` ise dönem sonunda yenilenmez → `ended` + restoran `no_subscription` nedeniyle askıya alınır.
- Paket değişimi: eski abonelik `ended`; yeni abonelik bugün başlar, tam dönem faturası kesilir (formda isteğe bağlı indirim tutarı). Eski aboneliğin açık faturaları otomatik iptal edilmez; admin ekranda uyarılır ve isterse `void` eder.
- Demo → ücretli geçiş de bir paket değişimidir.

---

## 6. Servisler ve kurallar

Mevcut desenler korunur: `Transactional::atomic()`, `BusinessRuleException` / `ValidationException`, `Scope::admin($id)` / `Scope::tenant()`, `TenantModel`.

| Sınıf | Sorumluluk |
|---|---|
| `Models/PlanModel`, `PlanTranslationModel` | Global (TenantModel değil) |
| `Models/SubscriptionModel`, `InvoiceModel`, `RestaurantDomainModel` | `TenantModel` — panelde otomatik restoran kapsamı |
| `Services/PlanService` | Paket CRUD, toggle, kullanımdaysa silme engeli |
| `Services/SubscriptionService` | `start()`, `changePlan()`, `cancel()`, `suspend()`, `resume()`, `entitlements(restaurantId)`, `assertBranchLimit()`, `assertPlanFits()` |
| `Services/InvoiceService` | `issue()` (numara üretimi, KDV hesabı), `markPaid()`, `void()` |
| `Services/BillingRunner` | Cron adımları; "bugün" parametre olarak verilir (test edilebilirlik) |
| `Services/BillingNotifier` | E-postalar (`service('email')`, view: `emails/billing/*`) |
| `Services/DomainService` | Alan adı tanımlama/doğrulama, `resolve(host)` (cache'li) |

### Paket haklarının zorlanması
- **Şube limiti:** aktif (silinmemiş, `is_active = 1`) şube sayısı. `BranchService::create/update/toggle` içinde tek noktadan `SubscriptionService::assertBranchLimit()` — admin ve panel aynı servisi kullandığı için ikisi de kapsanır.
- **Özel alan adı:** `DomainService::save()` paket `allow_custom_domain` değilse reddeder.
- **Paket düşürme:** `changePlan()` hedef paketin limitlerini kontrol eder (`assertPlanFits`): aktif şube sayısı > limit veya tanımlı alan adı varken hakkı olmayan pakete geçiş → açıklayıcı `BusinessRuleException`.
- **Yeni restoran:** `RestaurantService::create()` `plan_id` + `billing_cycle` alır, aynı transaction'da aboneliği başlatır (varsayılan Demo).

### Askıya alma etkisi
- **Menü:** `MenuService::build()` sorgusuna `r.suspended_at IS NULL` eklenir (`app/Services/MenuService.php:45-56`); askıya alma/açmada `MenuService::invalidateRestaurant()` çağrılır.
- **Panel:** `PanelAuth::isUsable()` değişmez (askıdaki restoran giriş yapabilir). `TenantContext`'e `suspended` bilgisi eklenir (`PanelAuthFilter` set eder). Yeni `panelsubscription` filtresi içerik rotalarına (şube, kategori, ürün, kullanıcı, restoran ayarları) uygulanır: askıdaysa HTML → `panel/subscription`'a yönlendirme, JSON → 403. Serbest rotalar: dashboard, profil, çıkış, abonelik/fatura sayfaları. Manager için yalnızca bilgi sayfası.
- **`is_active`** aynen kalır: admin'in elle tam kapatması (panel girişi dahil) içindir.

---

## 7. Zamanlanmış görev — `php spark billing:run`

CodeIgniter 4'te Laravel'deki gibi yerleşik zamanlayıcı yoktur; komut `app/Commands/BillingRun.php` (`BaseCommand`) olarak yazılır ve işletim sistemi cron'u ile günde bir kez çalıştırılır:

```
0 3 * * * cd /var/www/pixmenu && php spark billing:run >> writable/logs/billing.log 2>&1
```
(Geliştirmede Windows Görev Zamanlayıcı veya elle çalıştırma.)

Seçenekler: `--dry-run` (yalnızca raporlar), `--date=YYYY-MM-DD` (test/geriye dönük çalıştırma).

Adımlar (hepsi idempotent; her restoran kendi transaction'ında, biri hata verirse loglanır ve diğerleri devam eder):
1. **Dönem ilerletme:** `current_period_end < bugün` olan aktif/past_due abonelikler → `cancel_at_period_end` ise sonlandır, değilse sonraki döneme geçir.
2. **Yenileme faturası:** dönem bitimine `invoiceLeadDays` kalan aboneliklere sonraki dönem faturası (UNIQUE anahtar çift kesimi engeller) + e-posta.
3. **Hatırlatmalar:** vadeye `reminderDaysBeforeDue` kalan faturalar; bitişine `trialReminderDays` kalan denemeler.
4. **Gecikme:** vadesi geçmiş açık faturası olan abonelik → `past_due` + e-posta.
5. **Askıya alma:** `due_date + graceDays < bugün` olan açık faturası bulunan restoran → `suspended` (`overdue`) + e-posta + cache temizliği.
6. **Deneme bitişi:** süresi dolan `trialing` → `suspended` (`trial_expired`) + e-posta.

Komut sonunda özet tablo basar (kesilen fatura, hatırlatma, askıya alınan sayısı).

---

## 8. Ekranlar ve rotalar

Tüm view'lar `theme-skill` ile Velzon'a uygun yapılır; `docs/VIEW_CONTRACT.md` yeni view'larla güncellenir.

### Admin
| Rota | İşlev |
|---|---|
| `admin/plans` (+ `new`, `(:num)/edit`, `toggle`, `delete`, `reorder`) | Paket yönetimi |
| `admin/restaurants/(:num)/subscription` | Güncel abonelik kartı, geçmiş, paket değiştir, dönem sonunda iptal, elle askıya al/aç, gecikme eşiği istisnası |
| `admin/restaurants/(:num)/domain` | Alan adı tanımla / kaldır / doğrulandı işaretle |
| `admin/invoices` (`?status=&overdue=1&restaurant=`) | Tüm faturalar, filtreli |
| `admin/invoices/(:num)` (+ `pay`, `void`) | Fatura detayı/yazdırma, ödendi işaretleme (yöntem, referans, tarih), iptal |

Ayrıca: restoran oluşturma formuna paket + dönem seçimi; restoran listesi ve detayında paket/durum rozeti; dashboard'a "geciken fatura", "bu hafta biten deneme", "askıdaki restoran" widget'ları; sidebar'a "Paketler" ve "Faturalar".

### Restoran paneli (owner)
| Rota | İşlev |
|---|---|
| `panel/subscription` | Paket, dönem, durum, kullanım (şube 1/1), fatura listesi |
| `panel/subscription/invoices/(:num)` | Fatura detayı (yazdırılabilir) |
| `panel/subscription/plans` | Paket karşılaştırma; denemedeki owner için "Bu pakete geç" (`POST panel/subscription/upgrade`) |
| `panel/subscription/invoices/(:num)/pay` | Kart ödeme ekranı (GET) + stub POST — **TODO(PayTR)** |

- **Paket karşılaştırma sayfası tasarım referansı:** `admin_template/pages-pricing.html` (aylık/yıllık geçiş düğmesi, paket kartları, özellik listesi, "Popüler" şeridi). Kartlar `plans` tablosundan beslenir (`partials/billing/plan_card`, landing ve kayıt sihirbazıyla ortak); mevcut paket "Paketiniz" rozetiyle işaretlenir. Denemedeki (veya aboneliği olmayan, elle askıya alınmamış) owner ücretli bir pakete hemen geçer: eski abonelik `ended`, yeni dönem bugünden başlar, ilk dönem faturası `pending` kesilir ve ödeme ekranına yönlendirilir. Ücretli paketteki owner "iletişime geçin" metnini görür.
- **Self-servis kayıt (`/kayit`):** seçilen paketle `RestaurantService::create` aboneliği başlatır; ücretli pakette ilk fatura `pending` kesilir (vade +`paymentTermDays`). Ödeme yapılmazsa mevcut kurallarla `past_due` → askı işler; yani ücretli kayıt ödemesiz olarak en fazla vade + gecikme eşiği (varsayılan ~14 gün) kullanılabilir.
- Panel layout'unda uyarı bandı: deneme bitimine N gün kaldı / vadesi geçmiş fatura var / abonelik askıda.
- Şube listesinde limit doluysa "Yeni şube" düğmesi pasif + açıklama (`canAddBranch`).

---

## 9. Özel alan adı

### 9.1 Çözümleme
`app/Config/Routes.php` başında istek host'u uygulama host'u (`baseURL` + `allowedHostnames`) değilse **yalnızca** şu iki rota tanımlanır ve dosya orada biter:
```
GET /            → Menu\DomainMenuController::index
GET /(:segment)  → Menu\DomainMenuController::show/$1   (şube slug'ı)
```
Böylece özel alan adında `/admin`, `/panel`, `/api` hiç var olmaz (oturum/çerez yüzeyi açılmaz).

### 9.2 Davranış
- `DomainService::resolve(host)`: `www.` atılır; `restaurant_domains` + restoran aktif, askıda değil, paket `allow_custom_domain`, alan adı aktif. Sonuç kısa süreli cache'lenir; bulunamazsa 404.
- Tek aktif şube → kökte doğrudan menü. Birden fazla şube → kökte şube seçim sayfası (`menu/branches`), menüler `alanadi.com/{şube-slug}`.
- Menü üretimi mevcut `MenuService::build(public_code, …)` ile yapılır; `MenuController` içindeki dil/çerez mantığı ortak bir metoda çıkarılıp iki controller'da kullanılır.
- `/m/{public_code}` adresleri çalışmaya devam eder (basılı QR kodlar bozulmaz).

### 9.3 Dikkat edilecekler
- `asset_url()` / `upload_url()` özel alan adında doğru çalışmalı (göreli kök yol veya ana alan adına mutlak URL) — uygulama sırasında doğrulanır.
- Alan adı doğrulaması: kullanıcı girdisi normalize edilir, uygulamanın kendi host'ları ve ayrılmış adlar reddedilir.

### 9.4 Operasyon (kod dışı)
Müşteri alan adını CNAME/A kaydıyla sunucuya yönlendirir; web sunucusunda varsayılan (catch-all) vhost uygulamaya bakar; SSL için otomatik sertifika (ör. Caddy on-demand TLS veya Let's Encrypt) kurulur. Admin, yönlendirme tamamlanınca alan adını "doğrulandı" işaretler.

---

## 10. Uygulama fazları

Her faz sonunda `php spark migrate:refresh` hatasız ve `vendor/bin/phpunit` yeşil olmalıdır.

| # | Faz | Kapsam | Kabul |
|---|---|---|---|
| 1 | Şema ve temel | Migration'lar, modeller, `Config/Billing`, `PlanSeeder`, mevcut restoranlara demo ataması, `DemoSeeder` güncellemesi | Şema testi + tenant izolasyon testleri |
| 2 | Paket ve abonelik | `PlanService`, `SubscriptionService`, admin paket CRUD, restoran formunda paket seçimi, abonelik ekranı, şube limiti ve paket düşürme kuralları | Limit/düşürme testleri |
| 3 | Faturalama | `InvoiceService`, admin fatura listesi/detay, ödendi işaretleme, iptal | Numara, KDV, ödeme sonrası durum testleri |
| 4 | Askıya alma ve cron | `restaurants.suspended_at`, `MenuService` koşulu, `panelsubscription` filtresi, `BillingRunner`, `billing:run` | `--date` ile uçtan uca senaryo testi, idempotentlik |
| 5 | Panel ekranları | Abonelik, fatura, paket karşılaştırma (pricing), uyarı bandı, şube düğmesi | Owner/manager erişim testleri |
| 6 | Bildirimler | `BillingNotifier`, e-posta şablonları, SMTP ayarı | Tekrar gönderilmeme testi |
| 7 | Özel alan adı | Tablo ekranı, host çözümleme, `DomainMenuController`, şube seçim sayfası | Host bazlı feature testleri |
| 8 | Dokümantasyon | `PIXMENU_BACKEND_SPEC.md` (§4, §9, §10, §15), `docs/VIEW_CONTRACT.md` | — |

### Değişecek mevcut dosyalar (başlıcalar)
`app/Config/Routes.php`, `app/Config/Filters.php`, `app/Services/RestaurantService.php` (create), `app/Services/BranchService.php` (limit), `app/Services/MenuService.php` (askı koşulu), `app/Libraries/Tenant/TenantContext.php`, `app/Filters/PanelAuthFilter.php`, `app/Controllers/Admin/RestaurantController.php`, `app/Controllers/Menu/MenuController.php`, `app/Views/partials/layout/*` (sidebar öğeleri, uyarı bandı), `app/Language/{tr,en}/App.php`, `app/Database/Seeds/*`, `tests/_support/Fixture.php`.

---

## 11. Doğrulama

- **Otomatik:** `vendor/bin/phpunit` — yeni testler: tenant izolasyonu (başka restoranın faturası/aboneliği/alan adı görülemez), şube limiti (admin + panel), paket düşürme engeli, fatura kesimi ve çift kesim engeli, ödeme → yeniden aktifleşme, askıda menü 404 + panel kısıtı, deneme bitişi, özel alan adı çözümleme.
- **Cron senaryosu (elle):** `php spark billing:run --dry-run`, ardından `--date` ile ileri tarihler verilerek: fatura kesimi → hatırlatma → `past_due` → askıya alma; admin'den "ödendi" işaretle → menü ve panel geri açılır.
- **Tarayıcı:** admin'de paket oluştur/ata/değiştir, fatura öde/iptal; panelde owner olarak abonelik, fatura ve paket karşılaştırma sayfaları; askıdayken içerik sayfalarına erişimin abonelik sayfasına yönlendiği.
- **Özel alan adı:** `hosts` dosyasına test alan adı eklenip WAMP vhost'u üzerinden tek şubeli ve çok şubeli restoranla denenir; aynı host'ta `/admin` ve `/panel` 404 dönmelidir.

## 12. Kapsam dışı

Online ödeme tahsilatı (sanal POS — ekran hazır, PayTR entegrasyonu TODO), resmi e-fatura entegrasyonu ve PDF, owner'ın ücretli paketler arasında kendi kendine geçişi, dönem içi kıst (proration) hesabı, özel menü tasarımı/tema altyapısı, alt alan adı (`restoran.pixmenu.com`), otomatik DNS/SSL doğrulaması.
