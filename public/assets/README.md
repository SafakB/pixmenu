# public/assets — Velzon tema dosyaları

Kaynak: `admin_template/assets/` (seçici kopya: css, fonts, libs tamamı; js'den app/layout + login sayfa script'leri; images'tan favicon, auth-one-bg, varsayılan avatar, customizer önizlemeleri).

## `js/app.js` üzerinde yapılan yamalar

Tema güncellenirse bu değişiklikler yeniden uygulanmalı:

1. Göreli `"assets/..."` yolları → `window.PIX_ASSET_BASE+"..."` (alt URL'lerde 404 olmasın; değişken `partials/layout/head.php`'de tanımlı).
2. Dil (i18n) yükleyicisi yalnızca `#header-lang-img` varsa çalışır (aksi halde her sayfada `assets/lang/en.json` isteği atıyordu).
3. `#removeNotificationModal` yoksa bildirim modal bağlaması atlanır (null hatası tüm init'i durduruyordu).
4. İlk ziyarette customizer panelinin otomatik açılması kaldırıldı.
5. İki sütunlu yerleşimdeki `logo-sm.png` yerine metin logo ikonu.

## Bizim dosyalarımız

- `js/pix-confirm.js` — `form[data-confirm]` gönderimini SweetAlert2 onayına bağlar (layout'ta yüklü; öznitelikler dosyanın başında).

## Bilinen eksik

`css/app.min.css` varsayılan temada `hkgrotesk` fontunu kullanıyor, ancak `fonts/hkgrotesk-*.woff` dosyaları şablon kopyasında yok (404, tarayıcı sans-serif'e düşer). Orijinal Velzon paketinden `fonts/` altına eklenebilir.
