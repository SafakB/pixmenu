<?php

declare(strict_types=1);

namespace App\Models;

/**
 * PK (product_id, allergen_id). Çağıran taraf ürünün tenant'a ait olduğunu
 * ÖNCEDEN tenant kapsamlı ProductModel ile doğrulamış olmalıdır.
 */
final class ProductAllergenModel extends TenantModel
{
    public const LEVELS = ['contains', 'may_contain'];

    protected $table            = 'product_allergens';
    protected $primaryKey       = 'allergen_id';
    protected $useAutoIncrement = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = ['restaurant_id', 'product_id', 'allergen_id', 'level'];

    /**
     * @return array<int, string> allergen_id => level
     */
    public function forProduct(int $productId): array
    {
        $map = [];

        foreach ($this->where('product_id', $productId)->findAll() as $row) {
            $map[(int) $row['allergen_id']] = (string) $row['level'];
        }

        return $map;
    }

    /**
     * Ürünün alerjenlerini verilen kümeyle eşitler; gönderilmeyenler silinir.
     *
     * @param array<int, string> $levels allergen_id => level (önceden doğrulanmış)
     */
    public function replaceForProduct(int $productId, array $levels): void
    {
        $restaurantId = $this->requireScopeRestaurantId();

        $this->scopedBuilder()->where('product_id', $productId)->delete();

        foreach ($levels as $allergenId => $level) {
            $this->builder()->insert([
                'restaurant_id' => $restaurantId,
                'product_id'    => $productId,
                'allergen_id'   => (int) $allergenId,
                'level'         => $level,
            ]);
        }
    }
}
