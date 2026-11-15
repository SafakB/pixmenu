# PixMenu — Backend Teknik Spesifikasyonu (AI Ajanları İçin)

> Bu doküman PixMenu restoran menü sisteminin **yalnızca backend** kısmını tanımlar. Frontend (HTML, Bootstrap, CSS, vanilla JS view içerikleri) bu dokümanın kapsamı dışındadır; backend yalnızca view dosya adlarını ve view'lara geçirilen veriyi belirler.
>
> Bu dokümanı okuyan her AI ajanı **Bölüm 2 (Değişmez Kurallar)** maddelerini her görevde uygulamak zorundadır. Bir kural ile bir görev çelişirse kural kazanır; çelişkiyi raporla, kendi başına esnetme.

---

## 1. Genel Bakış

| Konu | Değer |
|---|---|
| Framework | CodeIgniter 4 (son kararlı 4.x, `codeigniter4/appstarter`) |
| PHP | 8.2+ (`intl`, `mbstring`, `gd`, `mysqli`, `fileinfo` eklentileri) |
| Veritabanı | MySQL / MariaDB, `utf8mb4` / `utf8mb4_unicode_ci`, InnoDB |
| Paket yönetimi | Composer |
| Frontend (kapsam dışı) | HTML, Bootstrap 5, CSS, vanilla JavaScript |
| Panel sayısı | 2 ayrı panel: **Admin** (`/admin`) ve **Restoran Paneli** (`/panel`) + herkese açık menü (`/m`, `/api/v1`) |

### 1.1 Roller

| Rol | Tablo | Kapsam |
|---|---|---|
| `admin` | `admins` | Sistem yönetimi; ne yapabileceğini `admins.role` belirler (§7.4): `super_admin` (her şey), `manager` / Yönetici (restoran, şube, kullanıcı, dil, alerjen, şablon; faturalar salt okunur), `accountant` / Muhasebe (fatura ödeme/indirim, paket değiştirme, ek süre, restoran indirimi; kritik işlemler hariç). |
| `owner` (restoran sahibi) | `restaurant_users` | Yalnızca kendi restoranı. Tüm şubeler, kategoriler, ürünler, kendi restoranının kullanıcıları. |
| `manager` (şube yöneticisi) | `restaurant_users` | Yalnızca kendi restoranında **kendisine atanmış şubeler**in kategori ve ürünleri. |
| Ziyaretçi | — | Yalnızca aktif şubenin aktif menüsünü okur. |

### 1.2 Kavramsal Hiyerarşi

```
Language (global, admin)        Allergen (global, admin)
        │                               │
Restaurant ──< RestaurantLanguage      │
   │                                    │
   ├──< RestaurantUser ──< RestaurantUserBranch
   │
   └──< Branch (default_language_id)
            │
            └──< Category
                    │
                    └──< Product >──< ProductAllergen >── Allergen
```

Her çevrilebilir varlığın bir `*_translations` tablosu vardır.

---

## 2. Değişmez Kurallar (Her Görevde Geçerli)

### 2.1 Tenant İzolasyonu — EN KRİTİK KURAL

Bir restoran, başka bir restoranın verisini **hiçbir koşulda** göremez, değiştiremez, varlığını dahi tespit edemez.

1. **`restaurant_id` asla request'ten okunmaz.** Restoran panelinde `restaurant_id` yalnızca oturumdan, `TenantContext` servisi üzerinden alınır. POST/GET/JSON body/URL içinde `restaurant_id` gelirse yok sayılır.
2. **Tenant'a ait her tabloda `restaurant_id` kolonu vardır** (`branches`, `categories`, `products`, `product_allergens`, `restaurant_users`, `restaurant_user_branches`, `restaurant_languages`). Bu bilerek yapılmış denormalizasyondur; kaldırma.
3. **Restoran panelindeki tüm sorgular `TenantModel` türevleriyle yapılır** ve otomatik olarak `WHERE <tablo>.restaurant_id = :tenant` filtresi alır (bkz. §6.2). Panel controller'larında `db_connect()` / ham `$db->query()` / `$db->table()` kullanmak **yasaktır**.
4. **URL'deki her ID sahiplik kontrolünden geçer.** `/panel/branches/15/products/88` isteğinde önce şube 15'in bu tenant'a (ve manager ise bu kullanıcıya) ait olduğu, sonra ürün 88'in **hem bu tenant'a hem şube 15'e** ait olduğu doğrulanır.
5. **Form ile gelen her yabancı anahtar doğrulanır.** `category_id`, `allergen_ids[]`, `branch_ids[]`, `language` kodları vb. tenant kapsamında var olduğu kontrol edilmeden yazılmaz.
6. **Başka tenant'a ait kayıt için cevap her zaman 404'tür** (403 değil). Var olup olmadığı sızdırılmaz. Hata mesajı "kayıt bulunamadı" ile aynıdır.
7. **Çeviri tablolarına doğrudan erişilmez.** Önce ebeveyn kayıt (`products`, `categories`…) tenant kapsamlı model ile yüklenir, sonra çeviriler o ebeveynin ID'si ile okunur/yazılır.
8. **Veritabanı seviyesinde bileşik foreign key'ler** (bkz. §4) uygulama hatalarına karşı ikinci savunma hattıdır; migration'lardan çıkarılmaz.
9. **Dosya yolları tenant'a göre ayrılır**: `public/uploads/r/{restaurant_id}/...`. Dosya silme/değiştirme işlemlerinde yol, DB'deki kayıttan alınır; request'ten gelen yol kullanılmaz.
10. Her yeni panel endpoint'i için §14.1'deki izolasyon testleri yazılmadan iş "tamamlandı" sayılmaz.

### 2.2 Oturum Ayrımı

- Admin ve restoran kullanıcıları **ayrı tablolarda** tutulur (`admins`, `restaurant_users`).
- Oturum anahtarları ayrıdır: `admin_auth` ve `panel_auth`. Bir anahtar diğerinin filtresinde asla kabul edilmez.
- Aynı tarayıcıda iki oturum aynı anda açık olabilir; birbirini etkilemez. Logout yalnızca kendi anahtarını siler.
- Login sonrası `session()->regenerate(true)` zorunlu.

### 2.3 Çok Dillilik

- Kullanıcıya görünen **her metin alanı** (isim, açıklama, adres vb.) çeviri tablosunda tutulur; ana tabloda metin kolonu bulunmaz.
- Fiyat, sıra, durum, görsel gibi dilden bağımsız alanlar ana tablodadır.

### 2.4 Kodlama Standartları

- `declare(strict_types=1);` her PHP dosyasında.
- PSR-12, PHP 8.2 tip bildirimleri, `final` sınıflar tercih edilir.
- Controller'lar ince: doğrulama + servis çağrısı + yanıt. İş mantığı `app/Services` altında.
- Toplu atamada `$this->request->getPost()` doğrudan modele verilmez; alanlar tek tek seçilir (`getPost(['price','sort_order',...])`).
- Çok tablolu yazmalar (ürün + çeviriler + alerjenler) **transaction** içinde.
- Tüm çıktılar view'da `esc()` ile basılır (view kapsam dışı ama controller ham HTML üretmez).
- Kimlik bilgileri yalnızca `.env` içinde; koda veya bu dokümanın dışındaki dosyalara yazılmaz.

---

## 3. Kurulum

### 3.1 Proje Oluşturma

```bash
composer create-project codeigniter4/appstarter pixmenu
cd pixmenu
composer require enshrined/svg-sanitize
composer require --dev fakerphp/faker
cp env .env
```

| Paket | Amaç |
|---|---|
| `codeigniter4/appstarter` | Framework |
| `enshrined/svg-sanitize` | Alerjen ikonu SVG yüklemelerinde XSS temizliği |
| `fakerphp/faker` (dev) | Demo seeder |

Kimlik doğrulama için harici paket (Shield vb.) **kullanılmaz**; iki ayrı panel ve tenant bağlamı nedeniyle hafif, özel bir auth katmanı yazılır (§7).

### 3.2 `.env`

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'

# Verilen host "localhost"tur. MySQLi, "localhost" görünce TCP yerine
# Unix socket kullanır ve port ayarını YOK SAYAR. 3307 portundaki
# sunucuya bağlanmak için 127.0.0.1 kullanılır. Bağlantı yine de
# başarısız olursa localhost + socket yolunu kontrol et.
database.default.hostname = 127.0.0.1
database.default.port     = 3307
database.default.database = pixmenu
database.default.username = db_user
database.default.password = '153246DbD!'
database.default.DBDriver = MySQLi
database.default.charset  = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci

# Test veritabanı (izolasyon testleri için; ayrıca oluşturulmalı)
database.tests.hostname = 127.0.0.1
database.tests.port     = 3307
database.tests.database = pixmenu_test
database.tests.username = db_user
database.tests.password = '153246DbD!'
database.tests.DBDriver = MySQLi
database.tests.charset  = utf8mb4
database.tests.DBCollat = utf8mb4_unicode_ci

# İlk admin (AdminSeeder kullanır)
pixmenu.adminEmail    = 'admin@pixmenu.local'
pixmenu.adminPassword = 'DEGISTIR_guclu_bir_sifre'

encryption.key = # php spark key:generate ile üretilecek
```

- `.env` dosyası `.gitignore` içinde olmalı (appstarter'da varsayılan olarak var; kontrol et).
- `php spark key:generate` çalıştırılır.

### 3.3 `app/Config` Ayarları

| Dosya | Ayar |
|---|---|
| `App.php` | `defaultLocale = 'tr'`, `negotiateLocale = false` (dil seçimi §8'deki kendi mantığımızla yapılır) |
| `Session.php` | `driver = DatabaseHandler` veya `FileHandler`; `cookieName = 'pixmenu_session'`, `expiration = 7200`, `matchIP = false`, `regenerateDestroy = true` |
| `Cookie.php` | `httponly = true`, `samesite = 'Lax'`, production'da `secure = true` |
| `Security.php` | `csrfProtection = 'session'`, `tokenRandomize = true`, `regenerate = true` |
| `Filters.php` | global `before`: `csrf` (except `api/*`), `invalidchars`; global `after`: `secureheaders`, `toolbar` (yalnız development) |
| `Routing.php` | `autoRoute = false` (zorunlu — yalnızca tanımlı rotalar çalışır) |

---

## 4. Veritabanı Şeması

Tüm tablolar migration ile oluşturulur (`app/Database/Migrations`). Engine InnoDB, charset `utf8mb4`. `created_at`, `updated_at` `DATETIME NULL`; soft delete olan tablolarda `deleted_at DATETIME NULL`.

### 4.1 Global Tablolar (Admin)

#### `admins`
| Kolon | Tip | Not |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| name | VARCHAR(100) | |
| email | VARCHAR(190) | UNIQUE |
| password_hash | VARCHAR(255) | `password_hash(PASSWORD_DEFAULT)` |
| role | VARCHAR(30) | `super_admin` \| `manager` \| `accountant` (`Config\AdminRoles`); tanımsız rol giriş yapamaz |
| is_active | TINYINT(1) | default 1 |
| last_login_at | DATETIME NULL | |
| password_changed_at | DATETIME NULL | Bu andan önce açılmış oturumlar düşer |
| created_at, updated_at | DATETIME NULL | |

Admin hesapları web arayüzünden **yönetilmez**; yalnızca `php spark admin:create | admin:list | admin:update` (§13). Son aktif süper admin pasifleştirilemez / rolü düşürülemez.

#### `languages`
| Kolon | Tip | Not |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| code | VARCHAR(10) | UNIQUE, ISO 639-1 (+ opsiyonel bölge: `tr`, `en`, `de`, `ar`, `pt-BR`) |
| name | VARCHAR(60) | İngilizce adı (`Turkish`) |
| native_name | VARCHAR(60) | Kendi dilindeki adı (`Türkçe`) |
| direction | ENUM('ltr','rtl') | default `ltr` |
| flag_path | VARCHAR(255) NULL | opsiyonel |
| is_active | TINYINT(1) | default 1 |
| sort_order | SMALLINT UNSIGNED | default 0 |
| created_at, updated_at | | |

#### `allergens`
| Kolon | Tip | Not |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| code | VARCHAR(40) | UNIQUE, makine adı (`gluten`, `milk`) |
| icon_path | VARCHAR(255) NULL | `public/uploads/allergens/...` |
| is_active | TINYINT(1) | default 1 |
| sort_order | SMALLINT UNSIGNED | default 0 |
| created_at, updated_at | | |

#### `allergen_translations`
| Kolon | Tip | Not |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| allergen_id | INT UNSIGNED | FK → allergens.id ON DELETE CASCADE |
| language_id | INT UNSIGNED | FK → languages.id ON DELETE CASCADE |
| name | VARCHAR(100) | |
| description | VARCHAR(500) NULL | |
| | | UNIQUE (allergen_id, language_id) |

### 4.2 Restoran Tabloları

#### `restaurants`
| Kolon | Tip | Not |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| slug | VARCHAR(120) | UNIQUE |
| default_language_id | INT UNSIGNED | FK → languages.id (RESTRICT). `restaurant_languages` içinde olmalı |
| currency_code | CHAR(3) | default `TRY` (ISO 4217) |
| logo_path | VARCHAR(255) NULL | |
| is_active | TINYINT(1) | default 1 |
| created_at, updated_at, deleted_at | | |

#### `restaurant_translations`
| Kolon | Tip | Not |
|---|---|---|
| id | PK | |
| restaurant_id | INT UNSIGNED | FK CASCADE |
| language_id | INT UNSIGNED | FK CASCADE |
| name | VARCHAR(150) | |
| description | TEXT NULL | |
| | | UNIQUE (restaurant_id, language_id) |

#### `restaurant_languages`
Restoranın kullanabileceği dilleri belirler. Restoran ve şubelerinin tüm içerikleri yalnızca bu dillerde girilebilir.

| Kolon | Tip | Not |
|---|---|---|
| restaurant_id | INT UNSIGNED | FK → restaurants.id CASCADE |
| language_id | INT UNSIGNED | FK → languages.id RESTRICT |
| sort_order | SMALLINT UNSIGNED | default 0 |
| is_active | TINYINT(1) | default 1 (pasif dil menüde gösterilmez, veri korunur) |
| | | PK (restaurant_id, language_id) |

#### `restaurant_users`
| Kolon | Tip | Not |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| restaurant_id | INT UNSIGNED | FK CASCADE |
| name | VARCHAR(100) | |
| email | VARCHAR(190) | **Global UNIQUE** (login'de restoran seçtirilmez) |
| password_hash | VARCHAR(255) | |
| role | ENUM('owner','manager') | |
| is_active | TINYINT(1) | default 1 |
| last_login_at | DATETIME NULL | |
| created_at, updated_at, deleted_at | | |
| | | UNIQUE (id, restaurant_id) — bileşik FK hedefi |

#### `branches`
| Kolon | Tip | Not |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| restaurant_id | INT UNSIGNED | FK CASCADE |
| slug | VARCHAR(120) | UNIQUE (restaurant_id, slug) |
| public_code | CHAR(10) | **Global UNIQUE**, rastgele (`[a-z0-9]`), QR/menü URL'sinde kullanılır, tahmin edilemez |
| default_language_id | INT UNSIGNED | FK → languages.id RESTRICT. Restoranın aktif dillerinden biri olmak zorunda |
| phone | VARCHAR(30) NULL | |
| is_active | TINYINT(1) | default 1 |
| sort_order | SMALLINT UNSIGNED | default 0 |
| created_at, updated_at, deleted_at | | |
| | | UNIQUE (id, restaurant_id) — bileşik FK hedefi |

#### `branch_translations`
| Kolon | Tip | Not |
|---|---|---|
| id | PK | |
| branch_id | INT UNSIGNED | FK CASCADE |
| language_id | INT UNSIGNED | FK CASCADE |
| name | VARCHAR(150) | |
| address | VARCHAR(500) NULL | |
| description | TEXT NULL | |
| | | UNIQUE (branch_id, language_id) |

#### `restaurant_user_branches`
Yalnızca `manager` rolü için anlamlıdır. `owner` tüm şubelere erişir; bu tabloya yazılmaz.

| Kolon | Tip | Not |
|---|---|---|
| restaurant_id | INT UNSIGNED | |
| user_id | INT UNSIGNED | |
| branch_id | INT UNSIGNED | |
| | | PK (user_id, branch_id) |
| | | FK (user_id, restaurant_id) → restaurant_users(id, restaurant_id) CASCADE |
| | | FK (branch_id, restaurant_id) → branches(id, restaurant_id) CASCADE |

> Bileşik FK sayesinde bir kullanıcı başka restoranın şubesine **DB seviyesinde** atanamaz.

### 4.3 Menü Tabloları

#### `categories`
| Kolon | Tip | Not |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| restaurant_id | INT UNSIGNED | |
| branch_id | INT UNSIGNED | |
| image_path | VARCHAR(255) NULL | |
| is_active | TINYINT(1) | default 1 |
| sort_order | SMALLINT UNSIGNED | default 0 |
| created_at, updated_at, deleted_at | | |
| | | FK (branch_id, restaurant_id) → branches(id, restaurant_id) CASCADE |
| | | UNIQUE (id, branch_id) — ürün FK hedefi |
| | | INDEX (branch_id, is_active, sort_order) |

#### `category_translations`
`category_id`, `language_id`, `name VARCHAR(150)`, `description TEXT NULL`, UNIQUE (category_id, language_id), FK CASCADE.

#### `products`
| Kolon | Tip | Not |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| restaurant_id | INT UNSIGNED | |
| branch_id | INT UNSIGNED | |
| category_id | INT UNSIGNED | |
| price | DECIMAL(10,2) | ≥ 0 |
| image_path | VARCHAR(255) NULL | büyük görsel |
| thumb_path | VARCHAR(255) NULL | küçük görsel |
| is_active | TINYINT(1) | default 1 |
| sort_order | SMALLINT UNSIGNED | default 0 |
| created_at, updated_at, deleted_at | | |
| | | FK (branch_id, restaurant_id) → branches(id, restaurant_id) CASCADE |
| | | FK (category_id, branch_id) → categories(id, branch_id) CASCADE |
| | | INDEX (category_id, is_active, sort_order) |

> İkinci bileşik FK, bir ürünün başka şubenin kategorisine bağlanmasını DB seviyesinde engeller.

#### `product_translations`
`product_id`, `language_id`, `name VARCHAR(150)`, `description TEXT NULL`, UNIQUE (product_id, language_id), FK CASCADE.

#### `product_allergens`
| Kolon | Tip | Not |
|---|---|---|
| restaurant_id | INT UNSIGNED | |
| product_id | INT UNSIGNED | FK → products.id CASCADE |
| allergen_id | INT UNSIGNED | FK → allergens.id RESTRICT |
| level | ENUM('contains','may_contain') | default `contains` ("içerir" / "eser miktarda içerebilir") |
| | | PK (product_id, allergen_id) |

### 4.4 Silme Politikası

- `restaurants`, `branches`, `restaurant_users`, `categories`, `products`: **soft delete** (`useSoftDeletes = true`). Soft-delete'li kayıtlar panelde ve menüde görünmez.
- `languages`: başka bir tabloda referansı varsa silinemez (sadece pasifleştirilir). Servis kontrol eder, DB RESTRICT ile de korunur.
- `allergens`: herhangi bir üründe kullanılıyorsa silinemez, pasifleştirilir.
- Kategori silinmek istendiğinde içinde (soft-delete olmamış) ürün varsa işlem reddedilir; kullanıcıya ürün sayısı bildirilir.
- Soft delete'lerde slug/public_code çakışmalarına dikkat: silinmiş kaydın slug'ı yeniden kullanılmak istenirse unique ihlali olur. Silerken `slug` değerinin sonuna `--deleted-{id}` eklenir.


### 4.5 Abonelik Tabloları

Ayrıntı ve gerekçeler: `docs/ABONELIK_PLANI.md` §3. Migration: `2026-11-12-000107_CreateBilling`, mevcut restoranlara deneme ataması: `…000108_AssignInitialSubscriptions`.

| Tablo | Tür | Özet |
|---|---|---|
| `plans` + `plan_translations` | global | `code` UNIQUE, `max_branches` (NULL = sınırsız), `allow_custom_domain`, `allow_custom_design`, `trial_days` (dolu = deneme paketi), `price_monthly`, `price_yearly`, `currency_code`, `is_featured`, `is_active`, `sort_order`. Kullanımdaki paket silinmez, pasifleştirilir. |
| `subscriptions` | tenant | `plan_id`, `billing_cycle` (trial/monthly/yearly), `status` (trialing/active/past_due/suspended/ended), `is_current` (1 / NULL), `price` (atama anında sabitlenir), dönem tarihleri, `cancel_at_period_end`, `grace_days`. UNIQUE(`restaurant_id`, `is_current`) → restoran başına tek güncel abonelik; UNIQUE(`id`, `restaurant_id`) bileşik FK hedefi. |
| `invoices` | tenant | `number` (`PM-{YYYY}-{id:06}`), dönem, `subtotal`/`discount`/`tax_rate`/`tax_amount`/`total`, `status` (pending/paid/void), `issued_at`, `due_date`, ödeme alanları, bildirim zaman damgaları. FK(`subscription_id`, `restaurant_id`) → subscriptions; UNIQUE(`subscription_id`, `period_start`). **Silinmez**, yalnızca `void`. "Gecikmiş" = `pending AND due_date < bugün`. |
| `restaurant_domains` | tenant | `restaurant_id` UNIQUE (restoran başına 1), `domain` UNIQUE (küçük harf, şemasız, `www.`'siz), `is_active`, `verified_at`. |
| `restaurants` (+) | — | `suspended_at`, `suspension_reason` (overdue/trial_expired/no_subscription/manual). Tek yazan `SubscriptionService::reconcile()` / `suspendManually()` / `resume()`. |

Ayarlar `app/Config/Billing.php` (`graceDays`, `paymentTermDays`, `invoiceLeadDays`, `reminderDaysBeforeDue`, `trialReminderDays`, `taxRate`, `invoicePrefix`, `billingEmailBcc`, `defaultPlanCode`, satıcı/ödeme bilgisi); `.env`'de `billing.*` ile ezilebilir.

---

## 5. Klasör Yapısı

```
app/
├── Config/
│   ├── Routes.php              # Tüm rotalar (§9)
│   ├── Filters.php             # Filter alias'ları (§7.3)
│   ├── Services.php            # tenant(), adminAuth(), panelAuth(), languageResolver()
│   └── Pixmenu.php             # Proje sabitleri (upload limitleri, görsel boyutları)
├── Controllers/
│   ├── BaseController.php
│   ├── Admin/
│   │   ├── AdminBaseController.php
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── LanguageController.php
│   │   ├── AllergenController.php
│   │   ├── RestaurantController.php
│   │   ├── RestaurantLanguageController.php
│   │   ├── RestaurantUserController.php
│   │   └── BranchController.php
│   ├── Panel/
│   │   ├── PanelBaseController.php
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── ProfileController.php
│   │   ├── RestaurantController.php   # owner: kendi restoran bilgisi
│   │   ├── BranchController.php
│   │   ├── CategoryController.php
│   │   ├── ProductController.php
│   │   └── UserController.php         # owner: manager yönetimi
│   ├── Menu/
│   │   └── MenuController.php         # Herkese açık HTML menü
│   └── Api/V1/
│       └── MenuController.php         # Herkese açık JSON menü
├── Filters/
│   ├── AdminAuthFilter.php
│   ├── PanelAuthFilter.php
│   ├── PanelOwnerFilter.php
│   └── NoCacheFilter.php
├── Libraries/
│   ├── Auth/
│   │   ├── AdminAuth.php
│   │   └── PanelAuth.php
│   └── Tenant/
│       ├── TenantContext.php
│       └── TenantNotSetException.php
├── Models/
│   ├── TenantModel.php                # Tenant kapsamlı taban model (§6.2)
│   ├── AdminModel.php
│   ├── LanguageModel.php
│   ├── AllergenModel.php
│   ├── AllergenTranslationModel.php
│   ├── RestaurantModel.php
│   ├── RestaurantTranslationModel.php
│   ├── RestaurantLanguageModel.php    # extends TenantModel
│   ├── RestaurantUserModel.php        # extends TenantModel
│   ├── RestaurantUserBranchModel.php  # extends TenantModel
│   ├── BranchModel.php                # extends TenantModel
│   ├── BranchTranslationModel.php
│   ├── CategoryModel.php              # extends TenantModel
│   ├── CategoryTranslationModel.php
│   ├── ProductModel.php               # extends TenantModel
│   ├── ProductTranslationModel.php
│   └── ProductAllergenModel.php       # extends TenantModel
├── Services/
│   ├── TranslationService.php
│   ├── LanguageResolver.php
│   ├── ImageService.php
│   ├── RestaurantService.php
│   ├── BranchService.php
│   ├── CategoryService.php
│   ├── ProductService.php
│   ├── AllergenService.php
│   └── MenuService.php
├── Validation/
│   └── PixmenuRules.php               # Özel kurallar (§10.2)
├── Database/
│   ├── Migrations/
│   └── Seeds/
└── Views/                             # Kapsam dışı; yalnızca dosya adları backend'de belirlenir
    ├── admin/...
    ├── panel/...
    └── menu/...
public/uploads/
├── allergens/
└── r/{restaurant_id}/{logo|branches|categories|products}/
tests/
├── _support/
└── Feature/TenantIsolation/
```

---

## 6. Tenant Altyapısı

### 6.1 `TenantContext` (Libraries/Tenant)

`Config\Services::tenant()` ile **shared** (istek başına tek örnek) servis.

```php
final class TenantContext
{
    private ?int $restaurantId = null;
    private ?int $userId = null;
    private ?string $role = null;        // 'owner' | 'manager'
    private ?array $branchIds = null;    // manager için izinli şubeler; owner için null = hepsi

    public function set(int $restaurantId, int $userId, string $role, ?array $branchIds): void;
    public function isSet(): bool;
    public function restaurantId(): int;        // set değilse TenantNotSetException
    public function userId(): int;
    public function isOwner(): bool;
    public function canAccessBranch(int $branchId): bool; // owner → true (tenant içindeyse), manager → in_array
    public function allowedBranchIds(): ?array;
    public function clear(): void;
}
```

- **Yalnızca `PanelAuthFilter` `set()` çağırabilir.** Başka hiçbir yer çağırmaz.
- Değerler oturumdan değil, her istekte **veritabanından** yeniden doğrulanarak doldurulur (§7.2): kullanıcı pasifleştirildiyse veya restoran pasif/silinmişse oturum anında düşer.

### 6.2 `TenantModel` (Models)

Tenant tablolarına ait tüm modellerin tabanı. CI4 Model'in korumalı `do*` metodları override edilerek her sorguya filtre eklenir.

```php
abstract class TenantModel extends Model
{
    protected bool $tenantScope = true;

    /** Yalnızca Admin servisleri kullanır. Panel kodunda çağrılması yasaktır. */
    public function withoutTenantScope(): static
    {
        $clone = clone $this;
        $clone->tenantScope = false;
        return $clone;
    }

    protected function applyTenantScope(): void
    {
        if (! $this->tenantScope) {
            return;
        }
        $this->builder()->where($this->table . '.restaurant_id', service('tenant')->restaurantId());
    }

    protected function doFind(bool $singleton, $id = null)          { $this->applyTenantScope(); return parent::doFind($singleton, $id); }
    protected function doFindAll(?int $limit = null, int $offset = 0) { $this->applyTenantScope(); return parent::doFindAll($limit, $offset); }
    protected function doFirst()                                     { $this->applyTenantScope(); return parent::doFirst(); }
    protected function doUpdate($id = null, $row = null): bool      { $this->applyTenantScope(); return parent::doUpdate($id, $row); }
    protected function doDelete($id = null, bool $purge = false)    { $this->applyTenantScope(); return parent::doDelete($id, $purge); }
    public function countAllResults(bool $reset = true, bool $test = false) { $this->applyTenantScope(); return parent::countAllResults($reset, $test); }

    // beforeInsert / beforeInsertBatch callback'i:
    // $data['data']['restaurant_id'] = service('tenant')->restaurantId();   (scope açıksa ZORLA üzerine yazar)
    // beforeUpdate callback'i: $data['data'] içinden 'restaurant_id' anahtarını SİLER (tenant değiştirilemez).
}
```

Kurallar:
- `restaurant_id`, `$allowedFields` içinde bulunur ama değeri **yalnızca callback** tarafından atanır.
- Tenant set edilmeden scope'lu model sorgusu `TenantNotSetException` fırlatır → 500 + log. Bu bir programlama hatasıdır, sessizce yutulmaz.
- Admin servisleri `withoutTenantScope()` kullanır ve insert'te `restaurant_id`'yi açıkça verir.
- Model metodları (`paginate`, özel query metodları) yazılırken builder'ı `applyTenantScope()` sonrası kullanmak zorunludur. Özel metodlarda `$this->builder()` değil, scope uygulanmış builder kullanılır.

### 6.3 Sahiplik Doğrulama Yardımcıları (`PanelBaseController`)

```php
protected function requireBranch(int $branchId): array      // tenant + canAccessBranch, yoksa PageNotFoundException
protected function requireCategory(int $branchId, int $categoryId): array   // category.branch_id === $branchId
protected function requireProduct(int $branchId, int $productId): array
protected function requireOwner(): void                     // manager ise 404
```

Her panel action'ı ilk iş olarak bu yardımcılardan uygun olanı çağırır. Bulunamama, başka tenant'a ait olma, yetkisiz şube — üçü de **aynı** `PageNotFoundException` ile sonuçlanır.

---

## 7. Kimlik Doğrulama

### 7.1 Ortak Kurallar

- Parola: `password_hash($p, PASSWORD_DEFAULT)` / `password_verify`; login sonrası `password_needs_rehash` kontrolü.
- Parola politikası: min 10 karakter, en az bir harf ve bir rakam.
- Brute force: CI4 `Throttler` ile `ip + email` anahtarı başına dakikada 5 deneme; aşılırsa 429 ve "çok fazla deneme" mesajı.
- Başarısız login'de hata mesajı her zaman aynıdır: "E-posta veya şifre hatalı." (kullanıcı varlığı sızdırılmaz). Kullanıcı bulunamasa da `password_verify` sahte hash ile çağrılır (zamanlama eşitliği).
- Başarılı login: `session()->regenerate(true)`, `last_login_at` güncellenir.
- Logout yalnızca POST + CSRF ile.
- Login sayfaları (`GET`) zaten oturumlu kullanıcıyı ilgili dashboard'a yönlendirir.

### 7.2 Oturum Verisi

```php
// Admin
session()->set('admin_auth', ['id' => 1, 'login_at' => time()]);

// Restoran paneli
session()->set('panel_auth', ['id' => 7, 'restaurant_id' => 3, 'login_at' => time()]);
```

`PanelAuthFilter` her istekte:
1. `panel_auth` yoksa → `/panel/login` (AJAX ise 401 JSON).
2. `restaurant_users` kaydını **scope'suz** modelle `id` ile yükler; `is_active=1`, `deleted_at IS NULL`, `restaurant_id` oturumdakiyle eşleşmeli.
3. Restoran `is_active=1` ve silinmemiş olmalı.
4. Manager ise `restaurant_user_branches`'tan izinli şube ID'lerini alır (yalnızca silinmemiş şubeler).
5. Herhangi biri başarısızsa `panel_auth` silinir, login'e yönlendirilir.
6. Başarılıysa `service('tenant')->set(...)`.

`AdminAuthFilter` aynı mantıkla `admins` tablosunu doğrular (`is_active=1` ve rol `Config\AdminRoles`'ta tanımlı). Admin filtresi `TenantContext`'e **dokunmaz**.

### 7.3 Filter Alias'ları (`Config/Filters.php`)

| Alias | Sınıf | Kullanım |
|---|---|---|
| `adminauth` | `AdminAuthFilter` | `/admin/*` (login hariç) |
| `adminperm:<izin>` | `AdminPermissionFilter` | `adminauth`'tan sonra; rolün izni yoksa HTML → 403 `admin/forbidden`, JSON → 403 `{status:error}` (§7.4) |
| `panelauth` | `PanelAuthFilter` | `/panel/*` (login hariç) |
| `panelowner` | `PanelOwnerFilter` | Yalnızca owner'a açık panel rotaları; manager → 404 |
| `panelsubscription` | `PanelSubscriptionFilter` | Panel içerik rotaları; restoran askıdaysa owner → `panel/subscription`, manager → `panel`, JSON → 403 (§9.5) |
| `nocache` | `NoCacheFilter` | Tüm panel yanıtlarına `Cache-Control: no-store` |

### 7.4 Admin Rolleri ve İzinleri (`Config\AdminRoles`)

Roller ve izinleri tek yerde tanımlıdır; yeni rol (ör. `support`) eklemek için oraya bir girdi eklemek yeterlidir. `logout` dışındaki her admin rotası tam olarak bir `adminperm:<izin>` taşır (`AdminRouteCoverageTest` doğrular). View'lar yetkisiz düğmeleri `admin_can('<izin>')` ile gizler; asıl koruma filtredir. Rol her istekte DB'den okunduğu için değişiklik anında etkilidir.

| İzin | Kapsam | super_admin | manager | accountant |
|---|---|:-:|:-:|:-:|
| `dashboard.view` | Gösterge paneli | ✓ | ✓ | ✓ |
| `restaurants.view` | Restoran listesi / detay | ✓ | ✓ | ✓ |
| `restaurants.manage` | Restoran ekle/düzenle/aktif-pasif, şube, restoran kullanıcısı, restoran dilleri, alan adı | ✓ | ✓ | – |
| `restaurants.delete` | Restoran silme | ✓ | – | – |
| `definitions.manage` | Diller, alerjenler, menü şablonları | ✓ | ✓ | – |
| `billing.view` | Paket listesi, faturalar, abonelik sayfası (salt okunur), panodaki finans kartları | ✓ | ✓ | ✓ |
| `invoices.manage` | Fatura ödendi işaretle, fatura indirimi | ✓ | – | ✓ |
| `invoices.void` | Fatura iptali | ✓ | – | – |
| `subscriptions.manage` | Paket değiştir, gecikme eşiği, restoran indirimi | ✓ | – | ✓ |
| `subscriptions.control` | Abonelik iptal, askıya al, askıdan çıkar | ✓ | – | – |
| `plans.manage` | Paket ekle/düzenle/sırala/aktif-pasif/sil | ✓ | – | – |

---

## 8. Çok Dillilik

### 8.1 Dil Katmanları

| Katman | Nerede | Kim belirler |
|---|---|---|
| Sistemdeki tüm diller | `languages` | Admin |
| Restoranın dilleri | `restaurant_languages` | Admin (restoran düzenleme ekranından) |
| Restoranın varsayılan dili | `restaurants.default_language_id` | Admin |
| Şubenin varsayılan dili | `branches.default_language_id` | Admin veya owner; restoranın aktif dillerinden seçilir |

Bir şube, restoranın tüm aktif dillerinde menü sunar; yalnızca varsayılan dili farklıdır.

### 8.2 Çeviri Girişi (Form Formatı)

Tüm çevrilebilir formlarda alanlar şu formatta gönderilir:

```
translations[tr][name]=Mercimek Çorbası
translations[tr][description]=...
translations[en][name]=Lentil Soup
translations[en][description]=...
```

`TranslationService::sync(string $translationModel, string $fkColumn, int $parentId, array $input, array $allowedLangCodes, string $requiredLangCode)`:
1. `$input` anahtarlarını `$allowedLangCodes` ile kesiştirir; izinsiz dil kodları **yok sayılır**.
2. `$requiredLangCode` (restoran içerikleri için **şubenin** varsayılan dili; restoran/şube kendi bilgileri için **restoranın** varsayılan dili; alerjenler için `tr`) için `name` zorunludur.
3. Diğer dillerde `name` boşsa o dilin satırı silinir (fallback devreye girer).
4. Var olan satırları günceller, yenilerini ekler (upsert, `UNIQUE (parent, language)` üzerinden).
5. Çağıran servis bunu kendi transaction'ı içinde çağırır.

Panelde düzenleme ekranına gönderilen veri:

```php
[
  'languages'       => [['id'=>1,'code'=>'tr','native_name'=>'Türkçe','direction'=>'ltr','is_default'=>true], ...],
  'translations'    => ['tr' => ['name'=>'...','description'=>'...'], 'en' => [...]],
]
```

### 8.3 Okuma ve Fallback (`LanguageResolver`)

Bir metin istenen dilde yoksa sırasıyla: **istenen dil → şubenin varsayılan dili → restoranın varsayılan dili → mevcut ilk çeviri**.

Alerjenler için: istenen dil → şubenin varsayılan dili → `tr` → `en` → mevcut ilk çeviri.

Çeviri sorguları N+1 olmadan yapılır: bir menü için tüm `product_translations` satırları `WHERE product_id IN (...) AND language_id IN (istenen, şube varsayılanı, restoran varsayılanı)` ile tek sorguda çekilir ve PHP'de çözülür.

### 8.4 Ziyaretçi Dil Seçimi

1. `?lang=xx` parametresi, restoranın **aktif** dillerindeyse kullanılır ve `pixmenu_lang_{branch_public_code}` çerezine (30 gün) yazılır.
2. Yoksa çerez değeri (hâlâ geçerliyse).
3. Yoksa şubenin varsayılan dili.

`Accept-Language` başlığı kullanılmaz (bilinçli karar; şubenin varsayılan dili önceliklidir).

### 8.5 Dil Değişikliği Kuralları

- Restorandan bir dil çıkarılırken o dil restoranın veya herhangi bir şubesinin varsayılan diliyse işlem reddedilir.
- Çıkarılan dilin çevirileri **silinmez** (dil geri eklenirse veri geri gelir); menüde gösterilmez.
- Global bir dil pasifleştirilirse tüm restoranlarda menüde gösterilmez ama varsayılan dil olarak kullanılıyorsa pasifleştirme reddedilir.

---

## 9. Rotalar

`app/Config/Routes.php`. `autoRoute` kapalı. Formlar düz HTML olduğu için güncelleme/silme işlemleri **POST** ile ve açık yollarla yapılır (method spoofing kullanılmaz). `(:num)` ile kısıtlanmayan ID parametresi yoktur.

### 9.1 Admin Rotaları — `/admin`

> Güncel gruplama `app/Config/Routes.php`'tedir: her izin için ayrı alt grup, `['filter' => ['adminauth', 'adminperm:<izin>', 'nocache']]` (§7.4). Aşağıdaki liste rota kümesini gösterir.

```php
$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->get('login',  'AuthController::login');
    $routes->post('login', 'AuthController::attempt');

    $routes->group('', ['filter' => ['adminauth', 'nocache']], static function ($routes) {
        $routes->post('logout', 'AuthController::logout');
        $routes->get('/', 'DashboardController::index');

        // Diller
        $routes->get('languages',                    'LanguageController::index');
        $routes->get('languages/new',                'LanguageController::new');
        $routes->post('languages',                   'LanguageController::create');
        $routes->get('languages/(:num)/edit',        'LanguageController::edit/$1');
        $routes->post('languages/(:num)',            'LanguageController::update/$1');
        $routes->post('languages/(:num)/toggle',     'LanguageController::toggle/$1');
        $routes->post('languages/(:num)/delete',     'LanguageController::delete/$1');
        $routes->post('languages/reorder',           'LanguageController::reorder');      // JSON

        // Alerjenler
        $routes->get('allergens',                    'AllergenController::index');
        $routes->get('allergens/new',                'AllergenController::new');
        $routes->post('allergens',                   'AllergenController::create');
        $routes->get('allergens/(:num)/edit',        'AllergenController::edit/$1');
        $routes->post('allergens/(:num)',            'AllergenController::update/$1');
        $routes->post('allergens/(:num)/toggle',     'AllergenController::toggle/$1');
        $routes->post('allergens/(:num)/icon/delete','AllergenController::deleteIcon/$1');
        $routes->post('allergens/(:num)/delete',     'AllergenController::delete/$1');
        $routes->post('allergens/reorder',           'AllergenController::reorder');      // JSON

        // Restoranlar
        $routes->get('restaurants',                  'RestaurantController::index');
        $routes->get('restaurants/new',              'RestaurantController::new');
        $routes->post('restaurants',                 'RestaurantController::create');
        $routes->get('restaurants/(:num)',           'RestaurantController::show/$1');
        $routes->get('restaurants/(:num)/edit',      'RestaurantController::edit/$1');
        $routes->post('restaurants/(:num)',          'RestaurantController::update/$1');
        $routes->post('restaurants/(:num)/toggle',   'RestaurantController::toggle/$1');
        $routes->post('restaurants/(:num)/delete',   'RestaurantController::delete/$1');

        // Restoran dilleri
        $routes->get('restaurants/(:num)/languages',  'RestaurantLanguageController::index/$1');
        $routes->post('restaurants/(:num)/languages', 'RestaurantLanguageController::sync/$1');

        // Restoran kullanıcıları
        $routes->get('restaurants/(:num)/users',                   'RestaurantUserController::index/$1');
        $routes->get('restaurants/(:num)/users/new',               'RestaurantUserController::new/$1');
        $routes->post('restaurants/(:num)/users',                  'RestaurantUserController::create/$1');
        $routes->get('restaurants/(:num)/users/(:num)/edit',       'RestaurantUserController::edit/$1/$2');
        $routes->post('restaurants/(:num)/users/(:num)',           'RestaurantUserController::update/$1/$2');
        $routes->post('restaurants/(:num)/users/(:num)/toggle',    'RestaurantUserController::toggle/$1/$2');
        $routes->post('restaurants/(:num)/users/(:num)/delete',    'RestaurantUserController::delete/$1/$2');

        // Şubeler
        $routes->get('restaurants/(:num)/branches',                'BranchController::index/$1');
        $routes->get('restaurants/(:num)/branches/new',            'BranchController::new/$1');
        $routes->post('restaurants/(:num)/branches',               'BranchController::create/$1');
        $routes->get('restaurants/(:num)/branches/(:num)/edit',    'BranchController::edit/$1/$2');
        $routes->post('restaurants/(:num)/branches/(:num)',        'BranchController::update/$1/$2');
        $routes->post('restaurants/(:num)/branches/(:num)/toggle', 'BranchController::toggle/$1/$2');
        $routes->post('restaurants/(:num)/branches/(:num)/delete', 'BranchController::delete/$1/$2');
        $routes->post('restaurants/(:num)/branches/(:num)/regenerate-code', 'BranchController::regenerateCode/$1/$2');
    });
});
```

> Admin rotalarında da iç içe ID'ler doğrulanır: `restaurants/3/branches/9` için şube 9'un `restaurant_id = 3` olduğu kontrol edilir, değilse 404.

### 9.2 Restoran Paneli Rotaları — `/panel`

```php
$routes->group('panel', ['namespace' => 'App\Controllers\Panel'], static function ($routes) {
    $routes->get('login',  'AuthController::login');
    $routes->post('login', 'AuthController::attempt');

    $routes->group('', ['filter' => ['panelauth', 'nocache']], static function ($routes) {
        $routes->post('logout', 'AuthController::logout');
        $routes->get('/', 'DashboardController::index');

        $routes->get('profile',           'ProfileController::edit');
        $routes->post('profile',          'ProfileController::update');
        $routes->post('profile/password', 'ProfileController::password');

        // Yalnızca owner
        $routes->group('', ['filter' => 'panelowner'], static function ($routes) {
            $routes->get('restaurant',              'RestaurantController::edit');
            $routes->post('restaurant',             'RestaurantController::update');
            $routes->post('restaurant/logo/delete', 'RestaurantController::deleteLogo');

            $routes->get('branches/new',                    'BranchController::new');
            $routes->post('branches',                       'BranchController::create');
            $routes->get('branches/(:num)/edit',            'BranchController::edit/$1');
            $routes->post('branches/(:num)',                'BranchController::update/$1');
            $routes->post('branches/(:num)/toggle',         'BranchController::toggle/$1');
            $routes->post('branches/(:num)/delete',         'BranchController::delete/$1');

            $routes->get('users',                    'UserController::index');
            $routes->get('users/new',                'UserController::new');
            $routes->post('users',                   'UserController::create');
            $routes->get('users/(:num)/edit',        'UserController::edit/$1');
            $routes->post('users/(:num)',            'UserController::update/$1');
            $routes->post('users/(:num)/toggle',     'UserController::toggle/$1');
            $routes->post('users/(:num)/delete',     'UserController::delete/$1');
        });

        // Owner + manager (manager yalnızca atanmış şubeler)
        $routes->get('branches', 'BranchController::index');

        $routes->get('branches/(:num)/categories',                   'CategoryController::index/$1');
        $routes->get('branches/(:num)/categories/new',               'CategoryController::new/$1');
        $routes->post('branches/(:num)/categories',                  'CategoryController::create/$1');
        $routes->get('branches/(:num)/categories/(:num)/edit',       'CategoryController::edit/$1/$2');
        $routes->post('branches/(:num)/categories/(:num)',           'CategoryController::update/$1/$2');
        $routes->post('branches/(:num)/categories/(:num)/toggle',    'CategoryController::toggle/$1/$2');
        $routes->post('branches/(:num)/categories/(:num)/delete',    'CategoryController::delete/$1/$2');
        $routes->post('branches/(:num)/categories/reorder',          'CategoryController::reorder/$1');   // JSON

        $routes->get('branches/(:num)/products',                     'ProductController::index/$1');      // ?category=ID&q=
        $routes->get('branches/(:num)/products/new',                 'ProductController::new/$1');
        $routes->post('branches/(:num)/products',                    'ProductController::create/$1');
        $routes->get('branches/(:num)/products/(:num)/edit',         'ProductController::edit/$1/$2');
        $routes->post('branches/(:num)/products/(:num)',             'ProductController::update/$1/$2');
        $routes->post('branches/(:num)/products/(:num)/toggle',      'ProductController::toggle/$1/$2');
        $routes->post('branches/(:num)/products/(:num)/image/delete','ProductController::deleteImage/$1/$2');
        $routes->post('branches/(:num)/products/(:num)/delete',      'ProductController::delete/$1/$2');
        $routes->post('branches/(:num)/products/reorder',            'ProductController::reorder/$1');    // JSON
    });
});
```

### 9.3 Herkese Açık Rotalar

```php
$routes->get('m/(:alphanum)', 'Menu\MenuController::show/$1');                 // HTML menü, ?lang=en
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function ($routes) {
    $routes->get('menu/(:alphanum)', 'MenuController::show/$1');                 // JSON menü, ?lang=en
});
```

- Parametre `branches.public_code`'dur (restoran/şube ID'si dışarı açılmaz).
- Şube veya restoran pasif/silinmiş → 404.
- `api/*` CSRF dışında; yalnızca GET; `Cache-Control: public, max-age=60`.

### 9.4 Rota Çakışma Kuralları

- `/admin`, `/panel`, `/m`, `/api` önekleri ayrılmıştır; başka modül bu önekleri kullanamaz.
- Statik segmentli rotalar (`new`, `reorder`) `(:num)` rotalarından **önce** tanımlanır (yukarıdaki sıra korunmalıdır).


### 9.5 Abonelik Rotaları

```php
// admin (adminauth)
plans, plans/new, plans (POST), plans/reorder (JSON), plans/(:num)/edit, plans/(:num), plans/(:num)/toggle, plans/(:num)/delete
invoices (?status=pending|paid|void & overdue=1 & restaurant=ID), invoices/(:num), invoices/(:num)/pay, invoices/(:num)/void
restaurants/(:num)/subscription (GET), …/subscription/change|cancel|suspend|resume|grace (POST)
restaurants/(:num)/domain (GET, POST), …/domain/verify, …/domain/delete (POST)

// panel — yalnızca owner, askıdayken de açık
subscription, subscription/plans, subscription/invoices/(:num)
```

- **`panelsubscription` filtresi** (`PanelSubscriptionFilter`): restoran askıdaysa içerik rotaları (restoran ayarları, şubeler, kullanıcılar, kategori/ürün) kapanır — HTML: owner → `panel/subscription`, manager → `panel` (uyarı bandı); JSON: 403. Gösterge paneli, profil, çıkış ve abonelik sayfaları serbesttir. `TenantContext::isSuspended()` bilgisini `PanelAuthFilter` doldurur.
- **Özel alan adı** (`Routes.php` başı): istek host'u uygulamanın host'u (`baseURL`, `allowedHostnames`, localhost/IP) değilse YALNIZCA `GET /` ve `GET /(:segment)` (`Menu\DomainMenuController`) tanımlanır ve dosya biter; `/admin`, `/panel`, `/api`, `/m` o host'ta yoktur. Tek aktif şube → kökte menü; birden fazla → şube seçimi (`menu/branches`), menüler `/{şube-slug}`.
- Askıdaki restoranın menüsü (HTML, JSON ve özel alan adı) 404 döner.

---

## 10. Modül İş Kuralları ve Doğrulama

### 10.1 Modüller

**Dil (admin)**
- Alanlar: `code`, `name`, `native_name`, `direction`, `is_active`, `sort_order`.
- `code` regex: `^[a-z]{2,3}(-[A-Z]{2})?$`, unique. Oluşturulduktan sonra değiştirilemez.

**Alerjen (admin)**
- Alanlar: `code`, `icon` (dosya), `is_active`, `sort_order`, `translations[*][name|description]`.
- Çeviriler sistemdeki **tüm aktif dillerde** girilebilir; `tr` zorunlu.
- Panel kullanıcıları alerjenleri yalnızca okur (ürün formunda seçim için).

**Restoran (admin)**
- Oluşturma tek transaction: `restaurants` + `restaurant_translations` + `restaurant_languages` + ilk `owner` kullanıcı (opsiyonel ama formda önerilir).
- Formda en az bir dil seçilir; `default_language_id` seçilen diller arasında olmak zorunda.
- `slug` boşsa varsayılan dildeki isimden üretilir (`url_title(..., '-', true)`, Türkçe karakterler dönüştürülerek); çakışırsa `-2`, `-3`… eklenir.
- Pasifleştirilen restoranın tüm kullanıcılarının oturumu bir sonraki istekte düşer (§7.2) ve menüleri 404 döner.

**Restoran kullanıcısı (admin + owner)**
- Admin her rolde kullanıcı açabilir. Owner yalnızca `manager` açabilir/düzenleyebilir; kendisini ve diğer owner'ları silemez/pasifleştiremez.
- Manager oluştururken `branch_ids[]` en az bir şube; her ID tenant'a ait olmalı.
- Restoranın son aktif owner'ı silinemez/pasifleştirilemez/manager'a düşürülemez.
- Parola değişikliğinde kullanıcının diğer oturumlarını geçersiz kılmak için `restaurant_users` tablosuna `password_changed_at` eklenip filter'da `login_at` ile karşılaştırılabilir (önerilir).

**Şube (admin + owner)**
- Alanlar: `slug`, `default_language_id`, `phone`, `is_active`, `sort_order`, `translations[*][name|address|description]`.
- `public_code` oluşturulurken `bin2hex(random_bytes(5))` ile üretilir (10 karakter), çakışmada tekrar denenir. `regenerate-code` eski QR'ları geçersiz kılar (UI'da uyarı gösterilmeli).
- `default_language_id` restoranın **aktif** dillerinden biri olmalı.

**Kategori (owner + manager)**
- Alanlar: `image` (opsiyonel), `is_active`, `sort_order`, `translations[*][name|description]`.
- Şubenin varsayılan dilinde `name` zorunlu.
- Düz yapı (alt kategori yok — bkz. §15).

**Ürün (owner + manager)**
- Alanlar: `category_id`, `price`, `image`, `is_active`, `sort_order`, `translations[*][name|description]`, `allergens[{allergen_id}]=contains|may_contain`.
- `category_id` aynı şubeye ait ve silinmemiş olmalı.
- `price`: `decimal`, `greater_than_equal_to[0]`, `less_than[100000000]`; virgüllü giriş (`129,90`) servis katmanında noktaya çevrilir.
- Alerjenler: yalnızca `is_active=1` alerjenler kabul edilir; gönderilmeyenler silinir (sync).
- Ürün + çeviri + alerjen yazımı tek transaction.

**Sıralama (reorder)**
- JSON body: `{"ids":[12,5,9]}`. Tüm ID'ler aynı şubeye ve tenant'a ait olmalı; biri bile değilse işlem tamamen reddedilir (422). `sort_order` = dizideki index.

**Abonelik** (`SubscriptionService`, `InvoiceService`, `PlanService`, `DomainService`, `BillingRunner` — `docs/ABONELIK_PLANI.md` §5–§7)
- Yeni restoran `plan_id` + `billing_cycle` ile açılır (verilmezse `defaultPlanCode` = demo, 14 gün deneme); abonelik aynı transaction'da başlar. Ücretli pakette ilk dönem faturası hemen kesilir (vade = bugün + `paymentTermDays`).
- **Şube limiti**: aktif (silinmemiş, `is_active=1`) şube sayısı. `BranchService::create/update/toggle` bir şubeyi aktif hale getirecekse `assertBranchLimit()` çağırır (admin + panel). Güncel abonelik yoksa şube aktifleştirilemez.
- **Paket değişimi**: `assertPlanFits()` — aktif şube sayısı hedef limiti aşıyorsa veya alan adı tanımlıyken hedef paket alan adı içermiyorsa reddedilir. Eski abonelik `ended`; yeni abonelik bugün başlar, tam dönem faturası (isteğe bağlı indirimle) kesilir. Eski açık faturalar otomatik iptal edilmez.
- **Askı** her zaman `reconcile()` ile verilerden hesaplanır: `manual` (yalnızca admin kaldırır) > abonelik yok > deneme süresi dolmuş > vade + eşik (`grace_days ?? graceDays`) geçmiş açık fatura (eski abonelikler dahil). Abonelik statüsü: askıda → `suspended`, deneme → `trialing`, vadesi geçmiş açık fatura → `past_due`, aksi `active`. Ödeme/iptal/eşik değişikliği sonrası çağrılır.
- **Fatura**: tutarlar kuruş üzerinden; KDV = round((ara toplam − indirim) × oran). Yalnızca `pending` fatura ödenir veya iptal edilir.
- **Cron** `php spark billing:run [--dry-run] [--date=YYYY-MM-DD]`: dönem ilerletme (askıdaki/deneme hariç; `cancel_at_period_end` → sonlandır), içinde bulunulan dönemin eksik faturası, dönem sonuna `invoiceLeadDays` kala yenileme faturası (vade = dönem sonu), kesim/vade/gecikme/deneme bildirimleri (her biri bir kez), `reconcile`. Her restoran ayrı transaction; idempotent.
- **Alan adı**: normalize edilir; uygulamanın kendi host'ları, alt alan adları ve `reservedDomains` reddedilir; paket `allow_custom_domain` olmalı; global tekil. Değişince `verified_at` sıfırlanır. `resolve()` sonucu `domainCacheTtl` saniye cache'lenir.

### 10.2 Özel Doğrulama Kuralları (`Validation/PixmenuRules.php`)

| Kural | Açıklama |
|---|---|
| `tenant_branch[field]` | Değer, aktif tenant'ın ve kullanıcının erişebildiği bir şube ID'si mi |
| `branch_category[branchId]` | Değer, verilen şubeye ait silinmemiş bir kategori mi |
| `restaurant_language` | Dil ID'si restoranın aktif dillerinden mi |
| `active_allergen` | Alerjen ID'si var ve aktif mi |
| `strong_password` | §7.1 politikası |

Doğrulama hataları: HTML formlarında `redirect()->back()->withInput()->with('errors', $errors)`; JSON uçlarında §11 formatı ile 422.

---

## 11. Yanıt Formatları

### 11.1 HTML (panel ve admin)
- Başarılı yazma sonrası Post/Redirect/Get: `redirect()->to(...)->with('success', 'Kaydedildi.')`.
- Flash anahtarları: `success`, `error`, `errors` (dizi).
- View'a geçirilen her değişken controller'da açıkça hazırlanır; view içinde model çağrısı yapılmaz.

### 11.2 JSON (reorder, toggle-AJAX, public API)

```json
{ "status": "success", "data": { }, "message": "Sıralama kaydedildi." }
```
```json
{ "status": "error", "message": "Doğrulama hatası.", "errors": { "price": "Fiyat 0'dan küçük olamaz." } }
```

| Durum | HTTP |
|---|---|
| Başarılı | 200 / 201 |
| Doğrulama | 422 |
| Oturum yok | 401 |
| Bulunamadı / başka tenant / yetkisiz şube | 404 |
| Throttle | 429 |

AJAX istekleri CSRF token'ını `X-CSRF-TOKEN` başlığında gönderir; yanıt yeni token'ı `X-CSRF-TOKEN` başlığında döner (token regenerate açık olduğu için).

### 11.3 Herkese Açık Menü JSON — `GET /api/v1/menu/{public_code}?lang=en`

```json
{
  "status": "success",
  "data": {
    "restaurant": { "name": "Örnek Restoran", "description": "...", "logo_url": "https://.../uploads/r/3/logo/ab12.webp", "currency": "TRY" },
    "branch": { "code": "k3f9a0c21b", "name": "Kadıköy", "address": "...", "phone": "..." },
    "language": { "code": "en", "direction": "ltr" },
    "languages": [ { "code": "tr", "native_name": "Türkçe" }, { "code": "en", "native_name": "English" } ],
    "allergens": [ { "id": 1, "code": "gluten", "name": "Gluten", "icon_url": "..." } ],
    "categories": [
      {
        "id": 10, "name": "Soups", "description": null, "image_url": null, "emoji": "🍲",
        "products": [
          {
            "id": 88, "name": "Lentil Soup", "description": "...", "price": "129.90",
            "image_url": "...", "thumb_url": "...",
            "allergens": [ { "id": 1, "level": "contains" }, { "id": 7, "level": "may_contain" } ]
          }
        ]
      }
    ]
  }
}
```

- Yalnızca aktif ve silinmemiş kategoriler/ürünler; `sort_order, id` sıralı. Ürünü olmayan kategoriler listelenmez.
- `allergens` dizisi yalnızca menüde kullanılan alerjenleri içerir; ürünler ID ile referans verir.
- Fiyat string olarak döner (float yuvarlama hatası olmasın). Biçimlendirme frontend'de `Intl.NumberFormat` ile yapılır.
- `MenuService::build(string $publicCode, ?string $lang): array` hem HTML hem JSON controller tarafından kullanılır. Sonuç `cache()` ile `menu_{branch_id}_{lang}` anahtarında 5 dk tutulur; şubeye ait kategori/ürün/çeviri/alerjen/restoran/şube değişikliklerinde ilgili şubenin tüm dil anahtarları silinir (`MenuService::invalidate(int $branchId)`; restoran değişikliğinde tüm şubeleri). Alerjen değişikliğinde tüm menü cache'i temizlenir.
- Toplam sorgu sayısı dil sayısından ve ürün sayısından bağımsız sabit olmalı (≤ 10 sorgu).

---

## 12. Dosya Yükleme (`ImageService`)

| Tür | İzinli | Maks. boyut | İşlem | Yol |
|---|---|---|---|---|
| Ürün görseli | jpg, jpeg, png, webp | 5 MB | Uzun kenar 1600 px, WebP %82; thumbnail 480 px | `uploads/r/{rid}/products/{random}.webp` |
| Kategori görseli | jpg, jpeg, png, webp | 3 MB | Uzun kenar 1200 px, WebP | `uploads/r/{rid}/categories/...` |
| Restoran logosu | jpg, jpeg, png, webp | 2 MB | Uzun kenar 600 px, WebP (şeffaflık korunur) | `uploads/r/{rid}/logo/...` |
| Alerjen ikonu | svg, png, webp | 512 KB | SVG → `enshrined/svg-sanitize` ile temizlenir; PNG/WebP → 128 px | `uploads/allergens/...` |

Kurallar:
- CI4 doğrulaması: `uploaded`, `is_image` (SVG hariç), `mime_in`, `max_size`, `max_dims[6000,6000]`.
- Dosya adı her zaman `bin2hex(random_bytes(16))`; kullanıcının dosya adı kullanılmaz.
- Raster görseller CI4 `Image` (GD) ile **yeniden encode** edilir (EXIF ve gömülü içerik atılır).
- `public/uploads/.htaccess` (Apache) ve nginx kuralı ile bu dizinde PHP çalıştırma kapatılır:
  ```apache
  <FilesMatch "\.(php|phtml|phar|php\d)$">
      Require all denied
  </FilesMatch>
  Options -Indexes
  ```
  ```nginx
  location ^~ /uploads/ { location ~ \.php$ { deny all; } }
  ```
- Görsel değiştirildiğinde veya kayıt **kalıcı** silindiğinde eski dosyalar diskten silinir. Soft delete'te dosyalar kalır.
- Yeni dosya diske yazılır → DB transaction commit olmazsa yazılan dosya silinir.

---

## 13. Seeder'lar

| Seeder | İçerik |
|---|---|
| `LanguageSeeder` | tr (Türkçe), en (English), de (Deutsch), ru (Русский), ar (العربية, rtl) |
| `AllergenSeeder` | AB 14 alerjeni, tr + en çevirileri: gluten, crustaceans, eggs, fish, peanuts, soybeans, milk, nuts, celery, mustard, sesame, sulphites, lupin, molluscs. İkonlar boş bırakılır. |
| `AdminSeeder` | `.env`'deki `pixmenu.adminEmail` / `pixmenu.adminPassword` ile bir **süper admin**. Değerler yoksa çalışmaz ve hata verir. |
| `DemoSeeder` | **Yalnızca development.** 2 restoran (izolasyon testini elle denemek için), her birinde 2 şube, owner + manager, kategoriler, ürünler, alerjen atamaları. |
| `DatabaseSeeder` | Language → Allergen → Admin (Demo hariç) |

```bash
php spark migrate
php spark db:seed DatabaseSeeder
php spark db:seed DemoSeeder   # opsiyonel
```

Diğer admin hesapları (web arayüzü yok):

```bash
php spark admin:create --name "Ayşe Yılmaz" --email ayse@ornek.com --role accountant   # parola üretilir, bir kez yazılır
php spark admin:list
php spark admin:update ayse@ornek.com --role manager      # --active 0|1, --password [<parola>]
```

---

## 14. Testler (Zorunlu)

PHPUnit + CI4 `FeatureTestTrait` + `DatabaseTestTrait`, `pixmenu_test` veritabanı. Her test kendi verisini fixture olarak kurar: **Restoran A** (owner A, manager A1 → yalnızca şube A1), **Restoran B** (owner B).

### 14.1 Tenant İzolasyon Testleri (`tests/Feature/TenantIsolation`)

Aşağıdakilerin **her biri** ayrı test olarak bulunmalı:

1. Owner A, B'nin şube/kategori/ürün edit sayfalarını GET ile açar → 404.
2. Owner A, B'nin ürününü POST ile günceller/siler/toggle eder → 404 ve DB değişmemiş.
3. Owner A, kendi şubesinin URL'si altında B'nin ürün ID'sini kullanır (`/panel/branches/{A1}/products/{B_product}`) → 404.
4. Owner A, kendi ürününü güncellerken `category_id` olarak B'nin kategorisini gönderir → 422/validation hatası, DB değişmemiş.
5. Owner A, POST body'ye `restaurant_id = B` ekler → kayıt yine A'ya yazılır.
6. Owner A, reorder isteğine B'nin ID'lerini karıştırır → 422, hiçbir sıra değişmemiş.
7. Owner A, manager oluştururken `branch_ids[]` içinde B'nin şubesini gönderir → hata.
8. Manager A1, A'nın atanmamış şubesi A2'nin kategori/ürünlerine erişir → 404.
9. Manager A1, owner rotalarına (`/panel/users`, `/panel/restaurant`, `/panel/branches/new`) erişir → 404.
10. Panel listeleri (`/panel/branches`, ürün listesi) B'ye ait hiçbir kaydı içermez.
11. `admin_auth` oturumu ile `/panel/*` → login'e yönlendirme; `panel_auth` ile `/admin/*` → login'e yönlendirme.
12. Restoran B pasifleştirilince owner B'nin sonraki isteği login'e düşer; B'nin menüsü 404.
13. Tenant set edilmeden `ProductModel::find()` → `TenantNotSetException`.

### 14.2 Diğer Testler
- Çeviri fallback zinciri (§8.3) birim testleri.
- Varsayılan dil olarak kullanılan dilin restorandan çıkarılamaması.
- Son owner'ın silinememesi.
- Public menü: pasif ürün/kategori görünmez, `?lang` geçersizse şube varsayılanı kullanılır, sorgu sayısı sınırı.
- Login throttle (6. denemede 429).

```bash
vendor/bin/phpunit
```

---

## 15. Kapsam Dışı / Sonraki Aşama

Aşağıdakiler bu sürümde **yapılmaz**; ajanlar kendiliğinden eklemez:
- Alt kategori (iç içe kategori).
- Ürün varyantları / porsiyon / ekstra seçenekler.
- Şubeler arası kategori/ürün kopyalama (sonraki aşama için `BranchService::cloneMenu(fromBranchId, toBranchId)` olarak planlanmıştır).
- Şube bazında farklı dil alt kümesi (şu an şube, restoranın tüm aktif dillerini kullanır).
- Şube bazında farklı para birimi.
- Sipariş, masa, ödeme.
- Admin'in restoran adına panele girmesi (impersonation).
- QR kod görseli üretimi (URL hazır: `/m/{public_code}`).
- Abonelik: online ödeme (sanal POS), resmi e-fatura ve PDF, owner'ın kendi kendine paket değiştirmesi, dönem içi kıst (proration), özel menü tasarımı altyapısı (yalnızca `allow_custom_design` bayrağı var), alt alan adı (`restoran.pixmenu.com`), otomatik DNS/SSL doğrulaması (bkz. `docs/ABONELIK_PLANI.md` §12).

---

## 16. Uygulama Sırası (Ajan Görev Planı)

Her aşama bir öncekinin testleri geçmeden başlamaz. Her aşama sonunda: `php spark migrate:refresh` hatasız, `vendor/bin/phpunit` yeşil.

| # | Aşama | Kabul kriteri |
|---|---|---|
| 1 | Kurulum, `.env`, Config ayarları (§3) | `php spark serve` açılır, DB bağlantısı çalışır |
| 2 | Tüm migration'lar (§4), bileşik FK'ler dahil | `migrate` / `migrate:rollback` hatasız |
| 3 | Seeder'lar (§13) | Diller, alerjenler, admin oluşur |
| 4 | `TenantContext`, `TenantModel`, tüm modeller (§6) | Test 13 geçer |
| 5 | Auth: AdminAuth, PanelAuth, filtreler (§7) | Test 11, 12 + throttle testi geçer |
| 6 | `TranslationService`, `LanguageResolver`, `ImageService` (§8, §12) | Fallback birim testleri geçer |
| 7 | Admin: dil, alerjen | CRUD + ikon yükleme çalışır |
| 8 | Admin: restoran, restoran dilleri, kullanıcılar, şubeler | §8.5 ve son-owner kuralları test edilir |
| 9 | Panel: dashboard, profil, restoran, şube, kullanıcı | Test 7, 9 geçer |
| 10 | Panel: kategori, ürün, alerjen atama, reorder | Test 1–6, 8, 10 geçer |
| 11 | `MenuService`, public HTML + JSON, cache invalidation | Menü testleri geçer |
| 12 | Güvenlik gözden geçirme: §2 maddelerinin tek tek kontrolü | Kontrol listesi raporlanır |

Her controller/servis için view dosya adları ve view'a giden değişkenler PR açıklamasında listelenir (frontend ekibi/ajanı için).

---

## 17. Varsayımlar

Bu doküman aşağıdaki varsayımlarla yazılmıştır; değişirse ilgili bölümler güncellenmelidir.

1. Restoranlar ve şubeleri admin açar; owner da kendi restoranına şube açabilir.
2. Restoranın hangi dilleri kullanacağını admin belirler; owner yalnızca şube varsayılan dilini seçer.
3. Kategoriler ve ürünler şubeye özeldir; aynı ürünün iki şubede olması iki ayrı kayıttır.
4. Para birimi restoran seviyesindedir (varsayılan TRY).
5. Restoran kullanıcısının e-postası tüm sistemde tekildir.
6. Menü ziyaretçiye `/m/{public_code}` ile (QR üzerinden) açılır.
