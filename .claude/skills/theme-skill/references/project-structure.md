# Bizim tema yapımız

CodeIgniter 4.7, view'lar `app/Views`. Tema: Velzon (HTML sürümü, HTTrack mirror'ı `admin_template/`, git'te yok).

Velzon admin/panel/login ile **herkese açık tanıtım sitesi** (landing `/`, yasal metinler, `/kayit` sihirbazı) içindir. Ziyaretçi menüsü ayrı bir uygulamadır: `menu-ui/` (Vue 3 + Tailwind, `GET /api/v1/site`); `app/Views/menu/*` yalnızca yedek HTML'dir. Bu skill menüye uygulanmaz — menü şablonları `menu-ui/src/templates/{kod}/` altındadır (bkz. `docs/VIEW_CONTRACT.md` "Menü sitesi API").

## Layout'lar

| Layout | Kullanan | Section'lar | Değişkenler |
|---|---|---|---|
| `layouts/admin.php` | `admin/*` | `content`, `css`, `js` | `currentAdmin{id,name,email}` (filtre verir), ops. `title`, `breadcrumbs` |
| `layouts/panel.php` | `panel/*` | `content`, `css`, `js` | `currentUser{id,name,email,role,restaurant_id}`, `isOwner`, `billingNotice` (PanelBaseController::render verir; null olabilir), ops. `title`, `breadcrumbs` |
| `layouts/auth.php` | `admin/auth/login`, `panel/auth/login`, `site/signup` | `heading`, `subtitle`, `content`, ops. `css`, `js` | ops. `title`, `authColClass` (kart sütunu; varsayılan `col-md-8 col-lg-6 col-xl-5`) |
| `layouts/landing.php` | `site/home`, `site/legal` | `content`, `js` | `seo{title, description, canonical, ogImage}`, `jsonLd` (list), `signupUrl`, `loginUrl`, `contact`, `anchorBase` (`''` ana sayfa, diğerlerinde `base_url()`) |

**Landing layout'u** `partials/layout/head`'i KULLANMAZ (o `noindex` basar): kendi `<head>`'inde `index,follow`, canonical, OG/Twitter, JSON-LD (`JSON_HEX_TAG`) ve 4 CSS vardır; `layout.js`/`app.js`/customizer yüklenmez. Gövde `layout-wrapper landing` + `site/partials/navbar` (Velzon `.navbar-landing`, yapışkan) + `<main>` + `site/partials/footer` (`.custom-footer`) + back-to-top; script'ler bootstrap → `js` section → `js/pages/landing.init.js`. Velzon `landing.html`'deki sahte müşteri logoları, yorumlar, ekip, lord-icon, `document.write`, `.bg-overlay-pattern` (görseli yok → 404) alınmadı. Görseller `public/assets/images/landing/` (SVG illüstrasyonlar + `og-image.png`).

admin/panel iskeleti (iki dosya aynı yapıda, yalnızca `$shell` dizisi farklı):

```
<html lang="tr" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" ...>
head  → partials/layout/head (pageTitle, withCsrfMeta=true) + sweetalert2.min.css + renderSection('css')
#layout-wrapper
  partials/layout/topbar  ($shell)
  partials/layout/sidebar ($shell)
  .main-content > .page-content > .container-fluid
      partials/layout/page_title (title, breadcrumbs)
      [yalnız panel] partials/billing/notice (notice = billingNotice, isOwner)
      partials/flash
      renderSection('content')
  partials/layout/footer
partials/layout/shell_end   (back-to-top, preloader, customizer)
partials/layout/scripts     (bootstrap.bundle, simplebar, waves, feather, sweetalert2, js/pix-confirm.js, js/pix-image-preview.js)
renderSection('js')
js/app.js
```

`<title>`: admin → `"{title|Yönetim} | PixMenu Admin"`, panel → `"{title|Panel} | PixMenu"`.

### `$shell` dizisi (layout dosyasının başında)

```php
$shell = [
    'user'         => $currentAdmin ?? [],           // panel: $currentUser
    'roleLabel'    => service('adminAuth')->roleLabel(), // Config\AdminRoles etiketi; panel: Restoran Sahibi / Şube Yöneticisi
    'homeUrl'      => site_url('admin'),
    'profileUrl'   => null,                           // panel: site_url('panel/profile')
    'logoutAction' => 'admin/logout',
    'menu'         => $visible,                       // admin: $menu, izne göre süzülmüş
];
```

Admin menüsünde her öğe `'can' => '<izin>'` taşır; layout `admin_can()` ile süzer, öğesi kalmayan başlığı da atar. Süzme döngüsünde `$title` kullanma (sayfa başlığıdır) — `$heading` kullanılır.

### Menü öğeleri

```php
['title' => 'Tanımlar']                                                    // bölüm başlığı
['label' => 'Diller', 'path' => 'admin/languages', 'icon' => 'ri-translate-2',
 'match' => ['admin/languages', 'admin/languages/*']]                      // match ops.; yoksa path
```
Aktiflik `url_is()` ile; tek seviyeli menü (alt menü/collapse yok). Mevcut menüler:
- **admin**: Gösterge Paneli (`admin`), Restoranlar · *Abonelik*: Paketler, Faturalar · *Tanımlar*: Diller, Alerjenler, Menü şablonları (`admin/templates`; kod anahtarlı, sıralama JSON `ids` string listesi) · *Sistem*: İşlem Kayıtları (`admin/logs`; salt okunur, izin `logs.view` — varsayılan yalnızca süper admin)
- **panel**: Gösterge Paneli, Şubeler · *Restoran* (yalnız owner): Restoran Bilgileri, Kullanıcılar, Abonelik · *Hesap*: Profil

Şubeye bağlı sayfalar (`panel/branches/{b}/categories|products`) "Şubeler" altında aktif görünür (`panel/branches/*`).

## Partial'lar (`app/Views/partials/layout/`)

| Dosya | Görev | Parametreler |
|---|---|---|
| `head.php` | meta, csrf meta, title, favicon, `window.PIX_ASSET_BASE`, layout.js, 4 CSS | `pageTitle`, `withCsrfMeta` |
| `logo.php` | Geçici metin logo (`ri-restaurant-2-line` + "PixMenu"), dark/light × sm/lg | `homeUrl`, `extraClass` ('' = sidebar, 'horizontal-logo' = topbar) |
| `topbar.php` | hamburger, tam ekran, açık/koyu mod, kullanıcı dropdown | `$shell` |
| `sidebar.php` | logo, sidebar-user dropdown (customizer'dan açılır), menü | `$shell` |
| `user_menu.php` | dropdown içeriği: e-posta, Profil (varsa), **Çıkış = POST form** | `user`, `profileUrl`, `logoutAction` |
| `page_title.php` | başlık + breadcrumb; `title` boşsa hiçbir şey basmaz (admin'de restoran altı sayfalarda breadcrumb'a restoran adı gerekir: controller `restaurant` satırına `AdminBaseController::restaurantName()` ile `name` ekler, sözleşmede "satır + `name`" olarak geçer) | `title`, `breadcrumbs[{label,url?}]` |
| `footer.php` | `© {yıl} PixMenu` | — |
| `shell_end.php` | back-to-top, preloader, customizer include | — |
| `customizer.php` | Velzon tema ayar paneli (Türkçe; corporate/galaxy/classic/vintage çıkarıldı; "Gizli" yerine "Sakla") | — |
| `scripts.php` | çekirdek JS + SweetAlert2 + `pix-confirm.js` (form[data-confirm] onayı) + `pix-image-preview.js` | — |

Diğer partial'lar (`app/Views/partials/`):
- `flash.php` — `success` / `info` / `warning` / `error` → `alert-*-dismissible alert-label-icon`, `errors` → liste. Login ve kayıt sayfalarında view içinden, diğerlerinde layout'tan çağrılır.
- `login_form.php` — `action`, `error` (429), `googleUrl|null`, `signupUrl|null` (hepsi açıkça geçilir; admin login ikisine `null`). email + göz ikonlu şifre (`password-addon.init.js`); panelde "veya" ayıracı + "Google ile giriş yap" + "Ücretsiz kayıt olun". Beni hatırla / şifremi unuttum **yok** (backend'de karşılığı yok).
- `translations.php` — çeviri alanları; parametreler yalnızca `languages`, `translations`, `fields` (başka parametre yok — `saveData` sızıntısı). Dil sekmeli **kendi kartını basar** ("Çeviriler" başlıklı, `nav-tabs-custom card-header-tabs`), alan bazlı `is-invalid` + hatalı sekmede ikon, gizli sekmedeki `required` için inline `invalid` dinleyicisi içerir (patterns §5). Çağıran view onu kart içine sarmaz; 7 form birden kullanır.
- `branch_fields.php` — admin+panel ortak şube formu gövdesi: iki kolon (çeviriler + genel bilgiler / tek "Yayın" kartı + Vazgeç/Kaydet). Parametre: `branch`, `languages`, `translations`, `cancel_url`; view yalnızca `form_open/form_close` ile sarar.
- `user_fields.php` — admin+panel ortak kullanıcı formu gövdesi (iki kolon + kaydet kartı). Parametre: `user`, `branches`, `assigned`, `allowRole`, `cancel_url`.
- `billing/` — abonelik/fatura parçaları (admin + panel ortak):
  - `badge.php` — durum rozeti; `kind` (`invoice` | `subscription` | `reason`), `status`. Renk/etiket eşlemesi burada; yeni durumda buraya ekle.
  - `subscription_card.php` — güncel abonelik kartı; `summary` (SubscriptionService::summary), `suspended`, `suspension_reason`, `actions` (başlığa basılan hazır HTML; boş string olabilir).
  - `invoice_doc.php` — yazdırılabilir fatura (Velzon `apps-invoices-details` düzeni); `invoice`, `restaurantName`, `seller`, `paymentInstructions`, `payUrl|null` (panelde ödenmemişse "Kartla öde"; admin `null`). Sayfa `css` section'ına `billing/print_css` ekler (yazdırırken sidebar/topbar gizlenir).
  - `notice.php` — panel uyarı bandı (deneme / gecikmiş fatura / askı); layout'tan çağrılır, view'a yazma. Deneme, `trial_expired`, `no_subscription` durumunda owner'a "Paket seç" (→ `panel/subscription/plans`).
  - `plan_card.php` — paket kartı (Velzon pages-pricing `.pricing-box`, "Popüler" şeridi, "Paketiniz" rozeti, `+KDV`); `plan`, `startYearly`, `isCurrent`, `footer` (hazır HTML: düğme / onaylı form / `btn-check` radyo). Panel paketler, landing fiyatlar ve kayıt sihirbazı ortak kullanır; sütunu çağıran verir.
- `site/partials/navbar.php`, `site/partials/footer.php` — landing navbar/footer; `signupUrl`, `loginUrl`, `anchorBase` (+ footer `contact`).

## Ortak JS (`public/assets/js/`)

- `pix-confirm.js` — `form[data-confirm]` submit'ini SweetAlert2 onayına bağlar. Öznitelikler: `data-confirm` (metin), `data-confirm-title`, `data-confirm-button`, `data-confirm-variant` (danger|warning|primary). Swal yoksa `confirm()` yedeği.
- `pix-image-preview.js` — `input[type=file][data-preview="#img"]` seçilince `#img`'yi gösterir (`d-none` kalkar); ops. `data-preview-placeholder="#yer-tutucu"` gizlenir. Sayfaya önizleme JS'i yazma.
- `pix-plan-cycle.js` — paket kartlarında aylık/yıllık: `.plan-nav [data-cycle]` düğmeleri, `.price-monthly/.price-yearly`, `input[data-cycle-input]` (gizli `billing_cycle`; pix-confirm submitter göndermediği için düğme değeri yerine), `a[data-cycle-href]` (`cycle=` güncellenir). Layout'ta değil; kullanan sayfanın `js` section'ında yüklenir.
- `pages/landing.init.js` (landing layout), `pages/signup.init.js` (kayıt sihirbazı: adım başına `reportValidity`, `data-start-step`, paket seçiminde kart vurgusu + düğme etiketi), `pages/checkout.init.js` (ödeme ekranı: `libs/card/card.js` önizlemesi, id tabanlı `formSelectors` — kart alanlarının `name`'i yoktur).

## Helper'lar (`app/Helpers/pixmenu_helper.php`, autoload)

- `asset_url(string $path): string` → `base_url('assets/'.$path)` + `?v=filemtime` (dosya varsa).
- `upload_url(?string $path): ?string` → yüklenen dosyanın tam URL'si (`public/uploads`).
- `pix_money($tutar, ?$paraBirimi)` → `1.499,90 TRY`; `pix_date(?$tarih, $saatli = false)` → `12.11.2026` (boşsa `—`). Fatura/abonelik view'larında tutar ve tarih bunlarla basılır.
- `pix_initials(string $adSoyad): string` → avatar baş harfleri: ilk + son kelimenin ilk harfi, Türkçe büyük harf (`"Şafak Bahçe"` → `ŞB`, `"irem"` → `İ`, boş → `?`). Controller'da hesaplanıp view'a `initials` olarak verilir (kalıp: `patterns.md` §3).
- `admin_can(string $izin): bool` → oturumdaki adminin rolü izni veriyor mu (`Config\AdminRoles`). Admin view'larında yetkisiz düğme/link/kartı **gizlemek** için: `<?php if (admin_can('restaurants.manage')): ?>…<?php endif ?>`. Asıl koruma rotadaki `adminperm:<izin>` filtresidir (403 → `admin/forbidden`); gizlemek yalnızca UX. İzin listesi: `app/Config/AdminRoles.php`.
- BaseController helper'ları: `url`, `form`, `text`, `pixmenu`.

## Veri akışı notları

- `old($key, $default, $escape = 'html')` değeri **escape ederek** döner; view'larda her çağrı `old(..., false)` biçimindedir ve çıktıda `esc()` uygulanır. Yeni kodda da bu biçimi koru.

- `Config\View::$saveData = true`: `view()` / `setVar()` ile verilen değişkenler aynı istekte sonraki view'lara geçer. Child view'da `$this->setVar('title', …)` layout'a ulaşır. Yan etkisi: partial'lar arasında değişken sızıntısı → partial'a her parametreyi açıkça ver.
- CSRF token her istekte yenilenir; AJAX'ta yanıttaki `X-CSRF-TOKEN` başlığı sonraki istekte kullanılır. İlk token `<meta name="csrf-token">` (admin/panel layout; auth layout'ta yok).
- Manager'ın yetkisiz sayfası 404 döner; menüde owner'a özel öğeler `isOwner` ile gizli.
