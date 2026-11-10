<?php

declare(strict_types=1);

namespace App\Libraries\Tenant;

use InvalidArgumentException;

/**
 * İstek başına tek örnek (Config\Services::tenant()).
 *
 * set() YALNIZCA PanelAuthFilter tarafından çağrılır. Değerler her istekte
 * veritabanından yeniden doğrulanarak doldurulur (spec §6.1, §7.2).
 */
final class TenantContext
{
    private ?int $restaurantId = null;
    private ?int $userId       = null;
    private ?string $role      = null;

    /** @var list<int>|null manager için izinli şubeler; owner için null = hepsi */
    private ?array $branchIds = null;

    /**
     * @param list<int>|null $branchIds
     */
    public function set(int $restaurantId, int $userId, string $role, ?array $branchIds): void
    {
        if ($restaurantId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Geçersiz tenant kimliği.');
        }

        if (! in_array($role, ['owner', 'manager'], true)) {
            throw new InvalidArgumentException('Geçersiz rol.');
        }

        $this->restaurantId = $restaurantId;
        $this->userId       = $userId;
        $this->role         = $role;
        // Owner her şubeye erişir; manager için liste zorunlu (boş olabilir).
        $this->branchIds = $role === 'owner'
            ? null
            : array_values(array_unique(array_map('intval', $branchIds ?? [])));
    }

    public function isSet(): bool
    {
        return $this->restaurantId !== null;
    }

    public function restaurantId(): int
    {
        return $this->restaurantId ?? throw TenantNotSetException::create();
    }

    public function userId(): int
    {
        return $this->userId ?? throw TenantNotSetException::create();
    }

    public function role(): string
    {
        return $this->role ?? throw TenantNotSetException::create();
    }

    public function isOwner(): bool
    {
        return $this->role() === 'owner';
    }

    /**
     * Owner → true (şubenin tenant'a ait olduğunu çağıran taraf TenantModel ile doğrular),
     * manager → yalnızca atanmış şubeler.
     */
    public function canAccessBranch(int $branchId): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        return in_array($branchId, $this->branchIds ?? [], true);
    }

    /**
     * @return list<int>|null null = tüm şubeler (owner)
     */
    public function allowedBranchIds(): ?array
    {
        $this->restaurantId();

        return $this->branchIds;
    }

    public function clear(): void
    {
        $this->restaurantId = null;
        $this->userId       = null;
        $this->role         = null;
        $this->branchIds    = null;
    }
}
