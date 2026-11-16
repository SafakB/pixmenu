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
     * Google kimliğiyle giriş (Google e-postayı doğrulamış olmalı — çağıran kontrol eder).
     *
     *  - `google_id` eşleşen kullanıcı → giriş (`ok`).
     *  - Yoksa aynı e-postalı kullanıcı → hesaba bağlanır ve giriş (`linked`). Hesabın e-postası
     *    doğrulanmamışsa (self-servis kayıt) önceki şifre kaldırılır ve diğer oturumlar düşer
     *    (`linked_password_cleared`): başkasının adresiyle önceden açılmış hesap ele geçirilemez.
     *  - Hesap başka bir Google kimliğine bağlıysa → `conflict` (üzerine yazılmaz).
     *  - Kullanıcı yok → `not_found` (kayıt akışına yönlendirilir).
     *  - Kullanıcı veya restoran pasif → `unusable`.
     *
     * @return 'ok'|'linked'|'linked_password_cleared'|'not_found'|'conflict'|'unusable'
     */
    public function attemptGoogle(string $sub, string $email): string
    {
        $users = static fn (): RestaurantUserModel => (new RestaurantUserModel())->withoutTenantScope();
        $email = mb_strtolower(trim($email));
        $user  = $sub === '' ? null : $users()->where('google_id', $sub)->first();

        if ($user !== null) {
            return $this->login($user) ? 'ok' : 'unusable';
        }

        $user = $this->findByEmail($email);

        if ($user === null) {
            return 'not_found';
        }

        if (! empty($user['google_id'])) {
            return 'conflict';
        }

        if (! $this->isUsable($user, null)) {
            return 'unusable';
        }

        $now    = date('Y-m-d H:i:s');
        $fields = ['google_id' => $sub];
        $result = 'linked';

        if (empty($user['email_verified_at'])) {
            $fields += ['email_verified_at' => $now, 'password_hash' => null, 'password_changed_at' => $now];
            $result = 'linked_password_cleared';
        }

        $users()->update((int) $user['id'], $fields);

        return $this->login($user) ? $result : 'unusable';
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
