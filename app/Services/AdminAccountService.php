<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminModel;
use InvalidArgumentException;

/**
 * Admin hesapları — YALNIZCA CLI komutlarından (`admin:*`) kullanılır; web arayüzü yoktur.
 * Roller ve izinler: Config\AdminRoles.
 *
 * Kural: sistemde en az bir aktif süper admin kalmalıdır.
 */
final class AdminAccountService
{
    public const MIN_PASSWORD_LENGTH = 10;

    private AdminModel $admins;

    public function __construct(?AdminModel $admins = null)
    {
        $this->admins = $admins ?? new AdminModel();
    }

    /**
     * @return array{id: int, password: string, generated: bool}
     */
    public function create(string $name, string $email, string $role, ?string $password = null): array
    {
        $name  = trim($name);
        $email = mb_strtolower(trim($email));

        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Ad zorunludur (en fazla 100 karakter).');
        }

        $this->assertEmail($email);
        $this->assertRole($role);

        if ($this->findByEmail($email) !== null) {
            throw new InvalidArgumentException('Bu e-posta ile bir admin zaten var: ' . $email);
        }

        $generated = $password === null || $password === '';
        $password  = $generated ? self::generatePassword() : (string) $password;
        $this->assertPassword($password);

        $id = (int) $this->admins->insert([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
            'is_active'     => 1,
        ], true);

        return ['id' => $id, 'password' => $password, 'generated' => $generated];
    }

    /**
     * Rol / aktiflik / parola değişikliği. Parola değişirse adminin açık oturumları düşer
     * (BaseAuth `password_changed_at`'e bakar); rol ve aktiflik her istekte DB'den okunur.
     *
     * @param array{role?: string, is_active?: bool, password?: string|null} $changes
     *                                                                             password: null → rastgele üret
     *
     * @return array{id: int, password: string|null, generated: bool}
     */
    public function update(string $email, array $changes): array
    {
        $admin = $this->findByEmail($email) ?? throw new InvalidArgumentException('Admin bulunamadı: ' . $email);
        $id    = (int) $admin['id'];
        $data  = [];

        if (array_key_exists('role', $changes)) {
            $this->assertRole((string) $changes['role']);
            $data['role'] = (string) $changes['role'];
        }

        if (array_key_exists('is_active', $changes)) {
            $data['is_active'] = $changes['is_active'] ? 1 : 0;
        }

        $password  = null;
        $generated = false;

        if (array_key_exists('password', $changes)) {
            $generated = $changes['password'] === null || $changes['password'] === '';
            $password  = $generated ? self::generatePassword() : (string) $changes['password'];
            $this->assertPassword($password);
            $data['password_hash']       = password_hash($password, PASSWORD_DEFAULT);
            $data['password_changed_at'] = date('Y-m-d H:i:s');
        }

        if ($data === []) {
            throw new InvalidArgumentException('Değiştirilecek bir şey belirtilmedi (--role, --active, --password).');
        }

        $this->assertKeepsSuperAdmin($admin, $data);

        $this->admins->update($id, $data);

        return ['id' => $id, 'password' => $password, 'generated' => $generated];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->admins->select('id, name, email, role, is_active, last_login_at, created_at')
            ->orderBy('id', 'ASC')->findAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));

        return $email === '' ? null : $this->admins->where('email', $email)->first();
    }

    public static function generatePassword(int $length = 16): string
    {
        // Karışması kolay karakterler (0/O, 1/l/I) yok.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $max      = strlen($alphabet) - 1;
        $out      = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    private function assertEmail(string $email): void
    {
        if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Geçerli bir e-posta girin.');
        }
    }

    private function assertRole(string $role): void
    {
        $roles = config('AdminRoles');

        if (! $roles->exists($role)) {
            throw new InvalidArgumentException('Geçersiz rol: ' . $role . ' (geçerli: ' . implode(', ', array_keys($roles->roles)) . ')');
        }
    }

    private function assertPassword(string $password): void
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Parola en az ' . self::MIN_PASSWORD_LENGTH . ' karakter olmalı.');
        }
    }

    /**
     * Değişiklik bu admini "aktif süper admin" olmaktan çıkarıyorsa, başka aktif süper admin kalmalı.
     *
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $data
     */
    private function assertKeepsSuperAdmin(array $admin, array $data): void
    {
        $super     = config('AdminRoles')->superRole;
        $wasActive = (int) $admin['is_active'] === 1 && $admin['role'] === $super;
        $isActive  = (int) ($data['is_active'] ?? $admin['is_active']) === 1 && ($data['role'] ?? $admin['role']) === $super;

        if (! $wasActive || $isActive) {
            return;
        }

        $others = $this->admins->where('role', $super)->where('is_active', 1)
            ->where('id !=', (int) $admin['id'])->countAllResults();

        if ($others === 0) {
            throw new InvalidArgumentException('Son aktif süper admin pasifleştirilemez veya rolü değiştirilemez. Önce başka bir süper admin oluşturun.');
        }
    }
}
