<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\LanguageModel;
use App\Models\RestaurantLanguageModel;
use App\Services\RestaurantService;
use CodeIgniter\HTTP\ResponseInterface;

final class RestaurantLanguageController extends AdminBaseController
{
    /**
     * View: admin/restaurants/languages — restaurant (satır + name), languages[{id, code, native_name, is_active, selected, is_default}]
     */
    public function index(int $restaurantId): string
    {
        $restaurant = $this->requireRestaurant($restaurantId);
        $selected   = (new RestaurantLanguageModel())->forRestaurant($restaurantId)->languageIds(false);

        return $this->render('admin/restaurants/languages', [
            'restaurant' => $restaurant + ['name' => $this->restaurantName($restaurant)],
            'languages'  => array_map(static fn (array $language): array => [
                'id'          => (int) $language['id'],
                'code'        => $language['code'],
                'native_name' => $language['native_name'],
                'is_active'   => (int) $language['is_active'] === 1,
                'selected'    => in_array((int) $language['id'], $selected, true),
                'is_default'  => (int) $language['id'] === (int) $restaurant['default_language_id'],
            ], (new LanguageModel())->ordered()),
        ]);
    }

    public function sync(int $restaurantId): ResponseInterface
    {
        $restaurant = $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurant, $restaurantId): ResponseInterface {
            (new RestaurantService())->syncLanguages($restaurant, $this->intList('language_ids'));

            return $this->done('admin/restaurants/' . $restaurantId . '/languages', lang('App.language.synced'));
        });
    }
}
