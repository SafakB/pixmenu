# Bizim tema yapımız

CodeIgniter 4.7, view'lar `app/Views`. Tema: Velzon (HTML sürümü, HTTrack mirror'ı `admin_template/`, git'te yok).

## Layout'lar

| Layout | Kullanan | Section'lar | Değişkenler |
|---|---|---|---|
| `layouts/admin.php` | `admin/*` | `content`, `css`, `js` | `currentAdmin{id,name,email}` (filtre verir), ops. `title`, `breadcrumbs` |
| `layouts/panel.php` | `panel/*` | `content`, `css`, `js` | `currentUser{id,name,email,role,restaurant_id}`, `isOwner`, ops. `title`, `breadcrumbs` |
| `layouts/auth.php` | `admin/auth/login`, `panel/auth/login` | `heading`, `subtitle`, `content` | ops. `title` |

admin/panel iskeleti (iki dosya aynı yapıda, yalnızca `$shell` dizisi farklı):

```
<html lang="tr" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" ...>
head  → partials/layout/head (pageTitle, withCsrfMeta=true) + sweetalert2.min.css + renderSection('css')
#layout-wrapper
  partials/layout/topbar  ($shell)
  partials/layout/sidebar ($shell)
  .main-content > .page-content > .container-fluid
      partials/layout/page_title (title, breadcrumbs)
      partials/flash
      renderSection('content')
  partials/layout/footer
partials/layout/shell_end   (back-to-top, preloader, customizer)
partials/layout/scripts     (bootstrap.bundle, simplebar, waves, feather, sweetalert2, js/pix-confirm.js)
renderSection('js')
js/app.js
```

`<title>`: admin → `"{title|Yönetim} | PixMenu Admin"`, panel → `"{title|Panel} | PixMenu"`.

### `$shell` dizisi (layout dosyasının başında)

```php
$shell = [
    'user'         => $currentAdmin ?? [],           // panel: $currentUser
    'roleLabel'    => 'Sistem Yöneticisi',            // panel: Restoran Sahibi / Şube Yöneticisi
    'homeUrl'      => site_url('admin'),
    'profileUrl'   => null,                           // panel: site_url('panel/profile')
    'logoutAction' => 'admin/logout',
    'menu'         => [ ... ],
];
```

### Menü öğeleri

```php
['title' => 'Tanımlar']                                                    // bölüm başlığı
['label' => 'Diller', 'path' => 'admin/languages', 'icon' => 'ri-translate-2',
 'match' => ['admin/languages', 'admin/languages/*']]                      // match ops.; yoksa path
```
Aktiflik `url_is()` ile; tek seviyeli menü (alt menü/collapse yok). Mevcut menüler:
- **admin**: Gösterge Paneli (`admin`), Restoranlar · *Tanımlar*: Diller, Alerjenler
- **panel**: Gösterge Paneli, Şubeler · *Restoran* (yalnız owner): Restoran Bilgileri, Kullanıcılar · *Hesap*: Profil

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
| `scripts.php` | çekirdek JS + SweetAlert2 + `pix-confirm.js` (form[data-confirm] onayı) | — |

Diğer partial'lar (`app/Views/partials/`):
- `flash.php` — `success` / `error` → `alert-*-dismissible alert-label-icon`, `errors` → liste. Login sayfasında `login_form` içinden, diğerlerinde layout'tan çağrılır.
- `login_form.php` — `action`, `error` (429). email + göz ikonlu şifre (`password-addon.init.js`). Beni hatırla / şifremi unuttum / kayıt **yok** (backend'de karşılığı yok).
- `translations.php` — çeviri alanları; parametreler yalnızca `languages`, `translations`, `fields` (başka parametre yok — `saveData` sızıntısı). Dil sekmeli **kendi kartını basar** ("Çeviriler" başlıklı, `nav-tabs-custom card-header-tabs`), alan bazlı `is-invalid` + hatalı sekmede ikon, gizli sekmedeki `required` için inline `invalid` dinleyicisi içerir (patterns §5). Çağıran view onu kart içine sarmaz; 7 form birden kullanır.
- `branch_fields.php` — admin+panel ortak şube formu gövdesi: iki kolon (çeviriler + genel bilgiler / tek "Yayın" kartı + Vazgeç/Kaydet). Parametre: `branch`, `languages`, `translations`, `cancel_url`; view yalnızca `form_open/form_close` ile sarar.
- `user_fields.php` — admin+panel ortak kullanıcı formu parçası (henüz stilsiz).

## Ortak JS (`public/assets/js/`)

- `pix-confirm.js` — `form[data-confirm]` submit'ini SweetAlert2 onayına bağlar. Öznitelikler: `data-confirm` (metin), `data-confirm-title`, `data-confirm-button`, `data-confirm-variant` (danger|warning|primary). Swal yoksa `confirm()` yedeği.

## Helper'lar (`app/Helpers/pixmenu_helper.php`, autoload)

- `asset_url(string $path): string` → `base_url('assets/'.$path)` + `?v=filemtime` (dosya varsa).
- `upload_url(?string $path): ?string` → yüklenen dosyanın tam URL'si (`public/uploads`).
- BaseController helper'ları: `url`, `form`, `text`, `pixmenu`.

## Veri akışı notları

- `old($key, $default, $escape = 'html')` değeri **escape ederek** döner; view'larda her çağrı `old(..., false)` biçimindedir ve çıktıda `esc()` uygulanır. Yeni kodda da bu biçimi koru.

- `Config\View::$saveData = true`: `view()` / `setVar()` ile verilen değişkenler aynı istekte sonraki view'lara geçer. Child view'da `$this->setVar('title', …)` layout'a ulaşır. Yan etkisi: partial'lar arasında değişken sızıntısı → partial'a her parametreyi açıkça ver.
- CSRF token her istekte yenilenir; AJAX'ta yanıttaki `X-CSRF-TOKEN` başlığı sonraki istekte kullanılır. İlk token `<meta name="csrf-token">` (admin/panel layout; auth layout'ta yok).
- Manager'ın yetkisiz sayfası 404 döner; menüde owner'a özel öğeler `isOwner` ile gizli.
