<?php

declare(strict_types=1);

namespace App\Libraries\Auth;

use App\Models\AdminModel;

final class AdminAuth extends BaseAuth
{
    public const SESSION_KEY = 'admin_auth';

    protected function sessionKey(): string
    {
        return self::SESSION_KEY;
    }

    protected function findByEmail(string $email): ?array
    {
        if ($email === '') {
            return null;
        }

        return (new AdminModel())->where('email', $email)->first();
    }

    protected function findById(int $id): ?array
    {
        return (new AdminModel())->find($id);
    }

    /**
     * Bilinmeyen (config'ten kaldırılmış) rol → giriş/oturum reddedilir.
     */
    protected function isUsable(array $user, ?array $session): bool
    {
        return (int) $user['is_active'] === 1 && config('AdminRoles')->exists((string) ($user['role'] ?? ''));
    }

    /**
     * Oturumdaki admin bu izne sahip mi? Rol her istekte DB'den okunur; değişiklik anında etkilidir.
     */
    public function can(string $permission): bool
    {
        $user = $this->user();

        return $user !== null && config('AdminRoles')->allows((string) $user['role'], $permission);
    }

    public function roleLabel(): string
    {
        $user = $this->user();

        return $user === null ? '' : config('AdminRoles')->label((string) $user['role']);
    }

    protected function sessionPayload(array $user): array
    {
        return ['id' => (int) $user['id']];
    }

    protected function updateUser(int $id, array $fields): void
    {
        (new AdminModel())->update($id, $fields);
    }
}
