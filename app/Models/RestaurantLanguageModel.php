<?php

declare(strict_types=1);

namespace App\Models;

/**
 * PK (restaurant_id, language_id). Tenant kapsamı içinde language_id tekildir;
 * bu yüzden kapsamlı kullanımda primaryKey olarak language_id yeterlidir.
 */
final class RestaurantLanguageModel extends TenantModel
{
    protected $table            = 'restaurant_languages';
    protected $primaryKey       = 'language_id';
    protected $useAutoIncrement = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = ['restaurant_id', 'language_id', 'sort_order', 'is_active'];

    /**
     * Restoranın dilleri, `languages` bilgisiyle birlikte.
     * $activeOnly: hem restoran düzeyinde hem global düzeyde aktif olanlar.
     *
     * @return list<array<string, mixed>> id, code, name, native_name, direction, rl_is_active, rl_sort_order
     */
    public function languages(bool $activeOnly = true): array
    {
        $builder = $this->scopedBuilder()
            ->select('languages.id, languages.code, languages.name, languages.native_name, languages.direction, languages.is_active AS global_is_active, restaurant_languages.is_active AS rl_is_active, restaurant_languages.sort_order AS rl_sort_order')
            ->join('languages', 'languages.id = restaurant_languages.language_id');

        if ($activeOnly) {
            $builder->where('restaurant_languages.is_active', 1)->where('languages.is_active', 1);
        }

        $rows = $builder
            ->orderBy('restaurant_languages.sort_order', 'ASC')
            ->orderBy('languages.id', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];

            return $row;
        }, $rows);
    }

    /**
     * @return list<int>
     */
    public function languageIds(bool $activeOnly = true): array
    {
        return array_column($this->languages($activeOnly), 'id');
    }

    /**
     * @return list<string>
     */
    public function languageCodes(bool $activeOnly = true): array
    {
        return array_map('strval', array_column($this->languages($activeOnly), 'code'));
    }

    /**
     * Restoranın dil listesini verilen kümeyle eşitler. Çıkarılan dillerin
     * çevirileri SİLİNMEZ (spec §8.5).
     *
     * @param list<int> $languageIds sıralı
     */
    public function replaceAll(array $languageIds): void
    {
        $restaurantId = $this->requireScopeRestaurantId();

        $this->scopedBuilder()->delete();

        foreach (array_values($languageIds) as $order => $languageId) {
            $this->builder()->insert([
                'restaurant_id' => $restaurantId,
                'language_id'   => $languageId,
                'sort_order'    => $order,
                'is_active'     => 1,
            ]);
        }
    }
}
