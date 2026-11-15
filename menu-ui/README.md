# PixMenu menü ön ucu (`menu-ui/`)

Restoranların ziyaretçiye açık QR menüsü. Vue 3, Vite, TypeScript, vue-router ve Tailwind CSS v4 ile yazılmıştır.

Çıktı statik bir sitedir (`dist/`). Uygulama veriyi PixMenu API'sinden alır; tek istek yeterlidir:

```
GET {VITE_API_URL}/site?restaurant=<slug> | host=<hostname> &branch=<şube-slug> &lang=<kod>
```

API sözleşmesi `docs/VIEW_CONTRACT.md` dosyasında, "Menü sitesi API" bölümündedir. TypeScript karşılığı `src/api/types.ts` dosyasıdır.

## Adresler

| Durum | Adres |
|---|---|
| Restoranın özel alan adı varsa | `menu.sirketadi.com` → şube listesi (tek şubede doğrudan menü), `menu.sirketadi.com/subeadi` |
| Özel alan adı yoksa | `sirketadi.pixmenu.com`, `sirketadi.pixmenu.com/subeadi` |
| Eski QR bağlantıları | `pixmenu.com/m/{kod}` → kanonik adrese 302 |

Restoran hibrit yöntemle bulunur:

- `.env` içinde `VITE_RESTAURANT` doluysa o slug kullanılır. Bu, restorana özel kurulum ve geliştirme içindir.
- Boşsa restoran tarayıcıdaki host'tan çözülür. Böylece tek build tüm restoranlara hizmet eder.

Host modunda restoranın özel alan adı varsa, alt alan adından gelen ziyaretçi otomatik olarak özel alan adına yönlenir (`canonical_url`).

## Kurulum

Node 22 veya üstü gerekir.

```bash
cd menu-ui
cp .env.example .env      # VITE_API_URL ve isteğe bağlı VITE_RESTAURANT
npm install
```

| Komut | İş |
|---|---|
| `npm run dev` | Geliştirme sunucusu, http://localhost:5173 |
| `npm run build` | Önce tip kontrolü, sonra `dist/` üretimi |
| `npm run type-check` | Yalnızca `vue-tsc` |
| `npm test` | Vitest birim testleri |
| `npm run preview` | `dist/` çıktısını yerelde sunar |

## Yerel geliştirme

1. CI4 tarafında, proje kökündeki `.env` dosyasına şunları ekleyin. Alt alan adı ve kanonik adresler için gereklidir:

   ```ini
   pixmenu.menuRootDomain = localhost
   pixmenu.menuScheme     = http
   pixmenu.menuPort       = 5173
   ```

   Ardından `php spark serve` (http://localhost:8080) komutunu çalıştırın.

2. `menu-ui/.env` dosyası:

   ```ini
   VITE_API_URL=http://localhost:8080/api/v1
   VITE_RESTAURANT=demo-lezzet
   ```

   Ardından `npm run dev` komutunu çalıştırın.

3. Tarayıcıda açın:
   - `http://localhost:5173/`: sabit restoran (`VITE_RESTAURANT`).
   - `http://demo-lezzet.localhost:5173/kadikoey`: host modu. Bunun için `VITE_RESTAURANT` boş olmalı. Chrome ve Firefox `*.localhost` adreslerini kendiliğinden 127.0.0.1'e çözer.
   - `?sablon=kategori`: şubenin şablonunu değiştirmeden başka bir şablonu önizler. Yalnızca geliştirmede çalışır; build'e girmez.

## Şablonlar

Admin, şube başına bir şablon atar: **Admin → Menü şablonları**, şube formundaki "Şablon" alanı. API bunu `menu.branch.template` olarak verir. Ziyaretçi yalnızca kendi şubesinin şablon chunk'ını indirir.

| Kod | Düzen |
|---|---|
| `standart` | Tek sayfa, basılı menü kartı. Yapışkan kategori sekmeleri kaydırdıkça aktif kategoriyi izler. Ürün adı ile fiyat noktalı çizgiyle birleşir. |
| `kategori` | Önce fotoğraflı kategori kartları; dokununca o kategorinin ürünleri açılır. Açık kategori adreste `?c={id}` olarak durur, böylece telefonun geri tuşu kartlara döner. |

Ön uçta karşılığı olmayan bir kod `standart` ile gösterilir; konsola bir uyarı düşer.

**Yeni şablon eklemek için:**

1. `src/templates/<kod>/` altında `<Kod>Template.vue` dosyasını yazın. Bileşen `site: Site` ve `menu: Menu` prop'larını alır. Ortak parçalar `src/components/` altındadır: `AppHeader`, `SearchBox`, `ProductSheet`, `AllergenList`, `MenuFooter`.
2. Şablonu `src/templates/registry.ts` dosyasına ekleyin.
3. Admin → Menü şablonları ekranında aynı kodla tanımlayın ve şubeye atayın.

Şablonun vurgu rengi kendi kök elemanındaki `--pm-accent` değişkenidir. Ortak bileşenler bu rengi kullanır.

## Dil

Dil şu sırayla seçilir:

1. Adresteki `?lang=xx`
2. Ziyaretçinin bu restoran için kayıtlı tercihi (`localStorage`)
3. Şubenin varsayılan dili. Bu seçimi sunucu yapar.

Tarayıcı dili bilinçli olarak kullanılmaz (spec §8.4). `<html lang/dir>` her yanıtta güncellenir, bu yüzden RTL diller desteklenir. Arayüz metinleri `src/core/i18n.ts` dosyasındadır; sözlüğü olmayan dil İngilizceye düşer.

## Dağıtım

### A) Tek build, tüm restoranlar (önerilen)

`VITE_RESTAURANT` boş bırakılır ve `npm run build` çalıştırılır. Çıkan `dist/` dizini, menü host'larının belge köküdür.

- **DNS:** `*.pixmenu.com` için wildcard kayıt gerekir. Özel alan adlarında restoran CNAME kaydını sunucuya yönlendirir.
- **SSL:**
  - Alt alan adları için wildcard sertifika (DNS-01 ile Let's Encrypt veya Cloudflare).
  - Özel alan adları için alan adı başına sertifika (Let's Encrypt, Caddy on-demand TLS veya Cloudflare for SaaS).
- **CI4 `.env`:** `pixmenu.menuRootDomain = pixmenu.com`
- **Apache:** Sıra önemlidir. Apache, host'u vhost'larla tanımlandıkları sırayla eşleştirir. Hiçbirine uymayan host ilk vhost'a düşer; özel alan adlarının yolu da budur.

  ```apache
  # 1) Varsayılan: tanınmayan host'lar = restoranların özel alan adları → menü
  <VirtualHost *:443>
      ServerName menu-default.invalid
      DocumentRoot /var/www/pixmenu/menu-ui/dist
  </VirtualHost>

  # 2) Uygulama: admin, panel, API (wildcard'dan ÖNCE)
  <VirtualHost *:443>
      ServerName pixmenu.com
      ServerAlias www.pixmenu.com
      DocumentRoot /var/www/pixmenu/public
  </VirtualHost>

  # 3) Alt alan adı menüleri
  <VirtualHost *:443>
      ServerName menu.pixmenu.com
      ServerAlias *.pixmenu.com
      DocumentRoot /var/www/pixmenu/menu-ui/dist
  </VirtualHost>
  ```

  Bu bloklara sertifika satırları da eklenmelidir. `dist/.htaccess`, SPA yönlendirmesini (bilinmeyen yol → `index.html`) ve önbellek başlıklarını içerir; `AllowOverride All` veya eşdeğeri gerekir.

### B) Restorana özel kurulum

`.env` içinde `VITE_RESTAURANT=sirketadi` tanımlanır ve `npm run build` çalıştırılır. Çıkan `dist/` o restoranın klasörüne veya hostuna yüklenir. Değerler build sırasında koda gömülür; bu yüzden her restoran ayrı derlenir.

## Notlar

- API "basit" CORS isteğiyle çağrılır: özel başlık ve çerez yoktur. CI4, `api/*` için `Access-Control-Allow-Origin: *` döner (`Config\Cors::$menu`).
- Önceki ziyaretten bilinen şablonun chunk'ı API isteğiyle paralel indirilir (`localStorage` → `pixmenu_tpl_*`).
- Görseller listelerde küçük boyutla (`thumb_url`) ve lazy yüklenir; büyük görsel yalnızca ürün detayında açılır.
- Restoran slug'ı alt alan adı olduğu için en fazla 63 karakterdir ve `www`, `admin`, `api` gibi ayrılmış adlardan biri olamaz. CI4 bunu doğrular.
