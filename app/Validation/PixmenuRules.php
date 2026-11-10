<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\AllergenModel;
use App\Models\BranchModel;
use App\Models\CategoryModel;
use App\Models\RestaurantLanguageModel;

/**
 * Özel doğrulama kuralları (spec §10.2).
 *
 * Tenant'a bakan kurallar YALNIZCA restoran panelinde (TenantContext kuruluyken)
 * kullanılır; tenant kapsamlı modeller üzerinden çalışır.
 */
final class PixmenuRules
{
    /**
     * §7.1 parola politikası: en az 10 karakter, en az bir harf ve bir rakam.
     */
    public static function isStrongPassword(string $password): bool
    {
        return mb_strlen($password) >= 10
            && mb_strlen($password) <= 255
            && preg_match('/\p{L}/u', $password) === 1
            && preg_match('/\d/', $password) === 1;
    }

    public function strong_password(mixed $value, ?string &$error = null): bool
    {
        if (is_string($value) && self::isStrongPassword($value)) {
            return true;
        }

        $error = lang('App.user.passwordWeak');

        return false;
    }

    /**
     * Değer, aktif tenant'ın ve kullanıcının erişebildiği bir şube ID'si mi?
     *
     * @param array<string, mixed> $data
     */
    public function tenant_branch(mixed $value, ?string $params = null, array $data = [], ?string &$error = null): bool
    {
        if (self::isPositiveInt($value)) {
            $branchId = (int) $value;

            if (service('tenant')->canAccessBranch($branchId) && (new BranchModel())->find($branchId) !== null) {
                return true;
            }
        }

        $error = lang('App.branch.invalid');

        return false;
    }

    /**
     * Değer, verilen şubeye ait silinmemiş bir kategori mi? Kullanım: branch_category[15]
     *
     * @param array<string, mixed> $data
     */
    public function branch_category(mixed $value, ?string $branchId = null, array $data = [], ?string &$error = null): bool
    {
        if (self::isPositiveInt($value) && self::isPositiveInt($branchId)
            && (new CategoryModel())->findInBranch((int) $branchId, (int) $value) !== null) {
            return true;
        }

        $error = lang('App.category.invalid');

        return false;
    }

    /**
     * Dil ID'si restoranın aktif dillerinden mi?
     */
    public function restaurant_language(mixed $value, ?string &$error = null): bool
    {
        if (self::isPositiveInt($value)
            && in_array((int) $value, (new RestaurantLanguageModel())->languageIds(true), true)) {
            return true;
        }

        $error = lang('App.language.notInRestaurant');

        return false;
    }

    /**
     * Alerjen ID'si var ve aktif mi?
     */
    public function active_allergen(mixed $value, ?string &$error = null): bool
    {
        if (self::isPositiveInt($value) && (new AllergenModel())->activeIdsAmong([(int) $value]) !== []) {
            return true;
        }

        $error = lang('App.allergen.invalid');

        return false;
    }

    private static function isPositiveInt(mixed $value): bool
    {
        return (is_int($value) && $value > 0) || (is_string($value) && ctype_digit($value) && (int) $value > 0);
    }
}
