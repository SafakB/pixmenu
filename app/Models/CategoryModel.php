<?php

declare(strict_types=1);

namespace App\Models;

final class CategoryModel extends TenantModel
{
    protected $table          = 'categories';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['restaurant_id', 'branch_id', 'image_path', 'is_active', 'sort_order'];

    /**
     * @return list<array<string, mixed>>
     */
    public function forBranch(int $branchId): array
    {
        return $this->where('branch_id', $branchId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Kategori yalnızca verilen şubeye aitse döner.
     *
     * @return array<string, mixed>|null
     */
    public function findInBranch(int $branchId, int $categoryId): ?array
    {
        return $this->where('branch_id', $branchId)->find($categoryId);
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

    public function nextSortOrder(int $branchId): int
    {
        $row = $this->selectMax('sort_order', 'max_order')->where('branch_id', $branchId)->first();

        return $row === null || $row['max_order'] === null ? 0 : min(65535, (int) $row['max_order'] + 1);
    }
}
