# PixMenu — View Sözleşmesi (Frontend İçin)

Backend her view'ın **dosya adını** ve view'a geçirilen **değişkenleri** belirler; içerik (HTML, Bootstrap, CSS, JS) frontend'e aittir. `app/Views` altındaki mevcut dosyalar yalnızca backend'i elle deneyebilmek için yazılmış stilsiz, minimal view'lardır — üzerine yazılabilir.

## Genel kurallar

- **Her çıktı `esc()` ile basılır.** Öznitelik içinde `esc($x, 'attr')`.
- **View içinde model/servis çağrısı yapılmaz**; ihtiyaç duyulan her şey aşağıdaki değişkenlerle gelir.
- **Formlar düz HTML + POST'tur** (method spoofing yok). Her formda CSRF alanı bulunmalıdır: `form_open()` otomatik ekler; elle yazılan formda `<?= csrf_field() ?>`.
- **Flash anahtarları:** `session('success')` (string), `session('error')` (string), `session('errors')` (dizi: alan => mesaj). Örnek: `partials/flash.php`.
- **Eski girdi:** doğrulama hatasında `redirect()->back()->withInput()` yapılır; `old('alan', $varsayilan)` kullanılır.
- **Hata alan adları:** çeviri alanları `translations.{kod}.{alan}` (ör. `translations.tr.name`), diğerleri form alan adıyla (`price`, `category_id`, `branch_ids`, `slug`…).
- **Checkbox alanları** (`is_active`, `remove_image`) `value="1"` ile gönderilir; gönderilmezse 0 sayılır.
- **AJAX:** `X-Requested-With: XMLHttpRequest` ve `X-CSRF-TOKEN: <token>` başlıkları gönderilir. Token her istekte yenilendiği için **yanıttaki `X-CSRF-TOKEN` başlığı bir sonraki istekte kullanılmalıdır.** İlk token layout'taki `<meta name="csrf-token">` içindedir.
- **JSON yanıt biçimi:** `{"status":"success","data":{},"message":"..."}` / `{"status":"error","message":"...","errors":{}}`. Durum kodları: 200, 401 (oturum yok), 404, 422 (doğrulama / iş kuralı), 429.
- **`restaurant_id` hiçbir formda gönderilmez**; gönderilse de yok sayılır.

## Çeviri alanları (tüm çevrilebilir formlar)

```
translations[tr][name]=...
translations[tr][description]=...
translations[en][name]=...
```

View'a iki değişken gelir:

| Değişken | Yapı |
|---|---|
| `languages` | `[{id, code, native_name, direction, is_default}]` — `is_default=true` olan dilde `name` **zorunludur** |
| `translations` | `{ "tr": {name, description, ...}, "en": {...} }` (kayıt yoksa boş dizi) |

Zorunlu dil: kategori/ürün için **şubenin** varsayılan dili; restoran ve şube bilgileri için **restoranın** varsayılan dili; alerjen için `tr`. Zorunlu olmayan bir dilde `name` boş gönderilirse o dilin çevirisi silinir. Hazır örnek: `partials/translations.php` (`fields` parametresi: `['name' => 'text', 'description' => 'textarea']`).

## Ortak layout değişkenleri

| Layout | Değişkenler |
|---|---|
| `layouts/admin.php` | `currentAdmin{id, name, email}` |
| `layouts/panel.php` | `currentUser{id, name, email, role, restaurant_id}`, `isOwner` (bool), `billingNotice` (uyarı bandı: `{type: trial\|overdue\|suspended, level, days?, count?, reason?}` veya null) |
| `layouts/auth.php` | — |

Tema: **Velzon** (`admin_template/` kaynak, depoda yok). Layout'lar `partials/layout/*` parçalarından oluşur (head, topbar, sidebar, page_title, footer, customizer, scripts, logo).

| Layout | Section'lar | Opsiyonel değişkenler |
|---|---|---|
| `layouts/admin`, `layouts/panel` | `content`, `css` (sayfaya özel stil), `js` (sayfaya özel script; çekirdek kütüphanelerden sonra, `app.js`'ten önce basılır) | `title` (sayfa başlığı + `<title>`), `breadcrumbs` (`[{label, url?}]`; url'siz öğe aktif sayılır) |
| `layouts/auth` | `heading`, `subtitle`, `content` | `title` |

- Asset yolu: `asset_url('libs/sweetalert2/sweetalert2.min.js')` → `public/assets/...` + `?v=filemtime`.
- Menü öğeleri layout dosyalarının başındaki dizilerdedir; aktif öğe `url_is()` ile belirlenir.
- Temanın `plugins.js` dosyası **kullanılmaz**; Choices, flatpickr, Toastify gibi kütüphaneler gereken sayfanın `js` section'ında yüklenir.
- `public/assets/js/app.js` temadan alınıp yamalanmıştır (bkz. `public/assets/README.md`); tema güncellenirse yamalar yeniden uygulanmalıdır.

---

## Admin (`/admin`)

| View | Rota | Değişkenler |
|---|---|---|
| `admin/auth/login` | `GET admin/login` | `error` (string\|null; 429'da dolu). Form: `POST admin/login` → `email`, `password` |
| `admin/dashboard` | `GET admin` | `stats{restaurants, branches, users, languages, allergens}`, `billing{overdue_count, overdue_sum{para birimi: tutar}, overdue[{id, number, restaurant_id, restaurant_name, total, currency_code, due_date, days_late}] (≤ 5), trials[{restaurant_id, restaurant_name, current_period_end, days_left}] (7 gün içinde biten), suspended[{id, name, suspension_reason, suspended_at}]}` |
| `admin/languages/index` | `GET admin/languages` | `languages[{id, code, name, native_name, direction, is_active, sort_order}]` |
| `admin/languages/form` | `GET admin/languages/new`, `…/{id}/edit` | `language` (satır\|null), `action`. Alanlar: `code` (yalnızca oluşturmada), `name`, `native_name`, `direction` (ltr\|rtl), `is_active`, `sort_order` |
| `admin/allergens/index` | `GET admin/allergens` | `allergens[{id, code, name, icon_url, is_active, sort_order}]` |
| `admin/templates/index` | `GET admin/templates` | `templates[{code, name, description, is_active, sort_order, branch_count, is_default}]` (`branch_count`: kullanan silinmemiş şube; `is_default`: `standart` — silinemez/pasifleştirilemez) |
| `admin/templates/form` | `GET admin/templates/new`, `…/{code}/edit` | `template` (satır + `is_default` \| null), `action`. Alanlar: `code` (yalnızca oluşturmada; `^[a-z0-9]+(-[a-z0-9]+)*$`, ≤ 30, `new`/`reorder` yasak), `name`, `description` (ops.), `is_active`, `sort_order` |
| `admin/allergens/form` | `GET admin/allergens/new`, `…/{id}/edit` | `allergen` (satır\|null), `icon_url`, `languages`, `translations`, `action`. Alanlar: `code`, `icon` (dosya: svg/png/webp ≤ 512 KB), `is_active`, `sort_order`, `translations[*][name\|description]`. `multipart/form-data` |
| `admin/restaurants/index` | `GET admin/restaurants` | `restaurants[{id, slug, name, currency_code, is_active, plan_name\|null, plan_badge_class\|null (paketin `badge_class`'ı; boşsa `bg-primary`), subscription_status\|null, suspended, suspension_reason\|null}]` |
| `admin/restaurants/new` | `GET admin/restaurants/new` | `allLanguages[{id, code, native_name, ...}]` (dil seçimi), `languages` (çeviri alanları). Alanlar: `slug` (ops.), `currency_code`, `language_ids[]`, `default_language_id`, `logo` (dosya), `is_active`, `translations[*][name\|description]`, `owner_name`, `owner_email`, `owner_password` (ilk owner; ops.), `plan_id` (boş = varsayılan deneme paketi), `billing_cycle` (monthly\|yearly; deneme paketinde yok sayılır). Ek değişkenler: `plans[{id, code, name, trial_days, price_monthly, price_yearly, currency_code}]` (aktif), `defaultPlanId`, `currencies[{code, name, symbol, ...}]` (aktif; `currency_code` seçimi) |
| `admin/restaurants/show` | `GET admin/restaurants/{id}` | `restaurant{id, slug, name, currency_code, is_active, logo_url}`, `counts{branches, users, languages}`, `branches[{id, slug, name, public_code, menu_url, is_active}]`, `users[{id, name, email, role, is_active, branch_count}]`, `languages[{id, code, native_name, is_default}]` (aktif diller), `billing{summary, suspended, suspension_reason, open_invoices, overdue_invoices, domain\|null}` (`summary` yapısı aşağıda) |
| `admin/restaurants/edit` | `GET admin/restaurants/{id}/edit` | `restaurant` (satır), `logo_url`, `languages`, `translations`, `currencies[{code, name, symbol, ...}]` (aktif + mevcut), `currencyLocked` (bool; ürün varsa para birimi değiştirilemez). Alanlar: `slug`, `currency_code`, `default_language_id`, `logo`, `is_active`, `translations[*]` |
| `admin/restaurants/languages` | `GET admin/restaurants/{id}/languages` | `restaurant` (satır + `name`), `languages[{id, code, native_name, is_active, selected, is_default}]`. Alan: `language_ids[]` (işaretliler kalır, diğerleri çıkarılır) |
| `admin/users/index` | `GET admin/restaurants/{id}/users` | `restaurant` (satır + `name`), `users[{id, name, email, role, is_active, branch_ids[]}]` |
| `admin/users/form` | `GET …/users/new`, `…/users/{uid}/edit` | `restaurant` (satır + `name`), `user` (satır\|null; `password_hash` yok), `branches[{id, name}]`, `assigned[]` (şube ID'leri), `action`. Alanlar: `name`, `email`, `password` (düzenlemede boş = değişmez), `role` (owner\|manager), `branch_ids[]` (manager için ≥ 1), `is_active` |
| `admin/branches/index` | `GET admin/restaurants/{id}/branches` | `restaurant` (satır + `name`), `branches[{id, slug, name, public_code, menu_url, template_code, template_name, is_active, sort_order, category_count, product_count, managers[{name, initials}]}]` (`managers`: şubeye atanmış aktif manager'lar) |
| `admin/branches/form` | `GET …/branches/new`, `…/branches/{bid}/edit` | `restaurant` (satır + `name`), `branch` (satır\|null), `languages`, `translations`, `templates[{code, name, is_active, ...}]` (aktif + mevcut), `action`. Alanlar: `slug` (ops.), `default_language_id`, `template_code` (yalnızca admin), `phone`, `is_active`, `sort_order`, `translations[*][name\|address\|description]` |

### Abonelik ve faturalama (admin)

`summary` (admin + panel ortak; `SubscriptionService::summary`): `{subscription{…satır, plan_name, cycle_label, status_label}|null, plan{…satır, name, description}|null, usage{branches, max_branches|null}, days_left|null, grace_days, can_add_branch}`. Durum değerleri — abonelik: `trialing|active|past_due|suspended|ended`; fatura `display_status`: `pending|overdue|paid|void`; askı nedeni: `overdue|trial_expired|no_subscription|manual`. Hazır parçalar: `partials/billing/{badge, subscription_card, invoice_doc, print_css, notice}`.

| View | Rota | Değişkenler |
|---|---|---|
| `admin/plans/index` | `GET admin/plans` | `plans[{id, code, badge_class\|null, name, description, max_branches, allow_custom_domain, allow_custom_design, trial_days, price_monthly, price_yearly, currency_code, is_featured, is_active, sort_order, current_count}]`. Sıralama: `POST admin/plans/reorder` (JSON) |
| `admin/plans/form` | `GET admin/plans/new`, `…/{id}/edit` | `plan` (satır\|null), `languages`, `translations`, `action`, `currencies[{code, name, symbol, ...}]` (aktif + mevcut). Alanlar: `code`, `badge_class` (ops.; paket rozetinin CSS class'ı, ör. `bg-primary`; boş = `bg-primary`), `price_monthly`, `price_yearly` (virgüllü giriş kabul), `currency_code`, `max_branches` (boş = sınırsız), `trial_days` (boş = ücretli), `allow_custom_domain`, `allow_custom_design`, `is_featured`, `is_active`, `sort_order`, `translations[*][name\|description]` (tr zorunlu) |
| `admin/subscriptions/show` | `GET admin/restaurants/{id}/subscription` | `restaurant` (satır + `name`), `summary`, `history[{…satır, plan_name, cycle_label, status_label}]`, `invoices[{…satır, display_status}]`, `plans` (aktif; `admin/plans/index` yapısı), `defaultGraceDays`. Formlar: `POST …/subscription/change` → `plan_id`, `billing_cycle`, `discount` (ops.), `notes` (ops.); `…/cancel` → `cancel` (1\|0); `…/suspend`, `…/resume` (gövdesiz); `…/grace` → `grace_days` (boş = varsayılan); `…/discount` → `discount_percent` (boş = kaldır, 0–100), `discount_until` (Y-m-d, boş = süresiz). `restaurant` satırı `discount_percent`, `discount_until` içerir |
| `admin/invoices/index` | `GET admin/invoices?status=&overdue=1&restaurant=` | `filters{status, overdue, restaurant}`, `restaurants[{id, name}]`, `invoices[{…satır, restaurant_name, display_status}]` (en yeni 500), `totals{pending_count, overdue_count, pending_sum{para birimi: tutar}}`, `limited` |
| `admin/invoices/show` | `GET admin/invoices/{id}` | `invoice` (satır + `display_status`, `method_label`), `restaurant` (satır + `name`), `methods{kod: etiket}`, `today`, `seller{name, address, tax_info}`, `paymentInstructions`. Formlar: `POST …/pay` → `payment_method` (bank_transfer\|cash\|card\|other), `paid_at` (Y-m-d), `payment_reference` (ops.); `POST …/void` (gövdesiz, yalnızca ödenmemiş); `POST …/discount` → `discount` (TL, KDV hariç; boş = 0; yalnızca ödenmemiş) |
| `admin/restaurants/domain` | `GET admin/restaurants/{id}/domain` | `restaurant` (satır + `name`), `domain` (satır\|null), `allowed` (paket izin veriyor mu), `appHost`, `menuUrl\|null`. Formlar: `POST …/domain` → `domain`, `is_active`; `…/domain/verify` → `verified` (1\|0); `…/domain/delete` |
| `admin/logs/index` | `GET admin/logs?area=&restaurant=&actor=&action=&from=&to=&page=` | İzin `logs.view` (varsayılan: yalnızca süper admin). `filters{area, restaurant, actor, action, from, to}`, `restaurants[{id, name}]`, `logs[{id, created_at, area (admin\|panel), actor_id, actor_label, restaurant_id\|null, restaurant_name\|null, action, action_label, path, ip, detail{anahtar: değer}}]` (en yeni önce), `page`, `hasMore`, `retentionDays`. Salt okunur; form yok (filtre GET) |

Onay isteyen abonelik işlemleri: paket değişimi (`primary`), dönem sonunda iptal ve elle askıya alma (`warning`), fatura iptali ve alan adı kaldırma (`danger`).

Tek tuşluk POST işlemleri (gövdesiz form): `…/toggle`, `…/delete`, `allergens/{id}/icon/delete`, `branches/{bid}/regenerate-code` (**eski QR kodlarını geçersiz kılar — onay/uyarı gösterilmeli**).

Sıralama (JSON): `POST admin/languages/reorder`, `POST admin/allergens/reorder` → gövde `{"ids":[3,1,2]}`; `POST admin/templates/reorder` → gövde `{"ids":["standart","modern"]}` (kodlar).

## Restoran paneli (`/panel`)

| View | Rota | Erişim | Değişkenler |
|---|---|---|---|
| `panel/auth/login` | `GET panel/login` | — | `error`. Form: `POST panel/login` → `email`, `password` |
| `panel/dashboard` | `GET panel` | owner + manager | `restaurantName`, `branches[{id, name, is_active, public_code, menu_url, category_count, product_count, managers[{name, initials}]}]` (`managers`: şubeye atanmış aktif manager'lar; `initials` Türkçe büyük harfle "ŞB") (manager: yalnızca atanmış şubeler) |
| `panel/profile` | `GET panel/profile` | owner + manager | `user{id, name, email, role}`. Formlar: `POST panel/profile` → `name`, `email`; `POST panel/profile/password` → `current_password`, `password`, `password_confirm` |
| `panel/restaurant` | `GET panel/restaurant` | owner | `restaurant{slug, currency_code}` (salt okunur), `logo_url`, `languages`, `translations`. Alanlar: `logo` (dosya), `translations[*][name\|description]`. Logo silme: `POST panel/restaurant/logo/delete` |
| `panel/branches/index` | `GET panel/branches` | owner + manager | `branches[{id, slug, name, public_code, menu_url, is_active, sort_order, category_count, product_count, managers[{name, initials}]}]` (`managers`: şubeye atanmış aktif manager'lar), `canAddBranch` (paket limiti dolu değil), `maxBranches\|null`. Limit doluysa "Yeni şube" pasif gösterilir |
| `panel/branches/form` | `GET panel/branches/new`, `…/{id}/edit` | owner | `branch` (satır\|null), `languages`, `translations`, `action`. Alanlar: admin şube formuyla aynı, `template_code` hariç (şablonu yalnızca admin seçer; panel güncellemesi şablona dokunmaz, panelden açılan şube `standart` alır) |
| `panel/users/index` | `GET panel/users` | owner | `users[{id, name, email, role, is_active, branch_ids[], editable}]` (`editable=false` → owner kayıtları; düzenleme/silme bağlantısı gösterilmez) |
| `panel/users/form` | `GET panel/users/new`, `…/{id}/edit` | owner | `user` (satır\|null), `branches[{id, name}]`, `assigned[]`, `action`. Alanlar: `name`, `email`, `password`, `branch_ids[]` (≥ 1), `is_active`. **Rol alanı yoktur**; panelden açılan kullanıcı her zaman manager'dır |
| `panel/categories/index` | `GET panel/branches/{b}/categories` | owner + manager | `branch{id, name}`, `categories[{id, name, image_url, is_active, sort_order, product_count}]` |
| `panel/categories/form` | `GET …/categories/new`, `…/categories/{c}/edit` | owner + manager | `branch{id, name}`, `category` (satır\|null), `image_url`, `languages`, `translations`, `action`. Alanlar: `image` (dosya ≤ 3 MB), `remove_image`, `is_active`, `sort_order` (boş = sona ekle), `translations[*][name\|description]` |
| `panel/products/index` | `GET panel/branches/{b}/products?category=&q=&min_price=&max_price=` | owner + manager | `branch{id, name}`, `categories[{id, name}]`, `filters{category, q, min_price, max_price}` (fiyatlar float\|null; sayı olmayan/negatif değer yok sayılır, min > max ise yer değiştirir), `price_range{min, max}` (şubedeki tüm ürünlerin fiyat sınırları), `currency`, `products[{id, name, category_id, category_name, price, thumb_url, is_active, sort_order}]` |
| `panel/products/form` | `GET …/products/new`, `…/products/{p}/edit` | owner + manager | `branch{id, name}`, `product` (satır\|null), `image_url`, `categories[{id, name}]`, `allergens[{id, code, name, icon_url}]`, `selectedAllergens{allergen_id: level}`, `languages`, `translations`, `currency`, `action`. Alanlar: `category_id`, `price` (virgüllü giriş kabul edilir: `129,90`), `image` (dosya ≤ 5 MB), `is_active`, `sort_order`, `translations[*][name\|description]`, `allergens[{allergen_id}]=contains\|may_contain` (boş değer = seçilmemiş; gönderilmeyenler silinir) |

Tek tuşluk POST işlemleri: `branches/{b}/toggle|delete`, `users/{u}/toggle|delete`, `…/categories/{c}/toggle|delete`, `…/products/{p}/toggle|delete`, `…/products/{p}/image/delete`, `panel/logout`.

Sıralama (JSON): `POST panel/branches/{b}/categories/reorder`, `POST panel/branches/{b}/products/reorder` → `{"ids":[12,5,9]}`. ID'lerden biri bile bu şubeye ait değilse 422 döner ve hiçbir sıra değişmez.

| `panel/subscription/index` | `GET panel/subscription` | owner | `summary`, `suspended`, `suspension_reason`, `invoices[{…satır, display_status}]`, `paymentInstructions` |
| `panel/subscription/invoice` | `GET panel/subscription/invoices/{id}` | owner | `invoice` (satır + `display_status`, `method_label`), `restaurantName`, `seller`, `paymentInstructions`. Başka restoranın faturası → 404 |
| `panel/subscription/plans` | `GET panel/subscription/plans` | owner | `plans` (aktif; admin yapısı), `currentPlanId\|null`, `currentCycle\|null`, `taxRate`. Bilgi amaçlı; satın alma yok |

**Askıdaki restoran:** owner/manager giriş yapabilir; gösterge paneli, profil ve abonelik sayfaları açıktır. Diğer sayfalar owner'ı `panel/subscription`'a, manager'ı `panel`'e yönlendirir (`error` flash); AJAX istekleri 403 alır.

Manager, owner'a özel sayfalara giderse veya atanmadığı bir şubeye erişirse yanıt **404**'tür (403 değil).

## Herkese açık menü

Ziyaretçi menüsü `menu-ui/` (Vue SPA) uygulamasıdır ve aşağıdaki **Menü sitesi API**'sini kullanır. CI4'ün HTML view'ları yedek olarak kalır.

| View | Rota | Değişkenler |
|---|---|---|
| `menu/show` | `GET m/{public_code}?lang=xx` | `menu` — aşağıdaki JSON'ın `data` kısmıyla **aynı yapı**. Restoranın kanonik menü adresi varsa (özel alan adı veya `menuRootDomain` alt alan adı) view yerine **302** ile `{kanonik}/{şube-slug}?lang=xx` adresine yönlenir |
| `menu/show` (özel alan adı) | `GET /`, `GET /{şube-slug}` (restoranın alan adında) | `menu`, `menuUrl` (dil bağlantılarının tabanı: `/m/{kod}`, `/` veya `/{slug}`) |
| `menu/branches` | `GET /` (özel alan adı, birden fazla aktif şube) | `restaurant{name, logo_url}`, `branches[{slug, name, address, url}]` |
| `errors/not_found` | tüm 404'ler | `message` |

`GET /api/v1/menu/{public_code}?lang=xx` (CSRF yok, `Cache-Control: public, max-age=60`):

```json
{
  "status": "success",
  "data": {
    "restaurant": { "name": "", "description": null, "logo_url": null, "currency": "TRY" },
    "branch":     { "code": "k3f9a0c21b", "name": "", "address": null, "phone": null, "template": "standart" },
    "language":   { "code": "tr", "direction": "ltr" },
    "languages":  [ { "code": "tr", "native_name": "Türkçe" } ],
    "allergens":  [ { "id": 1, "code": "gluten", "name": "Gluten", "icon_url": null } ],
    "categories": [
      { "id": 10, "name": "", "description": null, "image_url": null,
        "products": [
          { "id": 88, "name": "", "description": null, "price": "129.90",
            "image_url": null, "thumb_url": null,
            "allergens": [ { "id": 1, "level": "contains" } ] }
        ] }
    ]
  }
}
```

- `price` string'dir; biçimlendirme `Intl.NumberFormat(lang, {style:'currency', currency})` ile yapılır.
- `branch.template`: admin'in şubeye atadığı menü şablonu kodu (`menu_templates.code`, varsayılan `standart`). Tasarım `menu-ui/src/templates/{kod}/` altındadır: `standart` (tek sayfa) ve `kategori` (önce kategori kartları). Ön uçta karşılığı olmayan kod `standart` ile gösterilir.
- Ürünler alerjenlere `id` ile referans verir; adlar üstteki `allergens` dizisindedir.
- `language.direction` `rtl` olabilir (`<html dir>`).
- Dil seçimi: geçerli `?lang` → `pixmenu_lang_{public_code}` çerezi (HTML menüde; 30 gün) → şubenin varsayılan dili. JSON API çerez okumaz/yazmaz.
- Ürünü olmayan, pasif veya silinmiş kategoriler/ürünler listede yer almaz.
- Restoran askıdaysa (abonelik) menü 404 döner.

### Menü sitesi API (menu-ui)

`GET /api/v1/site?restaurant={slug}&branch={şube-slug}&lang=xx` veya `GET /api/v1/site?host={hostname}&branch=…&lang=…`

Restoran üç yoldan bulunur:

- `restaurant`: restoranın slug'ı (menu-ui `.env` → `VITE_RESTAURANT`).
- `host` alt alan adı: `{slug}.{Pixmenu::$menuRootDomain}`. Tek etiket olmalı; `Pixmenu::$reservedSubdomains` içindekiler reddedilir.
- `host` özel alan adı: `DomainService::resolve()` (paket `allow_custom_domain` + alan adı aktif).

Restoran aktif, silinmemiş ve askıda olmamalıdır; aksi hâlde 404 (`json_error_body`).

```json
{
  "status": "success",
  "data": {
    "restaurant":    { "slug": "sirketadi", "name": "", "logo_url": null },
    "language":      { "code": "tr", "direction": "ltr" },
    "languages":     [ { "code": "tr", "native_name": "Türkçe" } ],
    "branches":      [ { "slug": "merkez", "code": "k3f9a0c21b", "name": "", "address": null } ],
    "canonical_url": "https://sirketadi.pixmenu.com",
    "menu":          { "…": "GET /api/v1/menu/{code} `data` yapısının aynısı" }
  }
}
```

- `menu`: `branch` verildiyse o şubenin menüsü (bilinmeyen/pasif şube → 404). Verilmediyse tek aktif şube varsa onun menüsü, birden fazlaysa `null` (ön uç şube seçimini gösterir).
- `language`: menü varsa menünün dili, yoksa `?lang` (restoranın aktif dillerindeyse) veya restoranın varsayılan dili. Şube adları bu dil → restoran varsayılanı zinciriyle çözülür.
- `canonical_url` (sonunda `/` yok): aktif ve paketi uygun özel alan adı → `menuScheme://alan-adı`; değilse `menuRootDomain` doluysa `menuScheme://slug.kök[:menuPort]`; değilse `null`. Host modunda ön uç, adres çubuğundaki host farklıysa buraya yönlenir.
- Admin/panel `menu_url` değerleri de aynı kuraldan üretilir: `{canonical_url}/{şube-slug}`, kanonik adres yoksa `m/{public_code}`.
- Durumsuz ve çerezsizdir, `Cache-Control: public, max-age=60`.

**CORS:** `api/*` için `cors:menu` filtresi (`Config\Cors::$menu`) uygulanır: `Access-Control-Allow-Origin: *`, yalnızca `GET`, credentials kapalı. Preflight için `OPTIONS api/v1/*` rotası vardır. İstemci özel başlık göndermemelidir (basit istek).

**Restoran slug'ı** alt alan adı olduğu için en fazla 63 karakterdir ve ayrılmış adlardan (`www`, `admin`, `api`, …) biri olamaz.

**Config** (`.env`): `pixmenu.menuRootDomain` (boş = alt alan adı kapalı), `pixmenu.menuScheme` (varsayılan `https`), `pixmenu.menuPort` (yalnızca yerel geliştirme).

## E-posta şablonları (`emails/billing/*`)

`invoice_issued`, `invoice_reminder`, `invoice_overdue` (`invoice`, ayrıca `graceDays`), `suspended` (`reason`), `trial_reminder` (`subscription`, `daysLeft`), `trial_expired` (`reason`). Hepsi `subject`, `restaurantName`, `panelUrl`, `billing` (Config\Billing) alır ve `emails/billing/layout`'u kullanır. `saveData` kapalı render edilir.
