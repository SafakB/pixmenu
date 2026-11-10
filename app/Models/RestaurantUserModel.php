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
