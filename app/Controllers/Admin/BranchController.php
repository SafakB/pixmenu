<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Services\BranchService;
use App\Services\Scope;
use App\Services\TranslationService;
use CodeIgniter\HTTP\ResponseInterface;

final class BranchController extends AdminBaseController
{
    /**
     * View: admin/branches/index — restaurant, branches[{id, slug, name, public_code, menu_url, is_active, sort_order}]
     */
    public function index(int $restaurantId): string
    {
        $restaurant = $this->requireRestaurant($restaurantId);
        $branches   = (new BranchModel())->forRestaurant($restaurantId)->accessible(null);
        $names      = service('languageResolver')->resolveMany(
            BranchTranslationModel::class,
            array_map('intval', array_column($branches, 'id')),
            [(int) $restaurant['default_language_id']],
        );

        return $this->render('admin/branches/index', [
            'restaurant' => $restaurant + ['name' => $this->restaurantName($restaurant)],
            'branches'   => array_map(static fn (array $branch): array => [
                'id'          => (int) $branch['id'],
                'slug'        => $branch['slug'],
                'name'        => $names[(int) $branch['id']]['name'] ?? $branch['slug'],
                'public_code' => $branch['public_code'],
                'menu_url'    => site_url('m/' . $branch['public_code']),
                'is_active'   => (int) $branch['is_active'] === 1,
                'sort_order'  => (int) $branch['sort_order'],
            ], $branches),
        ]);
    }

    /**
     * View: admin/branches/form — restaurant, branch(null), languages, translations, action
     */
    public function new(int $restaurantId): string
    {
        return $this->form($this->requireRestaurant($restaurantId), null);
    }

    public function create(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurantId): ResponseInterface {
            (new BranchService(Scope::admin($restaurantId)))->create($this->input(), $this->translationsInput());

            return $this->done('admin/restaurants/' . $restaurantId . '/branches', lang('App.created'));
        });
    }

    /**
     * View: admin/branches/form — restaurant, branch, languages, translations, action
     */
    public function edit(int $restaurantId, int $branchId): string
    {
        return $this->form($this->requireRestaurant($restaurantId), $this->requireBranch($restaurantId, $branchId));
    }

    public function update(int $restaurantId, int $branchId): ResponseInterface
    {
        $branch = $this->requireBranch($restaurantId, $branchId);

        return $this->handle(function () use ($restaurantId, $branch): ResponseInterface {
            (new BranchService(Scope::admin($restaurantId)))->update($branch, $this->input(), $this->translationsInput());

            return $this->done('admin/restaurants/' . $restaurantId . '/branches', lang('App.saved'));
        });
    }

    public function toggle(int $restaurantId, int $branchId): ResponseInterface
    {
        (new BranchService(Scope::admin($restaurantId)))->toggle($this->requireBranch($restaurantId, $branchId));

        return $this->done('admin/restaurants/' . $restaurantId . '/branches', lang('App.statusChanged'));
    }

    public function delete(int $restaurantId, int $branchId): ResponseInterface
    {
        (new BranchService(Scope::admin($restaurantId)))->delete($this->requireBranch($restaurantId, $branchId));

        return $this->done('admin/restaurants/' . $restaurantId . '/branches', lang('App.deleted'));
    }

    /**
     * Eski QR kodlarını geçersiz kılar (UI'da uyarı gösterilmeli).
     */
    public function regenerateCode(int $restaurantId, int $branchId): ResponseInterface
    {
        $code = (new BranchService(Scope::admin($restaurantId)))->regenerateCode($this->requireBranch($restaurantId, $branchId));

        return $this->done('admin/restaurants/' . $restaurantId . '/branches', lang('App.branch.codeRegenerated'), ['public_code' => $code]);
    }

    /**
     * İç içe ID doğrulaması: şube URL'deki restorana ait olmalı, değilse 404.
     *
     * @return array<string, mixed>
     */
    private function requireBranch(int $restaurantId, int $branchId): array
    {
        $this->requireRestaurant($restaurantId);

        return (new BranchModel())->forRestaurant($restaurantId)->find($branchId) ?? $this->notFound();
    }

    /**
     * @return array{slug: string, default_language_id: int, phone: string, is_active: int, sort_order: int}
     */
    private function input(): array
    {
        return [
            'slug'                => trim((string) $this->request->getPost('slug')),
            'default_language_id' => (int) $this->request->getPost('default_language_id'),
            'phone'               => trim((string) $this->request->getPost('phone')),
            'is_active'           => $this->flag('is_active'),
            'sort_order'          => $this->sortOrder(),
        ];
    }

    /**
     * @param array<string, mixed>      $restaurant
     * @param array<string, mixed>|null $branch
     */
    private function form(array $restaurant, ?array $branch): string
    {
        $restaurantId = (int) $restaurant['id'];
        $translations = new TranslationService();
        $languages    = (new BranchService(Scope::admin($restaurantId)))->languages();

        return $this->render('admin/branches/form', [
            'restaurant'   => $restaurant + ['name' => $this->restaurantName($restaurant)],
            'branch'       => $branch,
            // is_default: zorunlu dil = RESTORANIN varsayılan dili
            'languages'    => $translations->formLanguages($languages, (int) $restaurant['default_language_id']),
            'translations' => $branch === null ? [] : $translations->load(BranchTranslationModel::class, (int) $branch['id']),
            'action'       => 'admin/restaurants/' . $restaurantId . '/branches' . ($branch === null ? '' : '/' . $branch['id']),
        ]);
    }
}
