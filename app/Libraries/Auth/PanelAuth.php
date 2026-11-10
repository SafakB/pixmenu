<?php

declare(strict_types=1);

namespace App\Libraries\Auth;

use App\Models\RestaurantModel;
use App\Models\RestaurantUserBranchModel;
use App\Models\RestaurantUserModel;

/**
 * Restoran paneli kimlik doğrulaması.
 *
 * Bu sınıf tenant bağlamı KURULMADAN ÖNCE çalıştığı için restaurant_users
 * tablosunu kapsam dışı (withoutTenantScope) okur; bu, spec §7.2'nin
 * açıkça izin verdiği tek panel-tarafı istisnadır.
 */
final class PanelAuth extends BaseAuth
{
    public const SESSION_KEY = 'panel_auth';

    /** @var array<string, mixed>|null */
    private ?array $restaurant = null;

    protected function sessionKey(): string
    {
        return self::SESSION_KEY;
    }

    protected function findByEmail(string $email): ?array
    {
        if ($email === '') {
            return null;
        }

        // E-posta global unique; soft-delete'li kayıtlar model tarafından dışlanır.
        return (new RestaurantUserModel())->withoutTenantScope()->where('email', $email)->first();
    }

    protected function findById(int $id): ?array
    {
        return (new RestaurantUserModel())->withoutTenantScope()->find($id);
    }

    protected function isUsable(array $user, ?array $session): bool
    {
        if ((int) $user['is_active'] !== 1) {
            return false;
        }

        // Oturumdaki restoran, DB'deki ile eşleşmeli.
        if ($session !== null && (int) ($session['restaurant_id'] ?? 0) !== (int) $user['restaurant_id']) {
            return false;
        }

        // Restoran aktif ve silinmemiş olmalı (soft-delete'li kayıt find() ile gelmez).
        $restaurant = (new RestaurantModel())->find((int) $user['restaurant_id']);

        if ($restaurant === null || (int) $restaurant['is_active'] !== 1) {
            return false;
        }

        $this->restaurant = $restaurant;

        return true;
    }

    protected function sessionPayload(array $user): array
    {
        return ['id' => (int) $user['id'], 'restaurant_id' => (int) $user['restaurant_id']];
    }

    protected function updateUser(int $id, array $fields): void
    {
        (new RestaurantUserModel())->withoutTenantScope()->update($id, $fields);
    }

    /**
     * Doğrulanmış kullanıcının restoranı (user() null değilse dolu).
     *
     * @return array<string, mixed>|null
     */
    public function restaurant(): ?array
    {
        return $this->user() === null ? null : $this->restaurant;
    }

    /**
     * Manager'ın izinli (silinmemiş) şube ID'leri.
     *
     * @param array<string, mixed> $user
     *
     * @return list<int>
     */
    public function managerBranchIds(array $user): array
    {
        return (new RestaurantUserBranchModel())
            ->forRestaurant((int) $user['restaurant_id'])
            ->branchIdsForUser((int) $user['id']);
    }
}
