<?php

declare(strict_types=1);

namespace App\Models;

final class ProductModel extends TenantModel
{
    protected $table          = 'products';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'restaurant_id', 'branch_id', 'category_id', 'price',
        'image_path', 'thumb_path', 'is_active', 'sort_order',
    ];

    /**
     * Şubenin ürünleri; isteğe bağlı kategori ve isim araması (herhangi bir dilde).
     *
     * @return list<array<string, mixed>>
     */
    public function forBranch(int $branchId, ?int $categoryId = null, ?string $search = null): array
    {
        $this->where('products.branch_id', $branchId);

        if ($categoryId !== null) {
            $this->where('products.category_id', $categoryId);
        }

        $search = $search === null ? '' : trim($search);

        if ($search !== '') {
            $like = '%' . $this->db->escapeLikeString($search) . '%';
            // Alt sorgu yalnızca dış sorgudaki (zaten tenant kapsamlı) ürünlerin çevirilerine bakar.
            $this->where(
                'EXISTS (SELECT 1 FROM product_translations pt WHERE pt.product_id = products.id AND pt.name LIKE '
                . $this->db->escape($like) . " ESCAPE '!')",
                null,
                false,
            );
        }

        return $this->orderBy('products.category_id', 'ASC')
            ->orderBy('products.sort_order', 'ASC')
            ->orderBy('products.id', 'ASC')
            ->findAll();
    }

    /**
     * Ürün yalnızca verilen şubeye aitse döner.
     *
     * @return array<string, mixed>|null
     */
    public function findInBranch(int $branchId, int $productId): ?array
    {
        return $this->where('branch_id', $branchId)->find($productId);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    public function idsInBranchAmong(int $branchId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map('intval', array_column(
            $this->select('id')->where('branch_id', $branchId)->whereIn('id', $ids)->findAll(),
            'id',
        ));
    }

    /**
     * Şubedeki (silinmemiş) ürün sayıları, kategoriye göre.
     *
     * @return array<int, int> category_id => adet
     */
    public function countsByCategory(int $branchId): array
    {
        $rows = $this->scopedBuilder()
            ->select('category_id, COUNT(*) AS total')
            ->where('branch_id', $branchId)
            ->where('deleted_at', null)
            ->groupBy('category_id')
            ->get()
            ->getResultArray();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['category_id']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countInCategory(int $categoryId): int
    {
        return (int) $this->where('category_id', $categoryId)->countAllResults();
    }

    public function nextSortOrder(int $categoryId): int
    {
        $row = $this->selectMax('sort_order', 'max_order')->where('category_id', $categoryId)->first();

        return $row === null || $row['max_order'] === null ? 0 : min(65535, (int) $row['max_order'] + 1);
    }
}
