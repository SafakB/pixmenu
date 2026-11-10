<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\BranchModel;
use App\Models\RestaurantUserBranchModel;
use App\Models\RestaurantUserModel;
use App\Services\Concerns\Transactional;
use App\Validation\PixmenuRules;

/**
 * Restoran kullanıcıları (admin + owner) — spec §10.1.
 *
 *  - Admin her rolde kullanıcı açabilir.
 *  - Owner yalnızca `manager` açabilir/düzenleyebilir; kendisini ve diğer
 *    owner'ları silemez/pasifleştiremez.
 *  - Restoranın son aktif owner'ı silinemez/pasifleştirilemez/manager'a düşürülemez.
 */
final class UserService
{
    use Transactional;

    public function __construct(private readonly Scope $scope)
    {
    }

    /**
     * @param array{name: string, email: string, password: string, role: string, is_active: int, branch_ids: list<int>} $input
     */
    public function create(array $input): int
    {
        $input  = $this->normalize($input);
        $errors = $this->validate($input, null, true);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->atomic(function () use ($input): int {
            $id = (int) $this->users()->insert([
                'name'          => $input['name'],
                'email'         => $input['email'],
                'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
                'role'          => $input['role'],
                'is_active'     => $input['is_active'],
            ]);

            $this->assignments()->replaceForUser($id, $input['role'] === 'manager' ? $input['branch_ids'] : []);

            return $id;
        });
    }

    /**
     * Parola boş gelirse değiştirilmez.
     *
     * @param array<string, mixed> $user mevcut kayıt (bu kapsamda yüklenmiş)
     * @param array{name: string, email: string, password: string, role: string, is_active: int, branch_ids: list<int>} $input
     */
    public function update(array $user, array $input): void
    {
        $this->assertManageable($user);

        $id     = (int) $user['id'];
        $input  = $this->normalize($input);
        $errors = $this->validate($input, $id, false);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $losesOwnership = $user['role'] === 'owner' && (int) $user['is_active'] === 1
            && ($input['role'] !== 'owner' || $input['is_active'] === 0);

        if ($losesOwnership) {
            $this->assertNotLastOwner($id);
        }

        $this->atomic(function () use ($id, $input): void {
            $fields = [
                'name'      => $input['name'],
                'email'     => $input['email'],
                'role'      => $input['role'],
                'is_active' => $input['is_active'],
            ];

            if ($input['password'] !== '') {
                $fields['password_hash']       = password_hash($input['password'], PASSWORD_DEFAULT);
                $fields['password_changed_at'] = date('Y-m-d H:i:s');
            }

            $this->users()->update($id, $fields);
            $this->assignments()->replaceForUser($id, $input['role'] === 'manager' ? $input['branch_ids'] : []);
        });
    }

    /**
     * @param array<string, mixed> $user
     */
    public function toggle(array $user, ?int $actorUserId = null): void
    {
        $this->assertManageable($user);
        $this->assertNotSelf($user, $actorUserId);

        $newFlag = (int) $user['is_active'] === 1 ? 0 : 1;

        if ($newFlag === 0 && $user['role'] === 'owner') {
            $this->assertNotLastOwner((int) $user['id']);
        }

        $this->users()->update((int) $user['id'], ['is_active' => $newFlag]);
    }

    /**
     * Soft delete. E-posta global unique olduğu için silinen kaydın e-postasına
     * `--deleted-{id}` eklenir; böylece aynı adres yeniden kullanılabilir.
     *
     * @param array<string, mixed> $user
     */
    public function delete(array $user, ?int $actorUserId = null): void
    {
        $this->assertManageable($user);
        $this->assertNotSelf($user, $actorUserId);

        $id = (int) $user['id'];

        if ($user['role'] === 'owner' && (int) $user['is_active'] === 1) {
            $this->assertNotLastOwner($id);
        }

        $this->atomic(function () use ($id, $user): void {
            $this->users()->update($id, [
                'email'     => pix_deleted_slug((string) $user['email'], $id, 190),
                'is_active' => 0,
            ]);
            $this->assignments()->replaceForUser($id, []);
            $this->users()->delete($id);
        });
    }

    /**
     * Kullanıcının kendi profili (ad + e-posta).
     *
     * @param array<string, mixed> $user
     */
    public function updateProfile(array $user, string $name, string $email): void
    {
        $name   = trim($name);
        $email  = mb_strtolower(trim($email));
        $errors = $this->validateIdentity($name, $email, (int) $user['id']);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->users()->update((int) $user['id'], ['name' => $name, 'email' => $email]);
    }

    /**
     * Kullanıcının kendi parolası. Diğer oturumlar password_changed_at ile düşer.
     *
     * @param array<string, mixed> $user
     */
    public function changeOwnPassword(array $user, string $current, string $new, string $confirm): void
    {
        $row = $this->users()->find((int) $user['id']);

        if ($row === null || ! password_verify($current, (string) $row['password_hash'])) {
            throw ValidationException::single('current_password', lang('App.user.passwordCurrent'));
        }

        if (! PixmenuRules::isStrongPassword($new)) {
            throw ValidationException::single('password', lang('App.user.passwordWeak'));
        }

        if ($new !== $confirm) {
            throw ValidationException::single('password_confirm', lang('App.user.passwordConfirm'));
        }

        $this->users()->update((int) $user['id'], [
            'password_hash'       => password_hash($new, PASSWORD_DEFAULT),
            'password_changed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ------------------------------------------------------------------

    private function users(): RestaurantUserModel
    {
        return $this->scope->model(RestaurantUserModel::class);
    }

    private function assignments(): RestaurantUserBranchModel
    {
        return $this->scope->model(RestaurantUserBranchModel::class);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{name: string, email: string, password: string, role: string, is_active: int, branch_ids: list<int>}
     */
    private function normalize(array $input): array
    {
        $branchIds = [];

        foreach ((array) ($input['branch_ids'] ?? []) as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $branchIds[] = (int) $id;
            } else {
                $branchIds[] = -1; // geçersiz değer → doğrulamada reddedilir
            }
        }

        $role = (string) ($input['role'] ?? '');

        // Owner (panel) yalnızca manager yönetebilir; gönderilen rol ne olursa olsun.
        if (! $this->scope->isAdmin()) {
            $role = 'manager';
        }

        return [
            'name'       => trim((string) ($input['name'] ?? '')),
            'email'      => mb_strtolower(trim((string) ($input['email'] ?? ''))),
            'password'   => (string) ($input['password'] ?? ''),
            'role'       => $role,
            'is_active'  => (int) ($input['is_active'] ?? 0) === 1 ? 1 : 0,
            'branch_ids' => array_values(array_unique($branchIds)),
        ];
    }

    /**
     * @param array{name: string, email: string, password: string, role: string, is_active: int, branch_ids: list<int>} $input
     *
     * @return array<string, string>
     */
    private function validate(array $input, ?int $exceptId, bool $passwordRequired): array
    {
        $errors = $this->validateIdentity($input['name'], $input['email'], $exceptId);

        if (! in_array($input['role'], ['owner', 'manager'], true)) {
            $errors['role'] = lang('App.user.roleInvalid');
        }

        if (($passwordRequired || $input['password'] !== '') && ! PixmenuRules::isStrongPassword($input['password'])) {
            $errors['password'] = lang('App.user.passwordWeak');
        }

        if ($input['role'] === 'manager') {
            if ($input['branch_ids'] === []) {
                $errors['branch_ids'] = lang('App.branch.atLeastOne');
            } else {
                // Her ID bu restorana ait (silinmemiş) bir şube olmalı; biri bile değilse reddedilir.
                $existing = $this->scope->model(BranchModel::class)->existingIdsAmong($input['branch_ids']);

                if (count($existing) !== count($input['branch_ids'])) {
                    $errors['branch_ids'] = lang('App.branch.invalid');
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateIdentity(string $name, string $email, ?int $exceptId): array
    {
        $errors = [];

        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = lang('App.user.nameRequired');
        }

        if ($email === '' || mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = lang('App.user.emailInvalid');
        } elseif ($this->users()->emailExistsGlobally($email, $exceptId)) {
            $errors['email'] = lang('App.user.emailTaken');
        }

        return $errors;
    }

    /**
     * Panelde (owner) yalnızca manager kayıtları yönetilebilir.
     *
     * @param array<string, mixed> $user
     */
    private function assertManageable(array $user): void
    {
        if (! $this->scope->isAdmin() && $user['role'] !== 'manager') {
            throw new BusinessRuleException(lang('App.user.cannotSelf'));
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    private function assertNotSelf(array $user, ?int $actorUserId): void
    {
        if ($actorUserId !== null && (int) $user['id'] === $actorUserId) {
            throw new BusinessRuleException(lang('App.user.cannotSelf'));
        }
    }

    private function assertNotLastOwner(int $userId): void
    {
        if ($this->users()->countActiveOwners($userId) === 0) {
            throw new BusinessRuleException(lang('App.user.lastOwner'));
        }
    }
}
