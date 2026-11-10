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

    protected function isUsable(array $user, ?array $session): bool
    {
        return (int) $user['is_active'] === 1;
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
