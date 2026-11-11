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
| `layouts/panel.php` | `currentUser{id, name, email, role, restaurant_id}`, `isOwner` (bool) |
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
| `admin/dashboard` | `GET admin` | `stats{restaurants, branches, users, languages, allergens}` |
| `admin/languages/index` | `GET admin/languages` | `languages[{id, code, name, native_name, direction, is_active, sort_order}]` |
| `admin/languages/form` | `GET admin/languages/new`, `…/{id}/edit` | `language` (satır\|null), `action`. Alanlar: `code` (yalnızca oluşturmada), `name`, `native_name`, `direction` (ltr\|rtl), `is_active`, `sort_order` |
| `admin/allergens/index` | `GET admin/allergens` | `allergens[{id, code, name, icon_url, is_active, sort_order}]` |
| `admin/allergens/form` | `GET admin/allergens/new`, `…/{id}/edit` | `allergen` (satır\|null), `icon_url`, `languages`, `translations`, `action`. Alanlar: `code`, `icon` (dosya: svg/png/webp ≤ 512 KB), `is_active`, `sort_order`, `translations[*][name\|description]`. `multipart/form-data` |
| `admin/restaurants/index` | `GET admin/restaurants` | `restaurants[{id, slug, name, currency_code, is_active}]` |
| `admin/restaurants/new` | `GET admin/restaurants/new` | `allLanguages[{id, code, native_name, ...}]` (dil seçimi), `languages` (çeviri alanları). Alanlar: `slug` (ops.), `currency_code`, `language_ids[]`, `default_language_id`, `logo` (dosya), `is_active`, `translations[*][name\|description]`, `owner_name`, `owner_email`, `owner_password` (ilk owner; ops.) |
| `admin/restaurants/show` | `GET admin/restaurants/{id}` | `restaurant{id, slug, name, currency_code, is_active, logo_url}`, `counts{branches, users, languages}` |
| `admin/restaurants/edit` | `GET admin/restaurants/{id}/edit` | `restaurant` (satır), `logo_url`, `languages`, `translations`. Alanlar: `slug`, `currency_code`, `default_language_id`, `logo`, `is_active`, `translations[*]` |
| `admin/restaurants/languages` | `GET admin/restaurants/{id}/languages` | `restaurant` (satır), `languages[{id, code, native_name, is_active, selected, is_default}]`. Alan: `language_ids[]` (işaretliler kalır, diğerleri çıkarılır) |
| `admin/users/index` | `GET admin/restaurants/{id}/users` | `restaurant`, `users[{id, name, email, role, is_active, branch_ids[]}]` |
| `admin/users/form` | `GET …/users/new`, `…/users/{uid}/edit` | `restaurant`, `user` (satır\|null; `password_hash` yok), `branches[{id, name}]`, `assigned[]` (şube ID'leri), `action`. Alanlar: `name`, `email`, `password` (düzenlemede boş = değişmez), `role` (owner\|manager), `branch_ids[]` (manager için ≥ 1), `is_active` |
| `admin/branches/index` | `GET admin/restaurants/{id}/branches` | `restaurant`, `branches[{id, slug, name, public_code, menu_url, is_active, sort_order}]` |
| `admin/branches/form` | `GET …/branches/new`, `…/branches/{bid}/edit` | `restaurant`, `branch` (satır\|null), `languages`, `translations`, `action`. Alanlar: `slug` (ops.), `default_language_id`, `phone`, `is_active`, `sort_order`, `translations[*][name\|address\|description]` |

Tek tuşluk POST işlemleri (gövdesiz form): `…/toggle`, `…/delete`, `allergens/{id}/icon/delete`, `branches/{bid}/regenerate-code` (**eski QR kodlarını geçersiz kılar — onay/uyarı gösterilmeli**).

Sıralama (JSON): `POST admin/languages/reorder`, `POST admin/allergens/reorder` → gövde `{"ids":[3,1,2]}`.

## Restoran paneli (`/panel`)

| View | Rota | Erişim | Değişkenler |
|---|---|---|---|
| `panel/auth/login` | `GET panel/login` | — | `error`. Form: `POST panel/login` → `email`, `password` |
| `panel/dashboard` | `GET panel` | owner + manager | `restaurantName`, `branches[{id, name, is_active, public_code}]` (manager: yalnızca atanmış şubeler) |
| `panel/profile` | `GET panel/profile` | owner + manager | `user{id, name, email, role}`. Formlar: `POST panel/profile` → `name`, `email`; `POST panel/profile/password` → `current_password`, `password`, `password_confirm` |
| `panel/restaurant` | `GET panel/restaurant` | owner | `restaurant{slug, currency_code}` (salt okunur), `logo_url`, `languages`, `translations`. Alanlar: `logo` (dosya), `translations[*][name\|description]`. Logo silme: `POST panel/restaurant/logo/delete` |
| `panel/branches/index` | `GET panel/branches` | owner + manager | `branches[{id, slug, name, public_code, menu_url, is_active, sort_order}]` |
| `panel/branches/form` | `GET panel/branches/new`, `…/{id}/edit` | owner | `branch` (satır\|null), `languages`, `translations`, `action`. Alanlar: admin şube formuyla aynı |
| `panel/users/index` | `GET panel/users` | owner | `users[{id, name, email, role, is_active, branch_ids[], editable}]` (`editable=false` → owner kayıtları; düzenleme/silme bağlantısı gösterilmez) |
| `panel/users/form` | `GET panel/users/new`, `…/{id}/edit` | owner | `user` (satır\|null), `branches[{id, name}]`, `assigned[]`, `action`. Alanlar: `name`, `email`, `password`, `branch_ids[]` (≥ 1), `is_active`. **Rol alanı yoktur**; panelden açılan kullanıcı her zaman manager'dır |
| `panel/categories/index` | `GET panel/branches/{b}/categories` | owner + manager | `branch{id, name}`, `categories[{id, name, image_url, is_active, sort_order, product_count}]` |
| `panel/categories/form` | `GET …/categories/new`, `…/categories/{c}/edit` | owner + manager | `branch{id, name}`, `category` (satır\|null), `image_url`, `languages`, `translations`, `action`. Alanlar: `image` (dosya ≤ 3 MB), `remove_image`, `is_active`, `sort_order` (boş = sona ekle), `translations[*][name\|description]` |
| `panel/products/index` | `GET panel/branches/{b}/products?category=&q=` | owner + manager | `branch{id, name}`, `categories[{id, name}]`, `filters{category, q}`, `products[{id, name, category_id, category_name, price, thumb_url, is_active, sort_order}]` |
| `panel/products/form` | `GET …/products/new`, `…/products/{p}/edit` | owner + manager | `branch{id, name}`, `product` (satır\|null), `image_url`, `categories[{id, name}]`, `allergens[{id, code, name, icon_url}]`, `selectedAllergens{allergen_id: level}`, `languages`, `translations`, `currency`, `action`. Alanlar: `category_id`, `price` (virgüllü giriş kabul edilir: `129,90`), `image` (dosya ≤ 5 MB), `is_active`, `sort_order`, `translations[*][name\|description]`, `allergens[{allergen_id}]=contains\|may_contain` (boş değer = seçilmemiş; gönderilmeyenler silinir) |

Tek tuşluk POST işlemleri: `branches/{b}/toggle|delete`, `users/{u}/toggle|delete`, `…/categories/{c}/toggle|delete`, `…/products/{p}/toggle|delete`, `…/products/{p}/image/delete`, `panel/logout`.

Sıralama (JSON): `POST panel/branches/{b}/categories/reorder`, `POST panel/branches/{b}/products/reorder` → `{"ids":[12,5,9]}`. ID'lerden biri bile bu şubeye ait değilse 422 döner ve hiçbir sıra değişmez.

Manager, owner'a özel sayfalara giderse veya atanmadığı bir şubeye erişirse yanıt **404**'tür (403 değil).

## Herkese açık menü

| View | Rota | Değişkenler |
|---|---|---|
| `menu/show` | `GET m/{public_code}?lang=xx` | `menu` — aşağıdaki JSON'ın `data` kısmıyla **aynı yapı** |
| `errors/not_found` | tüm 404'ler | `message` |

`GET /api/v1/menu/{public_code}?lang=xx` (CSRF yok, `Cache-Control: public, max-age=60`):

```json
{
  "status": "success",
  "data": {
    "restaurant": { "name": "", "description": null, "logo_url": null, "currency": "TRY" },
    "branch":     { "code": "k3f9a0c21b", "name": "", "address": null, "phone": null },
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
- Ürünler alerjenlere `id` ile referans verir; adlar üstteki `allergens` dizisindedir.
- `language.direction` `rtl` olabilir (`<html dir>`).
- Dil seçimi: geçerli `?lang` → `pixmenu_lang_{public_code}` çerezi (HTML menüde; 30 gün) → şubenin varsayılan dili. JSON API çerez okumaz/yazmaz.
- Ürünü olmayan, pasif veya silinmiş kategoriler/ürünler listede yer almaz.
