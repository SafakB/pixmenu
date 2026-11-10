<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BranchModel;
use App\Models\LanguageModel;
use App\Models\RestaurantLanguageModel;
use App\Models\RestaurantModel;
use App\Models\RestaurantTranslationModel;
use App\Models\RestaurantUserModel;
use App\Services\RestaurantService;
use App\Services\TranslationService;
use CodeIgniter\HTTP\ResponseInterface;

final class RestaurantController extends AdminBaseController
{
    /**
     * View: admin/restaurants/index — restaurants[{id, slug, name, currency_code, is_active}]
     */
    public function index(): string
    {
        $restaurants = (new RestaurantModel())->orderBy('id', 'DESC')->findAll();
        $chains      = [];

        foreach ($restaurants as $restaurant) {
            $chains[(int) $restaurant['id']] = [(int) $restaurant['default_language_id']];
        }

        $names = service('languageResolver')->resolveManyWithChains(RestaurantTranslationModel::class, $chains);

        return $this->render('admin/restaurants/index', [
            'restaurants' => array_map(static fn (array $restaurant): array => [
                'id'            => (int) $restaurant['id'],
                'slug'          => $restaurant['slug'],
                'name'          => $names[(int) $restaurant['id']]['name'] ?? $restaurant['slug'],
                'currency_code' => $restaurant['currency_code'],
                'is_active'     => (int) $restaurant['is_active'] === 1,
            ], $restaurants),
        ]);
    }

    /**
     * View: admin/restaurants/new — languages[] (tüm aktif diller; çeviri alanları ve dil seçimi için)
     */
    public function new(): string
    {
        $languages = (new LanguageModel())->ordered(true);

        return $this->render('admin/restaurants/new', [
            'allLanguages' => $languages,
            'languages'    => (new TranslationService())->formLanguages($languages, 0),
        ]);
    }

    public function create(): ResponseInterface
    {
        return $this->handle(function (): ResponseInterface {
            $this->validateOrFail([
                'default_language_id' => 'required|is_natural_no_zero',
                'currency_code'       => 'required|exact_length[3]',
            ] + $this->imageRules('logo', 'logo'));

            $ownerEmail = trim((string) $this->request->getPost('owner_email'));
            $owner      = $ownerEmail === '' ? null : [
                'name'     => (string) $this->request->getPost('owner_name'),
                'email'    => $ownerEmail,
                'password' => (string) $this->request->getPost('owner_password'),
            ];

            $id = (new RestaurantService())->create(
                [
                    'slug'                => trim((string) $this->request->getPost('slug')),
                    'default_language_id' => (int) $this->request->getPost('default_language_id'),
                    'currency_code'       => strtoupper(trim((string) $this->request->getPost('currency_code'))),
                    'is_active'           => $this->flag('is_active'),
                    'language_ids'        => $this->intList('language_ids'),
                ],
                $this->translationsInput(),
                $this->uploaded('logo'),
                $owner,
            );

            return $this->done('admin/restaurants/' . $id, lang('App.created'), ['id' => $id]);
        });
    }

    /**
     * View: admin/restaurants/show — restaurant{id, slug, name, currency_code, is_active, logo_url},
     *       counts{branches, users, languages}
     */
    public function show(int $id): string
    {
        $restaurant = $this->requireRestaurant($id);
        $name       = service('languageResolver')->resolveOne(
            RestaurantTranslationModel::class,
            $id,
            [(int) $restaurant['default_language_id']],
        )['name'] ?? $restaurant['slug'];

        return $this->render('admin/restaurants/show', [
            'restaurant' => [
                'id'            => $id,
                'slug'          => $restaurant['slug'],
                'name'          => $name,
                'currency_code' => $restaurant['currency_code'],
                'is_active'     => (int) $restaurant['is_active'] === 1,
                'logo_url'      => upload_url($restaurant['logo_path']),
            ],
            'counts' => [
                'branches'  => (new BranchModel())->forRestaurant($id)->countAllResults(),
                'users'     => (new RestaurantUserModel())->forRestaurant($id)->countAllResults(),
                'languages' => count((new RestaurantLanguageModel())->forRestaurant($id)->languages(false)),
            ],
        ]);
    }

    /**
     * View: admin/restaurants/edit — restaurant, logo_url, languages, translations
     */
    public function edit(int $id): string
    {
        $restaurant = $this->requireRestaurant($id);
        $service    = new TranslationService();

        return $this->render('admin/restaurants/edit', [
            'restaurant'   => $restaurant,
            'logo_url'     => upload_url($restaurant['logo_path']),
            'languages'    => $service->formLanguages(
                (new RestaurantLanguageModel())->forRestaurant($id)->languages(true),
                (int) $restaurant['default_language_id'],
            ),
            'translations' => $service->load(RestaurantTranslationModel::class, $id),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $restaurant = $this->requireRestaurant($id);

        return $this->handle(function () use ($restaurant, $id): ResponseInterface {
            $this->validateOrFail([
                'default_language_id' => 'required|is_natural_no_zero',
                'currency_code'       => 'required|exact_length[3]',
            ] + $this->imageRules('logo', 'logo'));

            (new RestaurantService())->update(
                $restaurant,
                [
                    'slug'                => trim((string) $this->request->getPost('slug')),
                    'default_language_id' => (int) $this->request->getPost('default_language_id'),
                    'currency_code'       => strtoupper(trim((string) $this->request->getPost('currency_code'))),
                    'is_active'           => $this->flag('is_active'),
                ],
                $this->translationsInput(),
                $this->uploaded('logo'),
            );

            return $this->done('admin/restaurants/' . $id, lang('App.saved'));
        });
    }

    public function toggle(int $id): ResponseInterface
    {
        (new RestaurantService())->toggle($this->requireRestaurant($id));

        return $this->done('admin/restaurants', lang('App.statusChanged'));
    }

    public function delete(int $id): ResponseInterface
    {
        (new RestaurantService())->delete($this->requireRestaurant($id));

        return $this->done('admin/restaurants', lang('App.deleted'));
    }
}
