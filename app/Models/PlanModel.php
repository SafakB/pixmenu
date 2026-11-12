<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Paketler (global, yalnızca admin yazar).
 */
final class PlanModel extends Model
{
    protected $table         = 'plans';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'code', 'max_branches', 'allow_custom_domain', 'allow_custom_design', 'trial_days',
        'price_monthly', 'price_yearly', 'currency_code', 'is_featured', 'is_active', 'sort_order',
    ];

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
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }
}
