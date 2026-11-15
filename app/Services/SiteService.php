<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Models\RestaurantTranslationModel;

/**
 * Menü ön ucunun (menu-ui/) "site"ı — docs/VIEW_CONTRACT.md "Menü sitesi API".
 *
 * Restoran üç yoldan bulunur:
 *  - slug              (ön ucun .env'indeki VITE_RESTAURANT)
 *  - alt alan adı host  `{slug}.{Pixmenu::$menuRootDomain}`
 *  - özel alan adı host DomainService::resolve() (paket izni + aktiflik kontrolü orada)
 *
 * Herkese açık istekte tenant bağlamı OLMADAN çalışır; tüm sorgular çözümlenen
 * restoran ID'sine sabitlenir. Restoran aktif, silinmemiş ve askıda olmamalıdır.
 */
final class SiteService
{
    /** DNS etiketi sınırı: alt alan adı olabilmesi için slug en fazla bu uzunlukta olmalı. */
    public const MAX_SLUG_LENGTH = 63;

    private const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * @return array{id: int, slug: string, default_language_id: int, logo_path: string|null}|null
     */
    public function find(?string $slug, ?string $host): ?array
    {
        if ($slug !== null && $slug !== '') {
            $slug = strtolower(trim($slug));

            return preg_match(self::SLUG_PATTERN, $slug) === 1 ? $this->restaurantRow('slug', $slug) : null;
        }

        if ($host !== null && $host !== '') {
            return $this->findByHost($host);
        }

        return null;
    }

    /**
     * @return array{id: int, slug: string, default_language_id: int, logo_path: string|null}|null
     */
    public function findByHost(string $host): ?array
    {
        $domain = DomainService::normalize($host);

        if ($domain === null) {
            return null;
        }

        $root = $this->rootDomain();

        if ($root !== '' && str_ends_with($domain, '.' . $root)) {
            $label = substr($domain, 0, -strlen('.' . $root));

            // Çok seviyeli (a.b.pixmenu.com) veya ayrılmış (admin.pixmenu.com) alt alan adı: menü yok.
            return $this->isSubdomainSlug($label) ? $this->restaurantRow('slug', $label) : null;
        }

        $site = (new DomainService())->resolve($domain);

        return $site === null ? null : $this->restaurantRow('id', $site['restaurant_id']);
    }

    /**
     * Ön ucun ihtiyaç duyduğu her şey tek yanıtta: restoran, şubeler, dil ve (seçiliyse) şubenin menüsü.
     *
     * - $branchSlug verildiyse o şubenin menüsü; şube yoksa null (→ 404).
     * - Verilmediyse ve tek aktif şube varsa onun menüsü; birden fazlaysa `menu: null` (şube seçimi).
     *
     * @param array{id: int, slug: string, default_language_id: int, logo_path: string|null} $restaurant
     *
     * @return array<string, mixed>|null
     */
    public function payload(array $restaurant, ?string $branchSlug, ?string $lang): ?array
    {
        $restaurantId = $restaurant['id'];
        $branches     = (new BranchModel())->forRestaurant($restaurantId)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        if ($branches === []) {
            return null;
        }

        $selected = null;

        if ($branchSlug !== null && $branchSlug !== '') {
            $branchSlug = strtolower($branchSlug);

            foreach ($branches as $branch) {
                if ($branch['slug'] === $branchSlug) {
                    $selected = $branch;
                    break;
                }
            }

            if ($selected === null) {
                return null;
            }
        } elseif (count($branches) === 1) {
            $selected = $branches[0];
        }

        $menu = null;

        if ($selected !== null) {
            $menu = (new MenuService())->build((string) $selected['public_code'], $lang);

            if ($menu === null) {
                return null; // gösterilebilir dili olmayan şube
            }
        }

        $languages = $this->languages($restaurantId);
        $resolver  = service('languageResolver');

        // Menü varsa onun dili esastır (şube varsayılanına göre seçilmiştir); yoksa restoranın.
        $language = $menu !== null
            ? $this->languageByCode($languages, (string) $menu['language']['code'])
            : $resolver->visitorLanguage($languages, $restaurant['default_language_id'], $lang, null)['language'];

        if ($language === null) {
            return null;
        }

        $chain     = $resolver->chain((int) $language['id'], $restaurant['default_language_id']);
        $branchIds = array_map('intval', array_column($branches, 'id'));
        $texts     = $resolver->resolveMany(BranchTranslationModel::class, $branchIds, $chain);

        return [
            'restaurant' => [
                'slug'     => $restaurant['slug'],
                'name'     => (string) ($resolver->resolveOne(RestaurantTranslationModel::class, $restaurantId, $chain)['name'] ?? ''),
                'logo_url' => upload_url($restaurant['logo_path']),
            ],
            'language'  => ['code' => $language['code'], 'direction' => $language['direction']],
            'languages' => array_map(static fn (array $l): array => [
                'code'        => $l['code'],
                'native_name' => $l['native_name'],
            ], $languages),
            'branches' => array_map(static fn (array $b): array => [
                'slug'    => $b['slug'],
                'code'    => $b['public_code'],
                'name'    => $texts[(int) $b['id']]['name'] ?? $b['slug'],
                'address' => $texts[(int) $b['id']]['address'] ?? null,
            ], $branches),
            'canonical_url' => $this->canonicalBase($restaurantId, $restaurant['slug']),
            'menu'          => $menu,
        ];
    }

    /**
     * Restoran menüsünün kanonik kök adresi (sonunda / yok):
     *  1. aktif ve kullanılabilir özel alan adı → https://menu.sirketadi.com
     *  2. alt alan adı açıksa ve slug uygunsa   → https://sirketadi.pixmenu.com
     *  3. hiçbiri                               → null (eski /m/{public_code} adresi kullanılır)
     */
    public function canonicalBase(int $restaurantId, string $slug): ?string
    {
        $domains = new DomainService();
        $custom  = $domains->current($restaurantId);

        if ($custom !== null && (int) $custom['is_active'] === 1 && $domains->resolve((string) $custom['domain']) !== null) {
            return $this->baseUrl((string) $custom['domain']);
        }

        $root = $this->rootDomain();

        if ($root !== '' && $this->isSubdomainSlug($slug)) {
            return $this->baseUrl($slug . '.' . $root);
        }

        return null;
    }

    /**
     * Şubenin paylaşılacak menü adresi. $base, canonicalBase()'in sonucudur (restoran başına bir kez hesaplanır).
     *
     * @param array<string, mixed> $branch slug + public_code içeren şube satırı
     */
    public static function branchUrl(?string $base, array $branch): string
    {
        return $base === null
            ? site_url('m/' . $branch['public_code'])
            : $base . '/' . rawurlencode((string) $branch['slug']);
    }

    /**
     * Eski /m/{public_code} adresinin yönleneceği kanonik şube adresi; yoksa null.
     */
    public function canonicalBranchUrl(string $publicCode): ?string
    {
        if (preg_match('/^[a-z0-9]{10}$/', $publicCode) !== 1) {
            return null;
        }

        $row = db_connect()->table('branches b')
            ->select('b.slug, b.public_code, r.id AS restaurant_id, r.slug AS restaurant_slug')
            ->join('restaurants r', 'r.id = b.restaurant_id')
            ->where('b.public_code', $publicCode)
            ->where('b.is_active', 1)
            ->where('b.deleted_at', null)
            ->where('r.is_active', 1)
            ->where('r.deleted_at', null)
            ->where('r.suspended_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        $base = $this->canonicalBase((int) $row['restaurant_id'], (string) $row['restaurant_slug']);

        return $base === null ? null : self::branchUrl($base, $row);
    }

    /**
     * Slug bir alt alan adı etiketi olabilir mi? (biçim, DNS uzunluğu, ayrılmış adlar)
     */
    public function isSubdomainSlug(string $slug): bool
    {
        return strlen($slug) <= self::MAX_SLUG_LENGTH
            && preg_match(self::SLUG_PATTERN, $slug) === 1
            && ! in_array($slug, config('Pixmenu')->reservedSubdomains, true);
    }

    /**
     * Config'teki alt alan adı kökü (küçük harf, şemasız, portsuz); kapalıysa ''.
     */
    public function rootDomain(): string
    {
        $root = strtolower(trim(config('Pixmenu')->menuRootDomain));
        $root = (string) preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $root);
        $root = (string) preg_replace('#[:/].*$#', '', $root);

        return trim($root, '.');
    }

    private function baseUrl(string $host): string
    {
        $config = config('Pixmenu');
        $port   = trim($config->menuPort);

        return $config->menuScheme . '://' . $host . ($port === '' ? '' : ':' . $port);
    }

    /**
     * @return array{id: int, slug: string, default_language_id: int, logo_path: string|null}|null
     */
    private function restaurantRow(string $field, int|string $value): ?array
    {
        $row = db_connect()->table('restaurants')
            ->select('id, slug, default_language_id, logo_path')
            ->where($field, $value)
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->where('suspended_at', null)
            ->get()
            ->getRowArray();

        return $row === null ? null : [
            'id'                  => (int) $row['id'],
            'slug'                => (string) $row['slug'],
            'default_language_id' => (int) $row['default_language_id'],
            'logo_path'           => $row['logo_path'],
        ];
    }

    /**
     * Restoranın aktif dilleri (global olarak da aktif), sıralı — MenuService ile aynı kural.
     *
     * @return list<array<string, mixed>>
     */
    private function languages(int $restaurantId): array
    {
        return db_connect()->table('restaurant_languages rl')
            ->select('l.id, l.code, l.native_name, l.direction')
            ->join('languages l', 'l.id = rl.language_id')
            ->where('rl.restaurant_id', $restaurantId)
            ->where('rl.is_active', 1)
            ->where('l.is_active', 1)
            ->orderBy('rl.sort_order', 'ASC')
            ->orderBy('l.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param list<array<string, mixed>> $languages
     *
     * @return array<string, mixed>|null
     */
    private function languageByCode(array $languages, string $code): ?array
    {
        foreach ($languages as $language) {
            if ($language['code'] === $code) {
                return $language;
            }
        }

        return null;
    }
}
