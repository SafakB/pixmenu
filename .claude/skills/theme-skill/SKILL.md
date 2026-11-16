---
name: theme-skill
description: PixMenu'de Velzon admin temasını (admin_template/) giydirme ve tema altyapısı rehberi. Bir view'ı temaya uygun hale getirme ("giydir", "temaya uyarla", "tasarımını yap", "Velzon'daki gibi yap"), admin/panel sayfalarında tablo, form, kart, rozet, modal, SweetAlert onayı, sürükle-bırak sıralama, dil sekmeli çeviri alanları, dashboard widget'ı yapma; layout, sidebar menüsü, topbar, login/kayıt sayfası, tanıtım (landing) sitesi, paket kartları, flash mesajları, customizer değişikliği; public/assets'e yeni kütüphane ekleme veya app.js yaması gibi her işte bu skill'i kullan. app/Views altındaki admin/panel/site view'larına görsel dokunan her istekte, kullanıcı "tema" demese bile tetiklenmelidir.
---

# PixMenu tema entegrasyonu (Velzon)

Bu skill iki şey bilir: **bizim yapımız** (layout'lar, partial'lar, helper'lar, yamalanmış asset'ler) ve **temada neyin nerede olduğu**. Amaç her view'ın aynı kurallarla, backend sözleşmesini bozmadan giydirilmesi.

## Adım 0 — Skill hâlâ güncel mi?

Her kullanımda önce çalıştır:

```bash
python -I .claude/skills/theme-skill/scripts/check_drift.py
```

- `OK` → devam.
- Fark raporu → skill'in anlattığı yapı ile koddaki yapı ayrışmış demektir. **Kullanıcıya bildir**: "theme-skill güncellenmeli: <fark listesi>". Değişikliğin ne olduğuna bak (`git diff`), ilgili referans dosyasını (çoğunlukla `references/project-structure.md` veya `assets-and-patches.md`) güncelle, sonra `--accept` ile parmak izini yenile. Kullanıcı "şimdi değil" derse işe devam et ama raporda bunu tekrar hatırlat.
- Aynı script bir PostToolUse hook'u olarak da çalışır: izlenen bir dosya düzenlenince uyarı otomatik gelir. Uyarı gelirse yukarıdakiyle aynı şekilde ele al. Skill güncellemesini kendin yaptıysan işin sonunda `--accept` çalıştırmayı unutma; aksi halde uyarı her düzenlemede tekrarlanır.

## Adım 1 — Bağlamı topla

1. `docs/VIEW_CONTRACT.md` → giydirilecek view'ın satırı: rota, değişkenler, form alanları. Bu sözleşme backend'le ortak dildir; değişken veya alan adını değiştirmek backend'i kırar.
2. Mevcut view dosyasını oku (stilsiz ama işlevsel; alan adları ve form action'ları doğru kabul edilir).
3. `references/project-structure.md` → layout'a ne verilir, hangi partial var, menü nerede.
4. `references/template-map.md` → bu ihtiyaç için temanın hangi sayfasına bakılacağı.
5. Tema sayfasını okurken dosyanın tamamını okuma (2–4 bin satır, çoğu bizde zaten olan layout/customizer). Sadece `<div class="page-content">` içindeki ilgili bloğu ve dosya sonundaki `<!-- JAVASCRIPT -->` sonrası script listesini al:
   ```bash
   grep -n 'class="page-content"\|<footer class="footer"\|<!-- JAVASCRIPT' admin_template/<sayfa>.html
   ```

## Adım 2 — Giydir

### Değişmez kurallar (neden önemli oldukları ile)

- **Sözleşme korunur.** `name` öznitelikleri (`translations[tr][name]`, `branch_ids[]`, `allergens[ID]`…), form action'ları ve HTTP metodu aynen kalır. Formlar düz POST; method spoofing yok. CSRF için `form_open()` / `form_open_multipart()` veya elle `<?= csrf_field() ?>`.
- **Her çıktı `esc()`**, öznitelikte `esc($x, 'attr')`. View'da model/servis çağrısı yok; ne lazımsa sözleşmedeki değişkenlerden gelir.
- **İstisna — `old()` zaten escape eder.** `esc(old('x'))` çift escape'tir (`&` → `&amp;` görünür). Doğrusu `esc((string) old('x', $varsayilan, false), 'attr')`. `form_open()` öznitelik dizisi de kendisi escape eder. Ayrıntı ve checkbox tuzağı: `references/patterns.md` §4.
- **Bool alanlar** (`is_active`, `is_default`, `editable`) controller'a göre `bool` ya da `"0"/"1"` gelebilir; karşılaştırmayı `(int) $x === 1` veya `! empty($x)` ile yap, `=== true` kullanma.
- **Asset'ler yalnızca `asset_url('...')`** ile (`public/assets` altı, otomatik `?v=`). Göreli `assets/...` yolu yazma: `/admin/restaurants/1/edit` gibi derin URL'lerde 404 olur.
- **CDN yok.** Temanın HTML'inde geçen `cdn.lordicon.com`, `cdn.jsdelivr.net` (toastify) vb. alınmaz; `<lord-icon>` yerine Remix/Boxicons ikonu kullan (`ri-*`, `bx-*`, `mdi-*` hepsi yüklü). Temanın `plugins.js`'i kullanılmaz (CDN + göreli yol yüklüyor).
- **Sayfaya özel CSS/JS** layout section'larına: `<?= $this->section('css') ?>…<?= $this->endSection() ?>`, `js` aynı şekilde. `js` section'ı çekirdek kütüphanelerden (bootstrap, simplebar, waves, feather) sonra, `app.js`'ten önce basılır.
- **Başlık / breadcrumb**: view'da `<h1>` yazma; en üstte değişken ver, layout `page_title` partial'ıyla basar:
  ```php
  <?php $this->setVar('title', 'Restoranlar') ?>
  <?php $this->setVar('breadcrumbs', [['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')], ['label' => 'Düzenle']]) ?>
  ```
  (`setVar` layout'a ulaşır çünkü `Config\View::$saveData = true`.)
- **Metinler Türkçe**, demo içerik (Velzon adı, avatar, sahte veri, envato linkleri) tamamen temizlenir.
- **Onay (karar verildi):** forma `data-confirm="…"` özniteliği vermek yeterli — `pix-confirm.js` layout'ta yüklü, sayfaya JS yazma (kalıp: `references/patterns.md` §7). Onay isteyenler:
  - tüm **silmeler** (`…/delete`, `…/image/delete`, `…/logo/delete`, `…/icon/delete`) — `danger`;
  - **QR yenileme** (`branches/{id}/regenerate-code`) — `warning`, metin "basılı eski QR kodları artık çalışmaz";
  - **yan etkisi büyük pasifleştirmeler** — `warning`: restoran (kullanıcı oturumları kapanır, menüler yayından kalkar), şube (menüsü 404 olur), kullanıcı (oturumu kapanır).
  Kategori, ürün, dil, alerjen toggle'ları ve tüm **aktifleştirmeler onaysızdır**.
- **Satır işlemleri ikon butondur (karar verildi):** `btn-icon btn-soft-*` + tooltip; "…" menüsü yalnızca istisna (kalıp ve renkler: `patterns.md` §2).
- **Breadcrumb:** kök "Gösterge Paneli" eklenmez; ilk öğe sayfanın sidebar menü öğesidir (url'li), son öğe url'siz. Şubeye bağlı sayfalar: `Şubeler (url) › {şube adı} (url'siz) › Kategoriler`. Şube düzenleme linki verme — manager için 404 döner.
- **Partial'lara tüm parametreleri açıkça geçir.** `saveData = true` yüzünden bir `view()` çağrısına verilen değişkenler sonraki view'lara sızar; partial içinde `$x ?? varsayılan`a güvenmek, önceki çağrıdan kalan değeri almak demek olabilir (sidebar logosu bu yüzden bir kez bozuldu).
- **Sunucu doğrulama hataları**: `session('errors')[alan]` → alana `is-invalid` + altına `<div class="invalid-feedback">`. Alan adları: `translations.tr.name` gibi noktalı (sözleşme). Flash zaten layout'ta; alan bazlı gösterim ek kolaylıktır.
- **Test metin çakışması**: bazı feature testleri sayfada belirli kelimelerin *görünmediğini* doğrular (ör. `assertDontSee('Gizli')`, başka tenant'ın kayıt adları). Layout/partial'a eklenen sabit metinler bu kelimeleri içermemeli. Testler kırılırsa önce UI metnini şüphelen.

### Ortak kalıplar

Liste tablosu, durum rozeti, satır aksiyonları, boş durum, form kartı, dil sekmeli çeviri alanları, görsel yükleme + önizleme, onaylı POST ve SortableJS sıralama için hazır kod: `references/patterns.md`. Çeviri alanları **ortak partial'da** (`partials/translations.php`) sekmeli hale getirilir; view'a ayrı sekme kodu yazma (§5). Yeni bir kalıp üretirsen ve tekrar kullanılacaksa oraya ekle.

### Yeni kütüphane gerekiyorsa

`admin_template/assets/libs/<lib>` → `public/assets/libs/<lib>` kopyala, sayfada `asset_url('libs/...')` ile `css`/`js` section'ında yükle, `references/assets-and-patches.md`'deki listeye ekle ve `check_drift.py --accept` çalıştır. Mirror'da olmayan kütüphaneler (choices.js, flatpickr, toastify) için kullanıcıya sor; CDN'e geçme.

## Adım 3 — Doğrula

0. Her değişen PHP dosyası için `php -l <dosya>` (sunucu/test çalıştırılamıyorsa en azından bu).
1. `vendor/bin/phpunit` (yaklaşık 2 dk) — tümü yeşil olmalı.
2. Görsel/JS kontrol: sunucu (`php spark serve --port 8080`, arka planda) + headless Chrome:
   ```bash
   node .claude/skills/theme-skill/scripts/screenshot.mjs <cookie-jar> <url> <out.png> [genişlik]
   ```
   Konsol hatalarını, ≥400 yanıtları ve exception'ları yazdırır; PNG'yi Read ile incele. Oturumlu sayfa için cookie jar'ı curl ile login olarak üret (CSRF token'ını login formundan al) — ayrıntı: `references/verification.md`.
   Bilinen tek gürültü: `hkgrotesk-regular.woff` 404 (font mirror'da yok).
3. Mobil genişlik (400px) ve koyu mod da en az bir kez bakılmaya değer.

## Referanslar

| Dosya | Ne zaman |
|---|---|
| `references/project-structure.md` | Layout/partial/menü/helper ayrıntısı gerektiğinde |
| `references/template-map.md` | Temada hangi sayfaya bakılacağına karar verirken |
| `references/patterns.md` | Tablo, form, onay, sıralama vb. kod kalıpları |
| `references/assets-and-patches.md` | public/assets içeriği, app.js yamaları, eksikler, lib ekleme |
| `references/verification.md` | Sunucu, login cookie'si, ekran görüntüsü adımları |
