<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\RestaurantTranslationModel;
use App\Models\RestaurantUserBranchModel;
use App\Services\SiteService;

final class DashboardController extends PanelBaseController
{
    /**
     * View: panel/dashboard — restaurantName,
     * branches[{id, name, is_active, public_code, menu_url, category_count, product_count, managers[{name, initials}]}]
     */
    public function index(): string
    {
        $resolver = service('languageResolver');
        $chain    = $resolver->chain((int) $this->restaurant['default_language_id']);

        // Manager yalnızca atanmış şubelerini görür.
        $branches  = (new BranchModel())->accessible($this->tenant->allowedBranchIds());
        $branchIds = array_map('intval', array_column($branches, 'id'));
        $names     = $resolver->resolveMany(BranchTranslationModel::class, $branchIds, $chain);

        $categoryCounts = (new CategoryModel())->countsByBranch($branchIds);
        $productCounts  = (new ProductModel())->countsByBranch($branchIds);
        $managers       = (new RestaurantUserBranchModel())->activeManagersByBranch($branchIds);
        $menuBase       = (new SiteService())->canonicalBase($this->tenant->restaurantId(), (string) $this->restaurant['slug']);

        $restaurantName = $resolver->resolveOne(
            RestaurantTranslationModel::class,
            $this->tenant->restaurantId(),
            $chain,
        )['name'] ?? '';

        return $this->render('panel/dashboard', [
            'restaurantName' => $restaurantName,
            'branches'       => array_map(static fn (array $branch): array => [
                'id'             => (int) $branch['id'],
                'name'           => $names[(int) $branch['id']]['name'] ?? $branch['slug'],
                'is_active'      => (int) $branch['is_active'] === 1,
                'public_code'    => $branch['public_code'],
                'menu_url'       => SiteService::branchUrl($menuBase, $branch),
                'category_count' => $categoryCounts[(int) $branch['id']] ?? 0,
                'product_count'  => $productCounts[(int) $branch['id']] ?? 0,
                'managers'       => array_map(static fn (array $user): array => [
                    'name'     => $user['name'],
                    'initials' => pix_initials($user['name']),
                ], $managers[(int) $branch['id']] ?? []),
            ], $branches),
        ]);
    }
}
