# public/assets — içerik, yamalar, eksikler

Ayrıca bkz. `public/assets/README.md` (proje içindeki kısa not; bu dosyayla tutarlı tutulmalı).

## Kopyalananlar (`admin_template/assets` → `public/assets`)

| Klasör | Kapsam |
|---|---|
| `css/` | tamamı: bootstrap, icons, app, custom (.min.css) |
| `fonts/` | tamamı (ikon fontları) |
| `libs/` | tamamı (44 kütüphane: sweetalert2, sortablejs, list.js, gridjs, apexcharts, dropzone, filepond, swiper, particles.js …) |
| `js/` | `app.js` (**yamalı**), `layout.js`, `pages/password-addon.init.js`, `pages/particles.app.js` · **bizim**: `pix-confirm.js` |
| `images/` | `favicon.ico`, `auth-one-bg.jpg`, `users/user-dummy-img.jpg`, `sidebar/img-1..4.jpg`, `demos/*.png` (customizer önizlemeleri) |

Kopyalanmayanlar: `js/plugins.js` (CDN toastify + göreli choices/flatpickr), `js/pages/plugins/lord-icon-2.1.0.js` (CDN), diğer `js/pages/*.init.js` (demo verisine bağlı; gerekirse kopyalanıp uyarlanır), demo görseller.

## `js/app.js` yamaları

Tema güncellenirse yeniden uygulanmalı; `check_drift.py` bunların varlığını denetler.

1. Göreli `"assets/...` → `window.PIX_ASSET_BASE+"...` (+ bildirim şablonundaki tek tırnaklı `bell.svg`). `PIX_ASSET_BASE` `partials/layout/head.php`'de `base_url('assets').'/'`.
2. `function o(){document.getElementById("header-lang-img")&&n(…)` — dil yükleyicisi yalnızca dil seçici varsa çalışır (aksi halde her sayfa `assets/lang/en.json` istiyordu).
3. `document.getElementById("removeNotificationModal")&&…addEventListener("show.bs.modal"` — bildirim modalı yoksa null hatası tüm init'i durduruyordu (sidebar dahil).
4. İlk ziyarette customizer'ı otomatik açan `L.click()` kaldırıldı.
5. İki sütunlu yerleşimde `logo-sm.png` yerine `<i class="ri-restaurant-2-line fs-22"></i>`.

Yama sonrası sözdizimi kontrolü: `node -e "new Function(require('fs').readFileSync('public/assets/js/app.js','utf8'))"`.

`app.js` tooltip (`data-bs-toggle="tooltip"`) ve popover'ları sayfa yüklenince kendisi başlatır; ek init yazma (sonradan DOM'a eklenen öğeler hariç).

`app.js` diğer bilinen davranışlar: `[data-choices]` → `new Choices` (lib yok, kullanma); `[data-provider=flatpickr]` → flatpickr (lib yok); `[data-toast]` → Toastify (lib yok). Bu öznitelikleri tema HTML'inden kopyalarken sil.

## Eksikler (mirror'da yok)

- Kütüphaneler: `choices.js`, `flatpickr`, `toastify-js`. Gerekirse kullanıcıya sor (npm'den indirip `public/assets/libs`'e koymak gibi); CDN'e geçme.
- Font: `fonts/hkgrotesk-*.woff` (varsayılan temanın birincil fontu) → her sayfada 1 adet 404, tarayıcı sans-serif'e düşer. Orijinal Velzon paketinden eklenebilir.
- Customizer önizleme görselleri: corporate, galaxy, classic, vintage (bu temalar customizer'dan çıkarıldı; galaxy ayrıca `images/galaxy/` ister).
- Logo dosyaları yok → `partials/layout/logo.php` metin logo kullanıyor.

## Yeni kütüphane ekleme

1. `cp -r admin_template/assets/libs/<lib> public/assets/libs/` (zaten tamamı kopyalı; yeni sürüm/ek lib için).
2. Sayfada:
   ```php
   <?= $this->section('css') ?><link href="<?= asset_url('libs/x/x.min.css') ?>" rel="stylesheet"><?= $this->endSection() ?>
   <?= $this->section('js') ?><script src="<?= asset_url('libs/x/x.min.js') ?>"></script><?= $this->endSection() ?>
   ```
3. Bu dosyayı güncelle, `python -I .claude/skills/theme-skill/scripts/check_drift.py --accept`.
