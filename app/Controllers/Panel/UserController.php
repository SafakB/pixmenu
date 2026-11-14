<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Models\RestaurantUserBranchModel;
use App\Models\RestaurantUserModel;
use App\Services\Scope;
use App\Services\UserService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Owner: manager yönetimi. Owner yalnızca `manager` açabilir/düzenleyebilir;
 * kendisini ve diğer owner'ları silemez/pasifleştiremez (onlar için yanıt 404).
 */
final class UserController extends PanelBaseController
{
    /**
     * View: panel/users/index — users[{id, name, email, role, is_active, branch_ids, editable}]
     */
    public function index(): string
    {
        $this->requireOwner();

        $users     = (new RestaurantUserModel())->ordered();
        $branchMap = (new RestaurantUserBranchModel())->branchIdsForUsers(array_map('intval', array_column($users, 'id')));

        return $this->render('panel/users/index', [
            'users' => array_map(static fn (array $user): array => [
                'id'         => (int) $user['id'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'is_active'  => (int) $user['is_active'] === 1,
                'branch_ids' => $branchMap[(int) $user['id']] ?? [],
                'editable'   => $user['role'] === 'manager',
            ], $users),
        ]);
    }

    /**
     * View: panel/users/form — user(null), branches[{id, name}], assigned[], action
     */
    public function new(): string
    {
        $this->requireOwner();

        return $this->form(null);
    }

    public function create(): ResponseInterface
    {
        $this->requireOwner();

        return $this->handle(function (): ResponseInterface {
            $input = $this->input();
            $id    = (new UserService(Scope::tenant()))->create($input);
            service('activity')->detail(['id' => $id, 'email' => trim($input['email']), 'role' => $input['role']]);

            return $this->done('panel/users', lang('App.created'));
        });
    }

    /**
     * View: panel/users/form — user, branches, assigned, action
     */
    public function edit(int $userId): string
    {
        $this->requireOwner();

        return $this->form($this->requireManager($userId));
    }

    public function update(int $userId): ResponseInterface
    {
        $this->requireOwner();
        $user = $this->requireManager($userId);

        return $this->handle(function () use ($user): ResponseInterface {
            $input = $this->input();
            (new UserService(Scope::tenant()))->update($user, $input);
            service('activity')->detail([
                'email'            => trim($input['email']),
                'role'             => $input['role'],
                'password_changed' => $input['password'] !== '' ? 'evet' : 'hayır',
            ]);

            return $this->done('panel/users', lang('App.saved'));
        });
    }

    public function toggle(int $userId): ResponseInterface
    {
        $this->requireOwner();
        $user = $this->requireManager($userId);

        return $this->handle(function () use ($user): ResponseInterface {
            (new UserService(Scope::tenant()))->toggle($user, $this->tenant->userId());

            return $this->done('panel/users', lang('App.statusChanged'));
        });
    }

    public function delete(int $userId): ResponseInterface
    {
        $this->requireOwner();
        $user = $this->requireManager($userId);

        return $this->handle(function () use ($user): ResponseInterface {
            (new UserService(Scope::tenant()))->delete($user, $this->tenant->userId());
            service('activity')->detail(['email' => $user['email'], 'role' => $user['role']]);

            return $this->done('panel/users', lang('App.deleted'));
        });
    }

    /**
     * Kullanıcı bu tenant'a ait VE manager olmalı. Başka tenant'ın kullanıcısı,
     * owner kayıtları ve var olmayan ID — hepsi aynı 404.
     *
     * @return array<string, mixed>
     */
    private function requireManager(int $userId): array
    {
        $user = (new RestaurantUserModel())->find($userId);

        if ($user === null || $user['role'] !== 'manager') {
            $this->notFound();
        }

        return $user;
    }

    /**
     * Alanlar tek tek seçilir. `role` ve `restaurant_id` request'ten OKUNMAZ:
     * panelden açılan kullanıcı her zaman bu tenant'ın manager'ıdır.
     *
     * @return array<string, mixed>
     */
    private function input(): array
    {
        return [
            'name'       => (string) $this->request->getPost('name'),
            'email'      => (string) $this->request->getPost('email'),
            'password'   => (string) $this->request->getPost('password'),
            'role'       => 'manager',
            'is_active'  => $this->flag('is_active'),
            'branch_ids' => (array) ($this->request->getPost('branch_ids') ?? []),
        ];
    }

    /**
     * @param array<string, mixed>|null $user
     */
    private function form(?array $user): string
    {
        $branches = (new BranchModel())->accessible(null);
        $names    = service('languageResolver')->resolveMany(
            BranchTranslationModel::class,
            array_map('intval', array_column($branches, 'id')),
            [(int) $this->restaurant['default_language_id']],
        );

        if ($user !== null) {
            unset($user['password_hash']);
        }

        return $this->render('panel/users/form', [
            'user'     => $user,
            'branches' => array_map(static fn (array $branch): array => [
                'id'   => (int) $branch['id'],
                'name' => $names[(int) $branch['id']]['name'] ?? $branch['slug'],
            ], $branches),
            'assigned' => $user === null ? [] : (new RestaurantUserBranchModel())->branchIdsForUser((int) $user['id']),
            'action'   => $user === null ? 'panel/users' : 'panel/users/' . $user['id'],
        ]);
    }
}
