<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Models\RestaurantUserBranchModel;
use App\Models\RestaurantUserModel;
use App\Services\Scope;
use App\Services\UserService;
use CodeIgniter\HTTP\ResponseInterface;

final class RestaurantUserController extends AdminBaseController
{
    /**
     * View: admin/users/index — restaurant, users[{id, name, email, role, is_active, branch_ids}]
     */
    public function index(int $restaurantId): string
    {
        $restaurant = $this->requireRestaurant($restaurantId);
        $users      = (new RestaurantUserModel())->forRestaurant($restaurantId)->ordered();
        $branchMap  = (new RestaurantUserBranchModel())->forRestaurant($restaurantId)
            ->branchIdsForUsers(array_map('intval', array_column($users, 'id')));

        return $this->render('admin/users/index', [
            'restaurant' => $restaurant + ['name' => $this->restaurantName($restaurant)],
            'users'      => array_map(static fn (array $user): array => [
                'id'         => (int) $user['id'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'is_active'  => (int) $user['is_active'] === 1,
                'branch_ids' => $branchMap[(int) $user['id']] ?? [],
            ], $users),
        ]);
    }

    /**
     * View: admin/users/form — restaurant, user(null), branches[{id, name}], assigned[], action
     */
    public function new(int $restaurantId): string
    {
        return $this->form($this->requireRestaurant($restaurantId), null);
    }

    public function create(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurantId): ResponseInterface {
            (new UserService(Scope::admin($restaurantId)))->create($this->input());

            return $this->done('admin/restaurants/' . $restaurantId . '/users', lang('App.created'));
        });
    }

    /**
     * View: admin/users/form — restaurant, user, branches, assigned, action
     */
    public function edit(int $restaurantId, int $userId): string
    {
        return $this->form($this->requireRestaurant($restaurantId), $this->requireUser($restaurantId, $userId));
    }

    public function update(int $restaurantId, int $userId): ResponseInterface
    {
        $user = $this->requireUser($restaurantId, $userId);

        return $this->handle(function () use ($restaurantId, $user): ResponseInterface {
            (new UserService(Scope::admin($restaurantId)))->update($user, $this->input());

            return $this->done('admin/restaurants/' . $restaurantId . '/users', lang('App.saved'));
        });
    }

    public function toggle(int $restaurantId, int $userId): ResponseInterface
    {
        $user = $this->requireUser($restaurantId, $userId);

        return $this->handle(function () use ($restaurantId, $user): ResponseInterface {
            (new UserService(Scope::admin($restaurantId)))->toggle($user);

            return $this->done('admin/restaurants/' . $restaurantId . '/users', lang('App.statusChanged'));
        });
    }

    public function delete(int $restaurantId, int $userId): ResponseInterface
    {
        $user = $this->requireUser($restaurantId, $userId);

        return $this->handle(function () use ($restaurantId, $user): ResponseInterface {
            (new UserService(Scope::admin($restaurantId)))->delete($user);

            return $this->done('admin/restaurants/' . $restaurantId . '/users', lang('App.deleted'));
        });
    }

    /**
     * İç içe ID doğrulaması: kullanıcı URL'deki restorana ait olmalı, değilse 404.
     *
     * @return array<string, mixed>
     */
    private function requireUser(int $restaurantId, int $userId): array
    {
        $this->requireRestaurant($restaurantId);

        return (new RestaurantUserModel())->forRestaurant($restaurantId)->find($userId) ?? $this->notFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        return [
            'name'       => (string) $this->request->getPost('name'),
            'email'      => (string) $this->request->getPost('email'),
            'password'   => (string) $this->request->getPost('password'),
            'role'       => (string) $this->request->getPost('role'),
            'is_active'  => $this->flag('is_active'),
            'branch_ids' => (array) ($this->request->getPost('branch_ids') ?? []),
        ];
    }

    /**
     * @param array<string, mixed>      $restaurant
     * @param array<string, mixed>|null $user
     */
    private function form(array $restaurant, ?array $user): string
    {
        $restaurantId = (int) $restaurant['id'];
        $branches     = (new BranchModel())->forRestaurant($restaurantId)->accessible(null);
        $names        = service('languageResolver')->resolveMany(
            BranchTranslationModel::class,
            array_map('intval', array_column($branches, 'id')),
            [(int) $restaurant['default_language_id']],
        );

        if ($user !== null) {
            unset($user['password_hash']);
        }

        return $this->render('admin/users/form', [
            'restaurant' => $restaurant + ['name' => $this->restaurantName($restaurant)],
            'user'       => $user,
            'branches'   => array_map(static fn (array $branch): array => [
                'id'   => (int) $branch['id'],
                'name' => $names[(int) $branch['id']]['name'] ?? $branch['slug'],
            ], $branches),
            'assigned' => $user === null
                ? []
                : (new RestaurantUserBranchModel())->forRestaurant($restaurantId)->branchIdsForUser((int) $user['id']),
            'action' => 'admin/restaurants/' . $restaurantId . '/users' . ($user === null ? '' : '/' . $user['id']),
        ]);
    }
}
