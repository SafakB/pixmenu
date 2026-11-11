# Doğrulama adımları

## 1. Testler

```bash
vendor/bin/phpunit            # ~2 dk, 175+ test
```
Feature testleri HTML içinde metin arar (`assertSee` / `assertDontSee`). Giydirme sonrası kırılan test genellikle eklenen sabit UI metninden kaynaklanır (ör. customizer'daki "Gizli" etiketi tenant izolasyon testini kırmıştı).

## 2. Sunucu

`.env`: `app.baseURL = 'http://localhost:8080/'`. Arka planda başlat (Bash `run_in_background: true`):

```bash
php spark serve --port 8080
```
İş bitince arka plan görevini durdur.

## 3. Oturum cookie'si (curl)

Login formu CSRF ister; token'ı formdan al:

```bash
J=<scratchpad>/jar.txt; rm -f $J
T=$(curl -s -c $J -b $J http://localhost:8080/panel/login | grep -o 'name="csrf_[a-z_]*" value="[^"]*"' | head -1)
N=$(echo $T | sed 's/name="\([^"]*\)".*/\1/'); V=$(echo $T | sed 's/.*value="\([^"]*\)"/\1/')
curl -s -c $J -b $J -o /dev/null -w "%{http_code} %{redirect_url}\n" \
  --data-urlencode "$N=$V" --data-urlencode "email=owner@demo-lezzet.local" --data-urlencode "password=Demo12345678" \
  http://localhost:8080/panel/login          # 303 -> /panel beklenir
```

Hesaplar:
- Panel (DemoSeeder): `owner@demo-lezzet.local`, `manager@demo-lezzet.local`, `owner@demo-anadolu.local`… şifre `DemoSeeder::PASSWORD` (`Demo12345678`).
- Admin: `.env` → `pixmenu.adminEmail` / `pixmenu.adminPassword`. Şifreyi ekrana/sohbete yazdırma; değişkene okuyup kullan.

Login denemeleri dakikada sınırlıdır (429) — çok sayıda hatalı deneme yapma.

## 4. Ekran görüntüsü + konsol

```bash
node .claude/skills/theme-skill/scripts/screenshot.mjs $J http://localhost:8080/panel/branches out.png        # 1366px
node .claude/skills/theme-skill/scripts/screenshot.mjs $J http://localhost:8080/panel/branches out-m.png 400  # mobil
```
Çıktı: `EXC …` (JS exception), `CONSOLE …` (error/warning), `HTTP 4xx/5xx url`, `FAIL …`. Beklenen tek satır: `HTTP 404 …/hkgrotesk-regular.woff`. PNG'yi Read ile incele. Chrome yolu script içinde (`C:/Program Files/Google/Chrome/Application/chrome.exe`).

Not: login sayfasına cookie'li giderseniz dashboard'a yönlendirilirsiniz; login'i görmek için boş bir jar dosyası verin.

## 5. Manuel kontrol listesi

- Aktif menü öğesi doğru mu (alt sayfalarda da)?
- Owner/manager farkı (manager'da owner menüleri ve owner'a özel butonlar yok).
- Flash mesajları (kaydet → başarı; hatalı form → alan hataları + `old()` değerleri korunuyor).
- Onaylı işlemlerde SweetAlert çıkıyor, Vazgeç'te POST gitmiyor.
- Koyu mod ve 400px genişlikte taşma yok.
