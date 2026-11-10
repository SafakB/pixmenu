<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Models\RestaurantLanguageModel;
use App\Models\RestaurantTranslationModel;
use App\Services\RestaurantService;
use App\Services\Scope;
use App\Services\TranslationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Owner: kendi restoran bilgisi. Düzenlenebilen alanlar YALNIZCA ad/açıklama
 * çevirileri ve logodur; slug, para birimi, diller ve varsayılan dil admin'e aittir.
 */
final class RestaurantController extends PanelBaseController
{
    /**
     * View: panel/restaurant — restaurant{slug, currency_code}, logo_url, languages, translations
     */
    public function edit(): string
    {
        $this->requireOwner();

        $service = new TranslationService();

        return $this->render('panel/restaurant', [
            'restaurant' => [
                'slug'          => $this->restaurant['slug'],
                'currency_code' => $this->restaurant['currency_code'],
            ],
            'logo_url'     => upload_url($this->restaurant['logo_path']),
            'languages'    => $service->formLanguages(
                (new RestaurantLanguageModel())->languages(true),
                (int) $this->restaurant['default_language_id'],
            ),
            // Ebeveyn ID'si request'ten değil TenantContext'ten gelir.
            'translations' => $service->load(RestaurantTranslationModel::class, $this->tenant->restaurantId()),
        ]);
    }

    public function update(): ResponseInterface
    {
        $this->requireOwner();

        return $this->handle(function (): ResponseInterface {
            $this->validateOrFail($this->imageRules('logo', 'logo'));

            (new RestaurantService())->updateProfile(
                $this->restaurant,
                Scope::tenant(),
                $this->translationsInput(),
                $this->uploaded('logo'),
            );

            return $this->done('panel/restaurant', lang('App.saved'));
        });
    }

    public function deleteLogo(): ResponseInterface
    {
        $this->requireOwner();

        // Silinecek dosyanın yolu DB kaydından gelir; request'ten yol okunmaz.
        (new RestaurantService())->deleteLogo($this->restaurant);

        return $this->done('panel/restaurant', lang('App.restaurant.logoDeleted'));
    }
}
