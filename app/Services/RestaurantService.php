<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\BranchModel;
use App\Models\CurrencyModel;
use App\Models\LanguageModel;
use App\Models\PlanModel;
use App\Models\ProductModel;
use App\Models\RestaurantLanguageModel;
use App\Models\RestaurantModel;
use App\Models\RestaurantTranslationModel;
use App\Services\Concerns\Transactional;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Restoran yönetimi — spec §8.5, §10.1.
 *
 * create/update/toggle/delete/syncLanguages YALNIZCA admin içindir.
 * updateProfile/deleteLogo hem admin hem owner (kendi restoranı) tarafından kullanılır.
 */
final class RestaurantService
{
    use Transactional;

    public function __construct(private readonly TranslationService $translations = new TranslationService())
    {
    }

    /**
     * Tek transaction: restaurants + restaurant_translations + restaurant_languages (+ ilk owner)
     * + abonelik (plan_id verilmezse config'teki varsayılan deneme paketi).
     *
     * @param array{slug: string, default_language_id: int, currency_code: string, is_active: int, language_ids: list<int>, plan_id?: int|null, billing_cycle?: string} $data
     * @param array<string, array<string, string>>                                                                       $translations
     * @param array{name: string, email: string, password: string}|null                                                  $owner
     */
    public function create(array $data, array $translations, ?UploadedFile $logo, ?array $owner): int
    {
        $languageIds = array_values(array_unique($data['language_ids']));
        $errors      = [];

        if ($languageIds === []) {
            $errors['language_ids'] = lang('App.language.atLeastOne');
        }

        $languages = $languageIds === []
            ? []
            : (new LanguageModel())->whereIn('id', $languageIds)->where('is_active', 1)->findAll();
        $codesById = array_column($languages, 'code', 'id');

        if (count($codesById) !== count($languageIds)) {
            $errors['language_ids'] = lang('App.language.invalidSelection');
        }

        // default_language_id seçilen diller arasında olmak zorunda.
        if (! in_array($data['default_language_id'], $languageIds, true) || ! isset($codesById[$data['default_language_id']])) {
            $errors['default_language_id'] = lang('App.language.defaultMustBeIn');
        }

        $errors += $this->validateCommon($data, null, null);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $codes        = array_map('strval', array_values($codesById));
        $requiredCode = (string) $codesById[$data['default_language_id']];
        $errors       = $this->translations->validate(RestaurantTranslationModel::class, $translations, $codes, $requiredCode);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $images = service('images');
        $stored = null;

        return $this->atomic(function () use ($data, $translations, $languageIds, $codes, $requiredCode, $logo, $owner, $images, &$stored): int {
            $model = new RestaurantModel();
            $id    = (int) $model->insert([
                'slug'                => $this->resolveSlug($data['slug'], $translations[$requiredCode]['name'] ?? ''),
                'default_language_id' => $data['default_language_id'],
                'currency_code'       => $data['currency_code'],
                'is_active'           => $data['is_active'],
            ]);

            // Varsayılan dil listede ilk sırada olsun.
            $ordered = array_values(array_unique(array_merge([$data['default_language_id']], $languageIds)));
            (new RestaurantLanguageModel())->forRestaurant($id)->replaceAll($ordered);

            $this->translations->sync(RestaurantTranslationModel::class, 'restaurant_id', $id, $translations, $codes, $requiredCode);

            if ($logo !== null) {
                $stored = $images->store($logo, 'logo', $id, 'logo');
                $model->update($id, ['logo_path' => $stored['path']]);
            }

            if ($owner !== null) {
                (new UserService(Scope::admin($id)))->create($owner + ['role' => 'owner', 'is_active' => 1, 'branch_ids' => []]);
            }

            (new SubscriptionService())->start($id, $this->resolvePlanId($data['plan_id'] ?? null), (string) ($data['billing_cycle'] ?? 'monthly'));

            return $id;
        }, static function () use ($images, &$stored): void {
            $images->delete($stored['path'] ?? null);
        });
    }

    /**
     * Admin güncellemesi: slug, varsayılan dil, para birimi, durum, çeviriler, logo.
     *
     * @param array<string, mixed>                                                               $restaurant   mevcut kayıt
     * @param array{slug: string, default_language_id: int, currency_code: string, is_active: int} $data
     * @param array<string, array<string, string>>                                               $translations
     */
    public function update(array $restaurant, array $data, array $translations, ?UploadedFile $logo): void
    {
        $id        = (int) $restaurant['id'];
        $languages = (new RestaurantLanguageModel())->forRestaurant($id)->languages(true);
        $codesById = array_column($languages, 'code', 'id');
        $errors    = [];

        // Varsayılan dil restoranın (aktif) dilleri içinde olmalı.
        if (! isset($codesById[$data['default_language_id']])) {
            $errors['default_language_id'] = lang('App.language.notInRestaurant');
        }

        $errors += $this->validateCommon($data, $id, (string) $restaurant['currency_code']);

        // Fiyatlar para birimiyle birlikte dönüştürülmediği için ürünü olan restoranda değiştirilemez.
        if (! isset($errors['currency_code']) && $data['currency_code'] !== $restaurant['currency_code'] && $this->hasProducts($id)) {
            $errors['currency_code'] = lang('App.restaurant.currencyLocked');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $codes        = array_map('strval', array_values($codesById));
        $requiredCode = (string) $codesById[$data['default_language_id']];

        $this->writeProfile($restaurant, $translations, $codes, $requiredCode, $logo, [
            'slug'                => $data['slug'] === '' ? (string) $restaurant['slug'] : $data['slug'],
            'default_language_id' => $data['default_language_id'],
            'currency_code'       => $data['currency_code'],
            'is_active'           => $data['is_active'],
        ]);
    }

    /**
     * Owner güncellemesi (panel): YALNIZCA çeviriler (ad, açıklama) ve logo.
     * Slug, para birimi, diller ve varsayılan dil admin'e aittir.
     *
     * @param array<string, mixed>                 $restaurant   service('tenant') restoranı
     * @param array<string, array<string, string>> $translations
     */
    public function updateProfile(array $restaurant, Scope $scope, array $translations, ?UploadedFile $logo): void
    {
        $languages    = $scope->model(RestaurantLanguageModel::class)->languages(true);
        $codesById    = array_column($languages, 'code', 'id');
        $codes        = array_map('strval', array_values($codesById));
        $requiredCode = (string) ($codesById[(int) $restaurant['default_language_id']] ?? ($codes[0] ?? ''));

        $this->writeProfile($restaurant, $translations, $codes, $requiredCode, $logo, []);
    }

    /**
     * @param array<string, mixed> $restaurant
     */
    public function deleteLogo(array $restaurant): void
    {
        (new RestaurantModel())->update((int) $restaurant['id'], ['logo_path' => null]);
        service('images')->delete($restaurant['logo_path']);

        (new MenuService())->invalidateRestaurant((int) $restaurant['id']);
    }

    /**
     * Pasifleştirilen restoranın kullanıcılarının oturumu sonraki istekte düşer,
     * menüleri 404 döner.
     *
     * @param array<string, mixed> $restaurant
     */
    public function toggle(array $restaurant): void
    {
        (new RestaurantModel())->update((int) $restaurant['id'], [
            'is_active' => (int) $restaurant['is_active'] === 1 ? 0 : 1,
        ]);

        (new MenuService())->invalidateRestaurant((int) $restaurant['id']);
    }

    /**
     * Soft delete; slug'a `--deleted-{id}` eklenir. Dosyalar kalır.
     *
     * @param array<string, mixed> $restaurant
     */
    public function delete(array $restaurant): void
    {
        $id    = (int) $restaurant['id'];
        $model = new RestaurantModel();

        $this->atomic(static function () use ($id, $restaurant, $model): void {
            $model->update($id, ['slug' => pix_deleted_slug((string) $restaurant['slug'], $id)]);
            $model->delete($id);
        });

        (new MenuService())->invalidateRestaurant($id);
    }

    /**
     * Restoranın dil listesini eşitler (spec §8.5):
     *  - Çıkarılan dil restoranın veya (silinmemiş) bir şubesinin varsayılan diliyse reddedilir.
     *  - Çıkarılan dilin çevirileri SİLİNMEZ; dil geri eklenirse veri geri gelir.
     *
     * @param array<string, mixed> $restaurant
     * @param list<int>            $languageIds seçilen diller (sıralı)
     */
    public function syncLanguages(array $restaurant, array $languageIds): void
    {
        $id          = (int) $restaurant['id'];
        $languageIds = array_values(array_unique($languageIds));

        if ($languageIds === []) {
            throw ValidationException::single('language_ids', lang('App.language.atLeastOne'));
        }

        $rlModel = (new RestaurantLanguageModel())->forRestaurant($id);
        $current = $rlModel->languageIds(false);

        // Yeni eklenenler global olarak var ve aktif olmalı; mevcutlar korunabilir.
        $added = array_values(array_diff($languageIds, $current));
        $known = (new LanguageModel())->select('id, code, is_active')->whereIn('id', $languageIds)->findAll();
        $byId  = array_column($known, null, 'id');

        if (count($byId) !== count($languageIds)) {
            throw ValidationException::single('language_ids', lang('App.language.invalidSelection'));
        }

        foreach ($added as $languageId) {
            if ((int) $byId[$languageId]['is_active'] !== 1) {
                throw ValidationException::single('language_ids', lang('App.language.invalidSelection'));
            }
        }

        $removed = array_values(array_diff($current, $languageIds));

        if ($removed !== []) {
            $codes = array_column((new LanguageModel())->select('id, code')->whereIn('id', $removed)->findAll(), 'code', 'id');

            foreach ($removed as $languageId) {
                $isRestaurantDefault = (int) $restaurant['default_language_id'] === $languageId;
                $isBranchDefault     = (new BranchModel())->forRestaurant($id)->countUsingDefaultLanguage($languageId) > 0;

                if ($isRestaurantDefault || $isBranchDefault) {
                    throw new BusinessRuleException(lang('App.language.removeIsDefault', [(string) ($codes[$languageId] ?? $languageId)]));
                }
            }
        }

        $this->atomic(static fn () => $rlModel->replaceAll($languageIds));

        (new MenuService())->invalidateRestaurant($id);
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed>                 $restaurant
     * @param array<string, array<string, string>> $translations
     * @param list<string>                         $codes
     * @param array<string, mixed>                 $fields       restaurants tablosunda güncellenecek ek alanlar
     */
    private function writeProfile(array $restaurant, array $translations, array $codes, string $requiredCode, ?UploadedFile $logo, array $fields): void
    {
        $id     = (int) $restaurant['id'];
        $errors = $this->translations->validate(RestaurantTranslationModel::class, $translations, $codes, $requiredCode);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $images = service('images');
        $stored = $logo === null ? null : $images->store($logo, 'logo', $id, 'logo');

        if ($stored !== null) {
            $fields['logo_path'] = $stored['path'];
        }

        $this->atomic(function () use ($id, $fields, $translations, $codes, $requiredCode): void {
            if ($fields !== []) {
                (new RestaurantModel())->update($id, $fields);
            }

            $this->translations->sync(RestaurantTranslationModel::class, 'restaurant_id', $id, $translations, $codes, $requiredCode);
        }, static fn () => $images->delete($stored['path'] ?? null));

        // Commit sonrası eski logo silinir (yol DB kaydından).
        if ($stored !== null) {
            $images->delete($restaurant['logo_path']);
        }

        (new MenuService())->invalidateRestaurant($id);
    }

    /**
     * @param array{slug: string, currency_code: string} $data
     *
     * @return array<string, string>
     */
    private function validateCommon(array $data, ?int $exceptId, ?string $currentCurrency): array
    {
        $errors = [];

        if (preg_match('/^[A-Z]{3}$/', $data['currency_code']) !== 1 || ! (new CurrencyModel())->isSelectable($data['currency_code'], $currentCurrency)) {
            $errors['currency_code'] = lang('App.restaurant.currencyInvalid');
        }

        if ($data['slug'] !== '') {
            // Slug menünün alt alan adıdır (sirketadi.pixmenu.com): DNS etiketi sınırı 63.
            if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $data['slug']) !== 1 || strlen($data['slug']) > SiteService::MAX_SLUG_LENGTH) {
                $errors['slug'] = lang('App.restaurant.slugInvalid');
            } elseif (in_array($data['slug'], config('Pixmenu')->reservedSubdomains, true)) {
                $errors['slug'] = lang('App.restaurant.slugReserved');
            } elseif ($this->slugExists($data['slug'], $exceptId)) {
                $errors['slug'] = lang('App.restaurant.slugTaken');
            }
        }

        return $errors;
    }

    /**
     * Seçilmemişse varsayılan (deneme) paket.
     */
    private function resolvePlanId(?int $planId): int
    {
        if ($planId !== null && $planId > 0) {
            return $planId;
        }

        $plan = (new PlanModel())->findByCode(config('Billing')->defaultPlanCode);

        if ($plan === null) {
            throw ValidationException::single('plan_id', lang('App.subscription.planInvalid'));
        }

        return (int) $plan['id'];
    }

    /**
     * Restoranın (silinmemiş) ürünü var mı — para birimi kilidi için.
     */
    public function hasProducts(int $restaurantId): bool
    {
        return (new ProductModel())->forRestaurant($restaurantId)->countAllResults() > 0;
    }

    private function slugExists(string $slug, ?int $exceptId): bool
    {
        $model = (new RestaurantModel())->withDeleted()->where('slug', $slug);

        if ($exceptId !== null) {
            $model->where('id !=', $exceptId);
        }

        return $model->countAllResults() > 0;
    }

    /**
     * Slug boşsa varsayılan dildeki isimden üretilir; çakışırsa -2, -3… eklenir.
     * Taban 60 karakterle sınırlıdır (ekle birlikte 63'ü aşmasın); ayrılmış adlara `-menu` eklenir.
     */
    private function resolveSlug(string $slug, string $name): string
    {
        if ($slug !== '') {
            return $slug;
        }

        $base = pix_slug($name, SiteService::MAX_SLUG_LENGTH - 3);
        $base = $base === '' ? 'restoran' : $base;
        $base = in_array($base, config('Pixmenu')->reservedSubdomains, true) ? $base . '-menu' : $base;
        $slug = $base;

        for ($i = 2; $this->slugExists($slug, null); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
