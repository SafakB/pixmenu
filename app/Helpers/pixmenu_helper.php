<?php

declare(strict_types=1);

use CodeIgniter\HTTP\RequestInterface;

if (! function_exists('wants_json')) {
    /**
     * İstek JSON yanıt bekliyor mu? (AJAX, JSON gövde veya Accept: application/json)
     */
    function wants_json(RequestInterface $request): bool
    {
        if (method_exists($request, 'isAJAX') && $request->isAJAX()) {
            return true;
        }

        $accept      = strtolower($request->getHeaderLine('Accept'));
        $contentType = strtolower($request->getHeaderLine('Content-Type'));

        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}

if (! function_exists('json_error_body')) {
    /**
     * Spec §11.2 hata gövdesi.
     *
     * @param array<string, string> $errors
     *
     * @return array<string, mixed>
     */
    function json_error_body(string $message, array $errors = []): array
    {
        $body = ['status' => 'error', 'message' => $message];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return $body;
    }
}

if (! function_exists('pix_slug')) {
    /**
     * Metinden URL slug'ı üretir; Türkçe karakterler sadeleştirilir (ö→o, ü→u, ş→s…).
     */
    function pix_slug(string $text, int $maxLength = 100): string
    {
        helper(['url', 'text']);

        $text = strtr($text, [
            'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'I' => 'i',
            'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
        ]);

        $slug = url_title(convert_accented_characters($text), '-', true);
        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '', $slug);
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');

        return trim(substr($slug, 0, $maxLength), '-');
    }
}

if (! function_exists('pix_initials')) {
    /**
     * Avatar için baş harfler: "Şafak Bahçe" → "ŞB", "ali" → "A".
     * İlk ve son kelimenin ilk harfi; Türkçe büyük harf kuralı (i → İ, ı → I) uygulanır.
     */
    function pix_initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '?';
        }

        $letters = mb_substr($words[0], 0, 1);

        if (count($words) > 1) {
            $letters .= mb_substr($words[array_key_last($words)], 0, 1);
        }

        return mb_strtoupper(strtr($letters, ['i' => 'İ', 'ı' => 'I']));
    }
}

if (! function_exists('pix_deleted_slug')) {
    /**
     * Soft delete'te unique çakışmasını önlemek için değerin sonuna `--deleted-{id}` ekler
     * (kolon uzunluğunu aşmayacak şekilde kırparak).
     */
    function pix_deleted_slug(string $value, int $id, int $maxLength = 120): string
    {
        $suffix = '--deleted-' . $id;

        return substr($value, 0, max(0, $maxLength - strlen($suffix))) . $suffix;
    }
}

if (! function_exists('upload_url')) {
    /**
     * DB'deki göreli upload yolunu tam URL'ye çevirir.
     */
    function upload_url(?string $path): ?string
    {
        return ($path === null || $path === '') ? null : base_url($path);
    }
}

if (! function_exists('asset_url')) {
    /**
     * public/assets altındaki tema dosyasının URL'si; dosya değiştikçe ?v= ile cache kırılır.
     */
    function asset_url(string $path): string
    {
        $path = ltrim($path, '/');
        $file = FCPATH . 'assets/' . $path;
        $url  = base_url('assets/' . $path);

        return is_file($file) ? $url . '?v=' . filemtime($file) : $url;
    }
}

if (! function_exists('pix_money')) {
    /**
     * "1499.90" + "TRY" → "1.499,90 TRY" (görünüm için).
     */
    function pix_money(string|float|int|null $amount, ?string $currency = null): string
    {
        $formatted = number_format((float) $amount, 2, ',', '.');

        return $currency === null || $currency === '' ? $formatted : $formatted . ' ' . $currency;
    }
}

if (! function_exists('pix_date')) {
    /**
     * 'Y-m-d' / 'Y-m-d H:i:s' → 'd.m.Y' (boşsa '—').
     */
    function pix_date(?string $value, bool $withTime = false): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $time = strtotime($value);

        return $time === false ? $value : date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $time);
    }
}

if (! function_exists('admin_can')) {
    /**
     * Oturumdaki admin bu izne sahip mi? (Config\AdminRoles) — admin görünümlerinde
     * düğme/menü gizlemek için. Asıl koruma rotadaki `adminperm` filtresidir.
     */
    function admin_can(string $permission): bool
    {
        return service('adminAuth')->can($permission);
    }
}
