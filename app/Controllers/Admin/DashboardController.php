<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AllergenModel;
use App\Models\BranchModel;
use App\Models\LanguageModel;
use App\Models\RestaurantModel;
use App\Models\RestaurantUserModel;

final class DashboardController extends AdminBaseController
{
    /**
     * View: admin/dashboard — stats{restaurants, branches, users, languages, allergens}
     */
    public function index(): string
    {
        return $this->render('admin/dashboard', [
            'stats' => [
                'restaurants' => (new RestaurantModel())->countAllResults(),
                'branches'    => (new BranchModel())->withoutTenantScope()->countAllResults(),
                'users'       => (new RestaurantUserModel())->withoutTenantScope()->countAllResults(),
                'languages'   => (new LanguageModel())->countAllResults(),
                'allergens'   => (new AllergenModel())->countAllResults(),
            ],
        ]);
    }
}
