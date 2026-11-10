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
