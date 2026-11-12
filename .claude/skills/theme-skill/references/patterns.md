# Kod kalıpları

Velzon sınıflarıyla, sözleşmeyi bozmayan hazır parçalar. Uyarlarken alan adlarını ve action'ları **sözleşmeden** al, buradaki örnek adlardan değil.

## İçindekiler
1. Sayfa iskeleti (title + breadcrumb)
2. Liste kartı + tablo + satır aksiyonları
3. Durum rozeti / boş durum
4. Form kartı + alanlar + sunucu hataları
5. Dil sekmeli çeviri alanları
6. Görsel yükleme + önizleme + kaldır
7. Onaylı POST (pix-confirm.js)
8. Sürükle-bırak sıralama (SortableJS + CSRF)

---

## 1. Sayfa iskeleti

```php
<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'Restoranlar') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Restoranlar']]) ?>
<?php /* Düzenleme sayfası: [['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')], ['label' => $restaurant['name']]]
         Şubeye bağlı: [['label' => 'Şubeler', 'url' => site_url('panel/branches')], ['label' => $branch['name']], ['label' => 'Kategoriler']] */ ?>
<?= $this->section('content') ?>
  ...
<?= $this->endSection() ?>
```

## 2. Liste kartı + tablo

```php
<div class="card">
    <div class="card-header d-flex align-items-center">
        <h5 class="card-title mb-0 flex-grow-1">Restoranlar</h5>
        <a href="<?= site_url('admin/restaurants/new') ?>" class="btn btn-success btn-sm"><i class="ri-add-line align-bottom me-1"></i> Yeni restoran</a>
    </div>
    <div class="card-body">
        <div class="table-responsive table-card">
            <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light">
                    <tr><th>Ad</th><th>Slug</th><th>Durum</th><th class="text-end">İşlemler</th></tr>
                </thead>
                <tbody>
                <?php foreach ($restaurants as $r): ?>
                    <tr>
                        <td><a href="<?= site_url('admin/restaurants/' . $r['id']) ?>" class="fw-medium"><?= esc($r['name']) ?></a></td>
                        <td class="text-muted"><?= esc($r['slug']) ?></td>
                        <td><!-- 3. rozet --></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="<?= site_url('admin/restaurants/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                   data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                <?= form_open('admin/restaurants/' . $r['id'] . '/toggle', ['class' => 'd-inline'] + ((int) $r['is_active'] === 1 ? [
                                    'data-confirm'         => $r['name'] . ' pasifleştirilecek. Kullanıcılarının oturumu kapanır, menüleri yayından kalkar.',
                                    'data-confirm-button'  => 'Evet, pasifleştir',
                                    'data-confirm-variant' => 'warning',
                                ] : [])) ?>
                                    <?php $label = (int) $r['is_active'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?>
                                    <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= (int) $r['is_active'] === 1 ? 'warning' : 'success' ?>"
                                            data-bs-toggle="tooltip" title="<?= $label ?>" aria-label="<?= $label ?>"><i class="ri-shut-down-line"></i></button>
                                <?= form_close() ?>
                                <?= form_open('admin/restaurants/' . $r['id'] . '/delete', ['class' => 'd-inline', 'data-confirm' => $r['name'] . ' silinecek. Bu işlem geri alınamaz.', 'data-confirm-button' => 'Evet, sil']) ?>
                                    <button type="submit" class="btn btn-sm btn-icon btn-soft-danger"
                                            data-bs-toggle="tooltip" title="Sil" aria-label="Sil"><i class="ri-delete-bin-line"></i></button>
                                <?= form_close() ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
```
**Proje standardı — satır işlemleri ikon butondur:** `btn btn-sm btn-icon btn-soft-{renk}` + `data-bs-toggle="tooltip"` + `title` + `aria-label`. Renkler: Düzenle `primary`, Detay `info` (`ri-eye-line`), Pasifleştir `warning` / Aktifleştir `success` (`ri-shut-down-line`), Sil `danger` (`ri-delete-bin-line`), alt sayfa kısayolları `secondary`. Tooltip'leri `app.js` kendisi başlatır. Onay kuralları için SKILL.md'deki "Onay" maddesine bak.

İstisna olarak "…" dropdown menüsü kullanılırsa (ör. 4'ten fazla işlem) `.table-responsive` (overflow) son satırlardaki menüyü kırpar: tetikleyici butona `data-bs-popper-config='{"strategy":"fixed"}'` ekle.

`form_open` öznitelik dizisindeki değerleri `stringify_attributes()` ile kendisi escape eder — `esc()` ile sarma, çift escape olur.

## 3. Rozet / boş durum

```php
<?php if ($r['is_active']): ?>
    <span class="badge bg-success-subtle text-success">Aktif</span>
<?php else: ?>
    <span class="badge bg-danger-subtle text-danger">Pasif</span>
<?php endif ?>
```

```php
<?php if ($restaurants === []): ?>
    <div class="text-center py-5">
        <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-store-2-line"></i></div></div>
        <h5 class="mb-1">Henüz restoran yok</h5>
        <p class="text-muted mb-0">İlk restoranı eklemek için "Yeni restoran"a tıklayın.</p>
    </div>
<?php endif ?>
```

## 4. Form kartı + sunucu hataları

```php
<?php
$errors   = session('errors') ?? [];
$hasOld   = old('translations', null, false) !== null;   // bu form bir hata sonrası mı geri geldi? (translations her gönderimde gelir)
$isActive = $hasOld ? old('is_active', null, false) === '1' : (int) ($row['is_active'] ?? 1) === 1;
?>
<?= form_open_multipart($action) ?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Genel bilgiler</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="price">Fiyat</label>
                    <input type="text" inputmode="decimal" class="form-control<?= isset($errors['price']) ? ' is-invalid' : '' ?>" id="price" name="price"
                           value="<?= esc((string) old('price', $product['price'] ?? '', false), 'attr') ?>">
                    <?php if (isset($errors['price'])): ?><div class="invalid-feedback"><?= esc($errors['price']) ?></div><?php endif ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Yayın</h5></div>
            <div class="card-body">
                <div class="form-check form-switch form-switch-success mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
                <button type="submit" class="btn btn-success w-100">Kaydet</button>
            </div>
        </div>
    </div>
</div>
<?= form_close() ?>
```
**`old()` escape tuzağı:** CI4'te `old($key, $default, $escape = 'html')` değeri **zaten escape ederek** döndürür. `esc(old(...))` çift escape eder (kullanıcı `Et & Tavuk` yazar, hata sonrası alanda `Et &amp; Tavuk` görür). Doğrusu: `esc((string) old('alan', $varsayilan, false), 'attr')`. Varsayılan değer de escape'li döner, bu yüzden `false` her zaman verilmeli.

**Checkbox + `old()`:** işaretsiz checkbox hiç gönderilmez, dolayısıyla `old('is_active', $row['is_active'])` kullanıcı işareti kaldırdıysa hata sonrası yine işaretli gösterir. Önce "eski girdi var mı" bak (yukarıdaki `$hasOld`; çeviri alanı olmayan formlarda her zaman gönderilen başka bir alanı işaret olarak kullan).

**Hata gösterimi:** layout'taki `partials/flash` hataları üstte liste olarak da basar; alan altı `invalid-feedback` ek kolaylıktır. İkisinin birlikte çıkması şimdilik kabul (karar değişirse burayı güncelle).

## 5. Dil sekmeli çeviri alanları

**Uygulandı** — `partials/translations.php` aşağıdaki kalıbın çalışan hâlidir; yeni formda sadece çağır, kendisi kart basar. Sözleşmesi: `languages`, `translations`, `fields` (`alan => text|textarea`). Ad: `translations[{code}][{field}]`, hata anahtarı `translations.{code}.{field}`. Sekmeler **ortak partial'da** (`partials/translations.php`) uygulanır ki 7 form birden aynı görünsün. `card-header-tabs` bir `card-header` ister, bu yüzden partial kendi kartını basar; view onu kart içine sarmaz.

Açık sekme seçimi: hatalı ilk dil → yoksa `is_default` dil → yoksa ilk dil. **`$i === 0` varsayma**: `languages` dizisinde varsayılan dil başta olmak zorunda değil. Panel id'leri `lang-{code}` (ör. `lang-tr`; `tr-tr` gibi kafa karıştırıcı id'lerden kaçın). Alan etiketleri Türkçe: `name` → Ad, `description` → Açıklama, `address` → Adres.

```php
<?php
$codes      = array_column($languages, 'code');
$errorCodes = array_values(array_filter($codes, static fn ($c) => preg_grep('/^translations\.' . preg_quote($c, '/') . '\./', array_keys($errors))));
$default    = array_values(array_filter($languages, static fn ($l) => $l['is_default']))[0]['code'] ?? ($codes[0] ?? null);
$activeCode = $errorCodes[0] ?? $default;
?>
<ul class="nav nav-tabs-custom card-header-tabs border-bottom-0" role="tablist">
    <?php foreach ($languages as $i => $lang): ?>
        <li class="nav-item">
            <a class="nav-link<?= $lang['code'] === $activeCode ? ' active' : '' ?>" data-bs-toggle="tab" href="#lang-<?= esc($lang['code'], 'attr') ?>" role="tab">
                <?= esc($lang['native_name']) ?><?= $lang['is_default'] ? ' <span class="text-danger">*</span>' : '' ?>
            </a>
        </li>
    <?php endforeach ?>
</ul>
<div class="tab-content">
    <?php foreach ($languages as $i => $lang): ?>
        <div class="tab-pane<?= $lang['code'] === $activeCode ? ' active' : '' ?>" id="lang-<?= esc($lang['code'], 'attr') ?>" role="tabpanel">
            ... alanlar: name="translations[<code>][<field>]", dir="<direction>" INPUT/TEXTAREA'da (panelde değil — RTL dilde Türkçe etiketler sağa kaymasın),
                hata: $errors["translations.{$code}.{$field}"] ...
        </div>
    <?php endforeach ?>
</div>
```
Hatalı dilin sekme başlığına `<i class="ri-error-warning-fill text-danger"></i>` ekle. Varsayılan dil `name` alanı `required`.

**Gizli sekmedeki `required` tuzağı:** zorunlu alan kapalı bir sekmede boş kalırsa tarayıcı formu göndermez ve hatayı gösteremez (kullanıcı hiçbir şey görmez). Formun `invalid` olayında ilgili sekmeyi aç:
```js
document.addEventListener('invalid', function (e) {
    var pane = e.target.closest('.tab-pane');
    if (pane && !pane.classList.contains('active')) {
        bootstrap.Tab.getOrCreateInstance(document.querySelector('[href="#' + pane.id + '"]')).show();
    }
}, true);
```
**Partial'dan JS:** `view()` ile basılan bir partial layout'un `js` section'ına yazamaz (section'lar yalnızca child view'da). Ya inline `<script>` kullan (partial'daki script bootstrap'tan önce çalışır; yukarıdaki gibi yalnızca `document`'a dinleyici bağlayıp `bootstrap`'a olay anında erişen kod güvenlidir), ya da ortak bir JS dosyasına taşıyıp `scripts.php`'ye ekle (manifest + `project-structure.md` güncellenir).

## 6. Görsel yükleme + önizleme

```php
<div class="mb-3">
    <label class="form-label" for="image">Görsel <span class="text-muted">(jpg, png, webp — en fazla 3 MB)</span></label>
    <input class="form-control" type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp" data-preview="#image-preview">
</div>
<img id="image-preview" src="<?= esc($image_url ?? '', 'attr') ?>" alt="" class="img-thumbnail mb-2<?= $image_url === null ? ' d-none' : '' ?>" style="max-height:160px">
<?php if ($image_url !== null): ?>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
        <label class="form-check-label" for="remove_image">Görseli kaldır</label>
    </div>
<?php endif ?>
```
```js
document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
        var img = document.querySelector(input.dataset.preview);
        if (!img || !input.files[0]) return;
        img.src = URL.createObjectURL(input.files[0]);
        img.classList.remove('d-none');
    });
});
```
Ayrı silme rotası olan yerlerde (`…/image/delete`, `allergens/{id}/icon/delete`, `restaurant/logo/delete`) checkbox yerine ayrı onaylı POST formu (kalıp 7) kullanılır — **ana formun içine form yerleştirme** (iç içe form HTML'de geçersiz); ana formun dışına koy.

## 7. Onaylı POST (`pix-confirm.js`)

SweetAlert2 ve `public/assets/js/pix-confirm.js` her admin/panel sayfasında yüklü (layout). Sayfaya JS yazma; onay istenen POST formuna öznitelik ver:

```php
<?= form_open('panel/branches/' . $b['id'] . '/regenerate-code', [
    'class'                => 'd-inline',
    'data-confirm'         => 'Yeni QR kodu oluşturulacak. Basılı eski QR kodları artık çalışmaz.',
    'data-confirm-title'   => 'QR kodu yenilensin mi?',   // ops., varsayılan "Emin misiniz?"
    'data-confirm-button'  => 'Evet, yenile',             // ops., varsayılan "Evet, devam et"
    'data-confirm-variant' => 'warning',                  // ops., danger (varsayılan) | warning | primary
]) ?>
```

Davranış: submit yakalanır → onay penceresi (Vazgeç odaklı) → onaylanırsa `requestSubmit()` ile normal POST. SweetAlert yüklenemezse `window.confirm()` yedeği. Kullanıcı verisini (`$r['name']`) doğrudan ver; `form_open` escape eder.

## 8. Sürükle-bırak sıralama

Sözleşme: `POST …/reorder`, gövde `{"ids":[...]}` (JSON), başlıklar `X-Requested-With: XMLHttpRequest`, `X-CSRF-TOKEN`. Yanıttaki `X-CSRF-TOKEN` sonraki istekte kullanılır. 422'de sıralama hiç değişmez → sayfayı yenile veya eski sırayı geri koy.

```php
<?= $this->section('js') ?>
<script src="<?= asset_url('libs/sortablejs/Sortable.min.js') ?>"></script>
<script>
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var list = document.getElementById('sortable-rows');           // <tbody id="sortable-rows"> içinde <tr data-id="…">
    if (!list) return;
    Sortable.create(list, {
        handle: '.drag-handle', animation: 150,                    // satırda <i class="ri-drag-move-2-line drag-handle"></i>
        onEnd: function () {
            var ids = Array.from(list.querySelectorAll('[data-id]')).map(function (el) { return +el.dataset.id; });
            fetch(<?= json_encode(site_url('panel/branches/' . $branch['id'] . '/categories/reorder')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta.content },
                body: JSON.stringify({ ids: ids })
            }).then(function (res) {
                var t = res.headers.get('X-CSRF-TOKEN'); if (t) meta.content = t;
                if (!res.ok) location.reload();
            });
        }
    });
})();
</script>
<?= $this->endSection() ?>
```
