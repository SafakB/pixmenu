# Temada nereye bakılır (`admin_template/`)

Her sayfa ~2–4 bin satır; ilgili kısım `<div class="page-content">` ile `<footer class="footer">` arasıdır, sayfa özel script'ler `<!-- JAVASCRIPT -->` sonrasında. Satır numaralarını bulmak için:

```bash
grep -n 'class="page-content"\|<footer class="footer"\|<!-- JAVASCRIPT' admin_template/<sayfa>.html
```

## İhtiyaç → sayfa

| İhtiyaç | Bakılacak sayfa(lar) | Sayfa özel lib / init |
|---|---|---|
| Basit liste tablosu, satır aksiyonları | `tables-basic.html`, `apps-ecommerce-orders.html`, `apps-crm-contacts.html` | — |
| Arama + sayfalama (istemci tarafı) | `tables-listjs.html` | `libs/list.js`, `libs/list.pagination.js`, `js/pages/listjs.init.js` |
| Grid tablo | `tables-gridjs.html` | `libs/gridjs` |
| Ürün listesi (görsel + fiyat + filtre) | `apps-ecommerce-products.html` | gridjs, nouislider (filtre; bizde GET formu yeterli) |
| Form elemanları | `forms-elements.html`, `forms-layouts.html`, `forms-checkboxs-radios.html`, `forms-select.html` | — (`data-choices` choices.js ister, lib mirror'da yok) |
| Switch (aktif/pasif) | `forms-checkboxs-radios.html` → `form-switch` | — |
| Doğrulama görünümü | `forms-validation.html` | init dosyası istemci doğrulaması; biz sunucu hatasını `is-invalid` ile basarız |
| Görsel/dosya yükleme | `forms-file-uploads.html`, `apps-ecommerce-add-product.html` | dropzone / filepond — bizde düz `<input type="file">` + önizleme (form POST bozulmasın) |
| Ürün ekleme formu düzeni (kartlar, sağ kolon) | `apps-ecommerce-add-product.html` | ckeditor (kullanılmıyor) |
| Sekmeler (dil sekmeleri) | `ui-tabs.html` (`nav-tabs-custom`, `nav-pills`) | — |
| Modal | `ui-modals.html` | — |
| Onay / uyarı diyaloğu | `advance-ui-sweetalerts.html` | `libs/sweetalert2/sweetalert2.min.css|js` |
| Sürükle-bırak sıralama | `advance-ui-nestable.html` | `libs/sortablejs/Sortable.min.js` |
| Kart, rozet, ikon, boş durum | `ui-cards.html`, `ui-badges.html`, `ui-general.html`, `widgets.html` | — |
| Alert / bildirim | `ui-alerts.html`, `ui-notifications.html` | (toastify mirror'da yok) |
| Dashboard sayaç kartları | `dashboard-*.html` üst sıra widget'ları, `widgets.html` | grafik gerekirse `libs/apexcharts` |
| Profil + şifre değiştirme | `pages-profile-settings.html` | — |
| Avatar/görsel yer tutucu | `ui-images.html` (`avatar-*`, `bg-*-subtle`) | — |
| 404 / hata | `auth-404-basic.html`, `auth-404-alt.html` | — |
| Görsel galerisi (alerjen ikonları vb.) | `pages-gallery.html`, `ui-images.html` | — |
| Tanıtım sayfası (navbar, hero, özellikler, süreç, SSS, CTA, footer) | `landing.html` → `site/home` | kendi `js/pages/landing.init.js` (Swiper yok) |
| Paket/fiyat kartları | `pages-pricing.html` → `partials/billing/plan_card` | `js/pix-plan-cycle.js` |
| Çok adımlı form (sihirbaz) | `forms-wizard.html` (`step-arrow-nav`) → `site/signup` | kendi `js/pages/signup.init.js` |
| Kayıt / sosyal giriş düğmesi | `auth-signup-basic.html` (`signin-other-title`) | — |
| Ödeme / kredi kartı | `apps-ecommerce-checkout.html` (sipariş özeti), `widgets.html` "Credit Card - card js plugin" → `panel/subscription/checkout` | `libs/card/card.js` + `js/pages/checkout.init.js` |

## İkon setleri (hepsi `icons.min.css` ile yüklü)

Remix (`ri-*-line|fill`) birincil; Boxicons (`bx bx-*`), Material Design (`mdi mdi-*`), Line Awesome (`la la-*`). Ön izleme: `icons-remix.html`. Feather (`data-feather`) da yüklü. Lordicon (`<lord-icon>`) **kullanılmaz** (CDN).

## Tema sınıfları — sık kullanılanlar

- Kart: `card > card-header (card-title mb-0) + card-body`; başlıkta aksiyon: `d-flex align-items-center` + `flex-grow-1`.
- Soft butonlar: `btn btn-soft-primary|danger|success btn-sm`, ikon buton `btn-icon`.
- Rozet: `badge bg-success-subtle text-success` / `bg-danger-subtle text-danger`.
- Tablo: `table-responsive table-card` içinde `table align-middle table-nowrap mb-0`, başlık `table-light`.
- Avatar: `avatar-xs|sm|md`, `rounded`, `avatar-title bg-light`.

## Tema sayfalarında görmezden gelinecekler

`<lord-icon>`, `document.write(...)`, `data-key="t-…"` (i18n), demo görseller (`assets/images/products|users|…`), `javascript:void(0)` linkleri, `plugins.js`, YouTube/Google Maps embed'leri.
