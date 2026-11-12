<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BranchModel;
use App\Models\LanguageModel;

/**
 * Herkese açık menü (spec §11.3). Hem HTML hem JSON controller'ı kullanır.
 *
 * Sonuç `menu_{branch_id}_{lang}` anahtarında cache'lenir; şubeye ait herhangi
 * bir değişiklikte ilgili şubenin tüm dil anahtarları silinir.
 */
final class MenuService
{
    public static function cacheKey(int $branchId, string $langCode): string
    {
        return 'menu_' . $branchId . '_' . preg_replace('/[^A-Za-z0-9\-]/', '', $langCode);
    }

    /**
     * Menüyü kurar (spec §11.3 biçiminde `data` dizisi). Şube/restoran yoksa,
     * pasifse veya silinmişse null döner (çağıran 404 üretir).
     *
     * Dil seçimi (spec §8.4): $lang restoranın aktif dillerindeyse o; değilse
     * $cookieLang (hâlâ geçerliyse); değilse şubenin varsayılan dili.
     *
     * Sorgu sayısı dil ve ürün sayısından bağımsız sabittir (cache yokken ≤ 10).
     * Bu servis tenant bağlamı OLMADAN çalışır; tüm sorgular public_code'dan
     * türetilen şube/restoran ID'sine sabitlenir.
     *
     * @return array<string, mixed>|null
     */
    public function build(string $publicCode, ?string $lang, ?string $cookieLang = null): ?array
    {
        if (preg_match('/^[a-z0-9]{10}$/', $publicCode) !== 1) {
            return null;
        }

        $db = db_connect();

        // 1) Şube + restoran (ikisi de aktif ve silinmemiş, restoran askıda değil)
        $branch = $db->table('branches b')
            ->select('b.id, b.restaurant_id, b.public_code, b.default_language_id, b.phone,
                      r.default_language_id AS restaurant_default_language_id, r.currency_code, r.logo_path')
            ->join('restaurants r', 'r.id = b.restaurant_id')
            ->where('b.public_code', $publicCode)
            ->where('b.is_active', 1)
            ->where('b.deleted_at', null)
            ->where('r.is_active', 1)
            ->where('r.deleted_at', null)
            ->where('r.suspended_at', null)   // askıdaki restoranın menüsü kapalı
            ->get()
            ->getRowArray();

        if ($branch === null) {
            return null;
        }

        $branchId     = (int) $branch['id'];
        $restaurantId = (int) $branch['restaurant_id'];

        // 2) Restoranın aktif dilleri (global olarak da aktif)
        $languages = $db->table('restaurant_languages rl')
            ->select('l.id, l.code, l.native_name, l.direction')
            ->join('languages l', 'l.id = rl.language_id')
            ->where('rl.restaurant_id', $restaurantId)
            ->where('rl.is_active', 1)
            ->where('l.is_active', 1)
            ->orderBy('rl.sort_order', 'ASC')
            ->orderBy('l.id', 'ASC')
            ->get()
            ->getResultArray();

        $resolver = service('languageResolver');
        $language = $resolver->visitorLanguage($languages, (int) $branch['default_language_id'], $lang, $cookieLang)['language'];

        if ($language === null) {
            return null; // restoranın gösterilebilir hiçbir dili yok
        }

        $langCode = (string) $language['code'];
        $cacheKey = self::cacheKey($branchId, $langCode);
        $cached   = cache($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $chain = $resolver->chain(
            (int) $language['id'],
            (int) $branch['default_language_id'],
            (int) $branch['restaurant_default_language_id'],
        );

        // 3) Kategoriler
        $categories = $db->table('categories')
            ->select('id, image_path')
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $categoryIds = array_map('intval', array_column($categories, 'id'));

        // 4) Ürünler
        $products = $categoryIds === [] ? [] : $db->table('products')
            ->select('id, category_id, price, image_path, thumb_path')
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $productIds = array_map('intval', array_column($products, 'id'));

        // 5) Ürün alerjenleri (yalnızca aktif alerjenler)
        $allergenRows = $productIds === [] ? [] : $db->table('product_allergens pa')
            ->select('pa.product_id, pa.allergen_id, pa.level, a.code, a.icon_path')
            ->join('allergens a', 'a.id = pa.allergen_id')
            ->where('pa.restaurant_id', $restaurantId)
            ->whereIn('pa.product_id', $productIds)
            ->where('a.is_active', 1)
            ->orderBy('a.sort_order', 'ASC')
            ->orderBy('a.id', 'ASC')
            ->get()
            ->getResultArray();

        $allergenIds = array_values(array_unique(array_map('intval', array_column($allergenRows, 'allergen_id'))));

        // 6) Alerjen çevirileri (küçük tablo; dil koduyla birlikte, tek sorgu)
        $allergenNames = $this->allergenNames($db, $allergenIds, $langCode, (int) $branch['default_language_id']);

        // 7) Restoran + şube çevirileri (tek UNION sorgusu)
        [$restaurantText, $branchText] = $this->ownTranslations($db, $restaurantId, $branchId, $chain);

        // 8-10) Kategori ve ürün çevirileri (zincirdeki diller; eksikler için tek ek sorgu)
        [$categoryText, $productText] = $this->contentTranslations($db, $categoryIds, $productIds, $chain);

        $allergensOut      = [];
        $productAllergens  = [];

        foreach ($allergenRows as $row) {
            $allergenId = (int) $row['allergen_id'];

            $productAllergens[(int) $row['product_id']][] = ['id' => $allergenId, 'level' => $row['level']];
            $allergensOut[$allergenId] ??= [
                'id'       => $allergenId,
                'code'     => $row['code'],
                'name'     => $allergenNames[$allergenId] ?? $row['code'],
                'icon_url' => upload_url($row['icon_path']),
            ];
        }

        $productsByCategory = [];

        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $text      = $productText[$productId] ?? null;

            if ($text === null) {
                continue; // hiçbir dilde adı olmayan ürün gösterilemez
            }

            $productsByCategory[(int) $product['category_id']][] = [
                'id'          => $productId,
                'name'        => $text['name'],
                'description' => $text['description'],
                // Fiyat string döner (float yuvarlama hatası olmasın).
                'price'       => number_format((float) $product['price'], 2, '.', ''),
                'image_url'   => upload_url($product['image_path']),
                'thumb_url'   => upload_url($product['thumb_path']),
                'allergens'   => $productAllergens[$productId] ?? [],
            ];
        }

        $categoriesOut = [];

        foreach ($categories as $category) {
            $categoryId = (int) $category['id'];
            $text       = $categoryText[$categoryId] ?? null;

            // Ürünü olmayan kategoriler listelenmez.
            if ($text === null || empty($productsByCategory[$categoryId])) {
                continue;
            }

            $categoriesOut[] = [
                'id'          => $categoryId,
                'name'        => $text['name'],
                'description' => $text['description'],
                'image_url'   => upload_url($category['image_path']),
                'products'    => $productsByCategory[$categoryId],
            ];
        }

        // `allergens` yalnızca menüde GÖRÜNEN ürünlerde kullanılan alerjenleri içerir.
        $usedAllergenIds = [];

        foreach ($categoriesOut as $category) {
            foreach ($category['products'] as $product) {
                foreach ($product['allergens'] as $allergen) {
                    $usedAllergenIds[$allergen['id']] = true;
                }
            }
        }

        $data = [
            'restaurant' => [
                'name'        => $restaurantText['name'] ?? '',
                'description' => $restaurantText['description'] ?? null,
                'logo_url'    => upload_url($branch['logo_path']),
                'currency'    => $branch['currency_code'],
            ],
            'branch' => [
                'code'    => $branch['public_code'],
                'name'    => $branchText['name'] ?? '',
                'address' => $branchText['address'] ?? null,
                'phone'   => $branch['phone'],
            ],
            'language'  => ['code' => $langCode, 'direction' => $language['direction']],
            'languages' => array_map(static fn (array $l): array => [
                'code'        => $l['code'],
                'native_name' => $l['native_name'],
            ], $languages),
            'allergens'  => array_values(array_intersect_key($allergensOut, $usedAllergenIds)),
            'categories' => $categoriesOut,
        ];

        cache()->save($cacheKey, $data, config('Pixmenu')->menuCacheTtl);

        return $data;
    }

    /**
     * Alerjen adları: istenen dil → şubenin varsayılan dili → tr → en → mevcut ilk çeviri.
     *
     * @param list<int> $allergenIds
     *
     * @return array<int, string>
     */
    private function allergenNames(\CodeIgniter\Database\BaseConnection $db, array $allergenIds, string $langCode, int $branchDefaultLanguageId): array
    {
        if ($allergenIds === []) {
            return [];
        }

        $rows = $db->table('allergen_translations t')
            ->select('t.allergen_id, t.language_id, t.name, l.code')
            ->join('languages l', 'l.id = t.language_id')
            ->whereIn('t.allergen_id', $allergenIds)
            ->orderBy('t.id', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['allergen_id']][] = $row;
        }

        $names = [];

        foreach ($grouped as $allergenId => $translations) {
            $byCode = array_column($translations, 'name', 'code');
            $byId   = array_column($translations, 'name', 'language_id');

            $names[$allergenId] = $byCode[$langCode]
                ?? $byId[$branchDefaultLanguageId]
                ?? $byCode['tr']
                ?? $byCode['en']
                ?? $translations[0]['name'];
        }

        return $names;
    }

    /**
     * Restoranın ve şubenin kendi çevirileri, tek sorguda.
     *
     * @param list<int> $chain
     *
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    private function ownTranslations(\CodeIgniter\Database\BaseConnection $db, int $restaurantId, int $branchId, array $chain): array
    {
        $rows = $db->query(
            "SELECT 'r' AS kind, id, language_id, name, description, NULL AS address
               FROM restaurant_translations WHERE restaurant_id = ?
             UNION ALL
             SELECT 'b' AS kind, id, language_id, name, description, address
               FROM branch_translations WHERE branch_id = ?
             ORDER BY id ASC",
            [$restaurantId, $branchId],
        )->getResultArray();

        $grouped = ['r' => [], 'b' => []];

        foreach ($rows as $row) {
            $grouped[$row['kind']][(int) $row['language_id']] = $row;
        }

        $resolver = service('languageResolver');

        return [$resolver->pick($grouped['r'], $chain), $resolver->pick($grouped['b'], $chain)];
    }

    /**
     * Kategori ve ürün çevirileri. Önce yalnızca zincirdeki diller çekilir (2 sorgu);
     * zincirde hiç çevirisi olmayan kayıtlar varsa "mevcut ilk çeviri" için TEK ek
     * UNION sorgusu yapılır.
     *
     * @param list<int> $categoryIds
     * @param list<int> $productIds
     * @param list<int> $chain
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function contentTranslations(\CodeIgniter\Database\BaseConnection $db, array $categoryIds, array $productIds, array $chain): array
    {
        $fetch = static function (string $table, string $fk, array $ids) use ($db, $chain): array {
            if ($ids === [] || $chain === []) {
                return [];
            }

            $grouped = [];
            $rows    = $db->table($table)
                ->select("{$fk} AS parent_id, language_id, name, description")
                ->whereIn($fk, $ids)
                ->whereIn('language_id', $chain)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $grouped[(int) $row['parent_id']][(int) $row['language_id']] = $row;
            }

            return $grouped;
        };

        $categories = $fetch('category_translations', 'category_id', $categoryIds);
        $products   = $fetch('product_translations', 'product_id', $productIds);

        $missingCategories = array_values(array_diff($categoryIds, array_keys($categories)));
        $missingProducts   = array_values(array_diff($productIds, array_keys($products)));

        if ($missingCategories !== [] || $missingProducts !== []) {
            $parts  = [];
            $params = [];

            if ($missingCategories !== []) {
                $parts[]  = "SELECT 'c' AS kind, id, category_id AS parent_id, language_id, name, description FROM category_translations WHERE category_id IN ?";
                $params[] = $missingCategories;
            }

            if ($missingProducts !== []) {
                $parts[]  = "SELECT 'p' AS kind, id, product_id AS parent_id, language_id, name, description FROM product_translations WHERE product_id IN ?";
                $params[] = $missingProducts;
            }

            $rows = $db->query(implode(' UNION ALL ', $parts) . ' ORDER BY id ASC', $params)->getResultArray();

            foreach ($rows as $row) {
                if ($row['kind'] === 'c') {
                    $categories[(int) $row['parent_id']][(int) $row['language_id']] = $row;
                } else {
                    $products[(int) $row['parent_id']][(int) $row['language_id']] = $row;
                }
            }
        }

        $resolver = service('languageResolver');
        $resolve  = static function (array $grouped) use ($resolver, $chain): array {
            $resolved = [];

            foreach ($grouped as $parentId => $byLanguage) {
                $picked = $resolver->pick($byLanguage, $chain);

                if ($picked !== null) {
                    $resolved[$parentId] = $picked;
                }
            }

            return $resolved;
        };

        return [$resolve($categories), $resolve($products)];
    }

    /**
     * Şubenin tüm dil anahtarlarını siler.
     */
    public function invalidate(int $branchId): void
    {
        foreach ((new LanguageModel())->allCodes() as $code) {
            cache()->delete(self::cacheKey($branchId, $code));
        }
    }

    /**
     * Restoran değişikliğinde: tüm şubelerinin cache'i.
     */
    public function invalidateRestaurant(int $restaurantId): void
    {
        $branches = (new BranchModel())->forRestaurant($restaurantId)->withDeleted()->select('id')->findAll();

        foreach ($branches as $branch) {
            $this->invalidate((int) $branch['id']);
        }
    }

    /**
     * Alerjen / global dil değişikliğinde: tüm menü cache'i.
     */
    public function invalidateAll(): void
    {
        cache()->deleteMatching('menu_*');
    }
}
