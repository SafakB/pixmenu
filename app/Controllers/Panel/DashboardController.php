<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Models\RestaurantTranslationModel;

final class DashboardController extends PanelBaseController
{
    /**
     * View: panel/dashboard — restaurantName, branches[{id, name, is_active, public_code}]
     */
    public function index(): string
    {
        $resolver = service('languageResolver');
        $chain    = $resolver->chain((int) $this->restaurant['default_language_id']);

        // Manager yalnızca atanmış şubelerini görür.
        $branches = (new BranchModel())->accessible($this->tenant->allowedBranchIds());
        $names    = $resolver->resolveMany(BranchTranslationModel::class, array_column($branches, 'id'), $chain);

        $restaurantName = $resolver->resolveOne(
            RestaurantTranslationModel::class,
            $this->tenant->restaurantId(),
            $chain,
        )['name'] ?? '';

        return $this->render('panel/dashboard', [
            'restaurantName' => $restaurantName,
            'branches'       => array_map(static fn (array $branch): array => [
                'id'          => (int) $branch['id'],
                'name'        => $names[(int) $branch['id']]['name'] ?? $branch['slug'],
                'is_active'   => (int) $branch['is_active'] === 1,
                'public_code' => $branch['public_code'],
            ], $branches),
        ]);
    }
}
