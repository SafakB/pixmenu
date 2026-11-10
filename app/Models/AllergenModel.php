<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class AllergenModel extends Model
{
    protected $table         = 'allergens';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'icon_path', 'is_active', 'sort_order'];

    /**
     * @return list<array<string, mixed>>
     */
    public function ordered(bool $activeOnly = false): array
    {
        if ($activeOnly) {
            $this->where('is_active', 1);
        }

        return $this->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /**
     * Verilen ID'lerden var ve aktif olanları döndürür.
     *
     * @param list<int> $ids
     *
     * @return list<int>
     */
    public function activeIdsAmong(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map('intval', array_column(
            $this->select('id')->whereIn('id', $ids)->where('is_active', 1)->findAll(),
            'id',
        ));
    }
}
