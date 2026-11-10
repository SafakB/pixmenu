<?php

declare(strict_types=1);

namespace App\Models;

final class RestaurantUserModel extends TenantModel
{
    protected $table          = 'restaurant_users';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'restaurant_id', 'name', 'email', 'password_hash', 'role',
        'is_active', 'last_login_at', 'password_changed_at',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function ordered(?string $role = null): array
    {
        if ($role !== null) {
            $this->where('role', $role);
        }

        return $this->orderBy('role', 'ASC')->orderBy('name', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /**
     * E-posta TÜM SİSTEMDE tekildir (spec §4.2, §17.5); bu yüzden bu kontrol bilerek
     * kapsam dışıdır. Yalnızca var/yok bilgisi döner, başka tenant'ın verisi dönmez.
     */
    public function emailExistsGlobally(string $email, ?int $exceptUserId = null): bool
    {
        $builder = $this->db->table($this->table)->where('email', $email);

        if ($exceptUserId !== null) {
            $builder->where('id !=', $exceptUserId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Restorandaki aktif owner sayısı ($exceptUserId hariç).
     */
    public function countActiveOwners(?int $exceptUserId = null): int
    {
        $this->where('role', 'owner')->where('is_active', 1);

        if ($exceptUserId !== null) {
            $this->where('id !=', $exceptUserId);
        }

        return (int) $this->countAllResults();
    }
}
