<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Restoranın özel alan adı (tenant; restoran başına en fazla 1).
 * `domain` küçük harf, şemasız ve `www.`'siz saklanır.
 */
final class RestaurantDomainModel extends TenantModel
{
    protected $table         = 'restaurant_domains';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['restaurant_id', 'domain', 'is_active', 'verified_at'];

    /**
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        return $this->orderBy('id', 'ASC')->first();
    }

    /**
     * Alan adı TÜM SİSTEMDE tekildir; kontrol bilerek kapsam dışıdır.
     */
    public function domainTakenGlobally(string $domain, ?int $exceptId = null): bool
    {
        $builder = $this->db->table($this->table)->where('domain', $domain);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }
}
