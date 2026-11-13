<?php

declare(strict_types=1);

namespace App\Models;

final class BranchModel extends TenantModel
{
    protected $table          = 'branches';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'restaurant_id', 'slug', 'public_code', 'default_language_id', 'template_code',
        'phone', 'is_active', 'sort_order',
    ];

    /**
     * Erişilebilir şubeler. $allowedBranchIds null ise (owner/admin) tümü.
     *
     * @param list<int>|null $allowedBranchIds
     *
     * @return list<array<string, mixed>>
     */
    public function accessible(?array $allowedBranchIds): array
    {
        if ($allowedBranchIds !== null) {
            if ($allowedBranchIds === []) {
                return [];
            }

            $this->whereIn('id', $allowedBranchIds);
        }

        return $this->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /**
     * Verilen ID'lerden bu kapsamda var olan (silinmemiş) şubelerin ID'leri.
     *
     * @param list<int> $ids
     *
     * @return list<int>
     */
    public function existingIdsAmong(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map('intval', array_column($this->select('id')->whereIn('id', $ids)->findAll(), 'id'));
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $this->where('slug', $slug);

        if ($exceptId !== null) {
            $this->where('id !=', $exceptId);
        }

        return $this->withDeleted()->countAllResults() > 0;
    }

    /**
     * public_code TÜM SİSTEMDE tekildir; çakışma kontrolü bilerek kapsam dışıdır
     * (silinmiş şubeler dahil). Yalnızca var/yok bilgisi döner.
     */
    public function publicCodeExistsGlobally(string $code): bool
    {
        return $this->db->table($this->table)->where('public_code', $code)->countAllResults() > 0;
    }

    /**
     * Şablonu kullanan şube sayısı — TÜM SİSTEMDE (kapsam dışı, admin içindir).
     * $withDeleted: silinmiş şubeler de sayılır (FK RESTRICT silmeyi engeller).
     */
    public function countUsingTemplateGlobally(string $code, bool $withDeleted = false): int
    {
        $builder = $this->db->table($this->table)->where('template_code', $code);

        if (! $withDeleted) {
            $builder->where('deleted_at', null);
        }

        return $builder->countAllResults();
    }

    /**
     * Şablon kodu => kullanan (silinmemiş) şube sayısı — TÜM SİSTEMDE.
     *
     * @return array<string, int>
     */
    public function templateUsageCounts(): array
    {
        $rows = $this->db->table($this->table)
            ->select('template_code, COUNT(*) AS total')
            ->where('deleted_at', null)
            ->groupBy('template_code')
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($rows, 'total', 'template_code'));
    }

    /**
     * Verilen dili varsayılan dil olarak kullanan şube sayısı.
     */
    public function countUsingDefaultLanguage(int $languageId): int
    {
        return (int) $this->where('default_language_id', $languageId)->countAllResults();
    }
}
