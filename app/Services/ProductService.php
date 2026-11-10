<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\AllergenModel;
use App\Models\CategoryModel;
use App\Models\ProductAllergenModel;
use App\Models\ProductModel;
use App\Models\ProductTranslationModel;
use App\Services\Concerns\Transactional;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Ürünler (owner + manager) — spec §10.1.
 *
 * Tüm metodlar, çağıran controller'ın `requireBranch()` / `requireProduct()` ile
 * DOĞRULADIĞI kayıtları alır. Formdan gelen her yabancı anahtar (category_id,
 * alerjen ID'leri, dil kodları) yazılmadan önce burada doğrulanır (spec §2.1.5).
 * Ürün + çeviri + alerjen yazımı tek transaction'dır.
 */
final class ProductService
{
    use Transactional;

    private readonly CategoryService $categories;

    public function __construct(
        private readonly Scope $scope,
        private readonly TranslationService $translations = new TranslationService(),
    ) {
        $this->categories = new CategoryService($scope, $translations);
    }

    /**
     * @param array<string, mixed>                 $branch       doğrulanmış şube
     * @param array{category_id: mixed, price: string, is_active: int, sort_order: int|null} $data
     * @param array<string, array<string, string>> $translations
     * @param mixed                                $allergens    allergens[{allergen_id}] = contains|may_contain
     */
    public function create(array $branch, array $data, array $translations, mixed $allergens, ?UploadedFile $image): int
    {
        $branchId = (int) $branch['id'];
        $context  = $this->categories->languageContext($branch);
        $clean    = $this->validate($branchId, $data, $translations, $allergens, $context);

        $images = service('images');
        $stored = $image === null ? null : $images->store($image, 'product', $this->scope->restaurantId());

        $id = $this->atomic(function () use ($branchId, $data, $clean, $translations, $context, $stored): int {
            $id = (int) $this->products()->insert([
                'branch_id'   => $branchId,
                'category_id' => $clean['category_id'],
                'price'       => $clean['price'],
                'image_path'  => $stored['path'] ?? null,
                'thumb_path'  => $stored['thumb'] ?? null,
                'is_active'   => $data['is_active'],
                'sort_order'  => $data['sort_order'] ?? $this->products()->nextSortOrder($clean['category_id']),
            ]);

            $this->translations->sync(ProductTranslationModel::class, 'product_id', $id, $translations, $context['codes'], $context['required_code']);
            $this->scope->model(ProductAllergenModel::class)->replaceForProduct($id, $clean['allergens']);

            return $id;
        }, static fn () => $images->delete($stored['path'] ?? null, $stored['thumb'] ?? null));

        (new MenuService())->invalidate($branchId);

        return $id;
    }

    /**
     * @param array<string, mixed>                 $branch       doğrulanmış şube
     * @param array<string, mixed>                 $product      doğrulanmış ürün (bu şubeye ait)
     * @param array{category_id: mixed, price: string, is_active: int, sort_order: int|null} $data
     * @param array<string, array<string, string>> $translations
     */
    public function update(array $branch, array $product, array $data, array $translations, mixed $allergens, ?UploadedFile $image): void
    {
        $id       = (int) $product['id'];
        $branchId = (int) $branch['id'];
        $context  = $this->categories->languageContext($branch);
        $clean    = $this->validate($branchId, $data, $translations, $allergens, $context);

        $images = service('images');
        $stored = $image === null ? null : $images->store($image, 'product', $this->scope->restaurantId());

        $this->atomic(function () use ($id, $product, $data, $clean, $translations, $context, $stored): void {
            $fields = [
                'category_id' => $clean['category_id'],
                'price'       => $clean['price'],
                'is_active'   => $data['is_active'],
                'sort_order'  => $data['sort_order'] ?? (int) $product['sort_order'],
            ];

            if ($stored !== null) {
                $fields['image_path'] = $stored['path'];
                $fields['thumb_path'] = $stored['thumb'];
            }

            $this->products()->update($id, $fields);
            $this->translations->sync(ProductTranslationModel::class, 'product_id', $id, $translations, $context['codes'], $context['required_code']);
            $this->scope->model(ProductAllergenModel::class)->replaceForProduct($id, $clean['allergens']);
        }, static fn () => $images->delete($stored['path'] ?? null, $stored['thumb'] ?? null));

        // Commit sonrası: değiştirilen eski görseller silinir (yollar DB kaydından).
        if ($stored !== null) {
            $images->delete($product['image_path'], $product['thumb_path']);
        }

        (new MenuService())->invalidate($branchId);
    }

    /**
     * @param array<string, mixed> $product
     */
    public function toggle(array $product): void
    {
        $this->products()->update((int) $product['id'], [
            'is_active' => (int) $product['is_active'] === 1 ? 0 : 1,
        ]);

        (new MenuService())->invalidate((int) $product['branch_id']);
    }

    /**
     * Görseli kaldırır. Silinecek dosya yolları DB kaydından alınır; request'ten yol okunmaz.
     *
     * @param array<string, mixed> $product
     */
    public function deleteImage(array $product): void
    {
        $this->products()->update((int) $product['id'], ['image_path' => null, 'thumb_path' => null]);
        service('images')->delete($product['image_path'], $product['thumb_path']);

        (new MenuService())->invalidate((int) $product['branch_id']);
    }

    /**
     * Soft delete — dosyalar kalır.
     *
     * @param array<string, mixed> $product
     */
    public function delete(array $product): void
    {
        $this->products()->delete((int) $product['id']);

        (new MenuService())->invalidate((int) $product['branch_id']);
    }

    /**
     * sort_order = dizideki index. ID'lerden biri bile bu şubeye (ve tenant'a) ait
     * değilse işlem TAMAMEN reddedilir.
     *
     * @param array<string, mixed> $branch doğrulanmış şube
     * @param list<int>            $ids
     */
    public function reorder(array $branch, array $ids): void
    {
        $branchId = (int) $branch['id'];
        $owned    = $this->products()->idsInBranchAmong($branchId, $ids);

        if (count($owned) !== count($ids)) {
            throw ValidationException::single('ids', lang('App.reorder.foreign'));
        }

        $this->atomic(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                $this->products()->update($id, ['sort_order' => $index]);
            }
        });

        (new MenuService())->invalidate($branchId);
    }

    /**
     * Virgüllü girişi noktaya çevirir ("129,90" → "129.90", "1.299,90" → "1299.90").
     * Geçersizse null.
     */
    public static function normalizePrice(string $raw): ?string
    {
        $value = str_replace(["\u{00A0}", ' '], '', trim($raw));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        }

        if (preg_match('/^\d{1,8}(\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    // ------------------------------------------------------------------

    private function products(): ProductModel
    {
        return $this->scope->model(ProductModel::class);
    }

    /**
     * @param array{category_id: mixed, price: string}                     $data
     * @param array<string, array<string, string>>                         $translations
     * @param array{codes: list<string>, required_code: string}         $context
     *
     * @return array{category_id: int, price: string, allergens: array<int, string>}
     */
    private function validate(int $branchId, array $data, array $translations, mixed $allergens, array $context): array
    {
        $errors = [];

        // category_id: aynı şubeye (dolayısıyla aynı tenant'a) ait ve silinmemiş olmalı.
        $categoryId = $data['category_id'];
        $categoryId = (is_int($categoryId) || (is_string($categoryId) && ctype_digit($categoryId))) ? (int) $categoryId : 0;

        if ($categoryId <= 0 || $this->scope->model(CategoryModel::class)->findInBranch($branchId, $categoryId) === null) {
            $errors['category_id'] = lang('App.category.invalid');
        }

        $price = self::normalizePrice($data['price']);

        if ($price === null) {
            $errors['price'] = lang('App.product.priceInvalid');
        } elseif ((float) $price >= 100_000_000) {
            $errors['price'] = lang('App.product.priceTooBig');
        }

        $levels = [];

        if ($allergens !== null && $allergens !== '' && ! is_array($allergens)) {
            $errors['allergens'] = lang('App.allergen.invalid');
        } elseif (is_array($allergens)) {
            foreach ($allergens as $allergenId => $level) {
                // Boş değer = seçilmemiş (gönderilmeyenler gibi silinir).
                if ($level === '' || $level === null) {
                    continue;
                }

                if (! (is_int($allergenId) || ctype_digit((string) $allergenId)) || (int) $allergenId <= 0) {
                    $errors['allergens'] = lang('App.allergen.invalid');

                    break;
                }

                if (! is_string($level) || ! in_array($level, ProductAllergenModel::LEVELS, true)) {
                    $errors['allergens'] = lang('App.allergen.levelInvalid');

                    break;
                }

                $levels[(int) $allergenId] = $level;
            }

            // Yalnızca var olan ve is_active=1 alerjenler kabul edilir.
            if (! isset($errors['allergens']) && $levels !== []) {
                $active = (new AllergenModel())->activeIdsAmong(array_keys($levels));

                if (count($active) !== count($levels)) {
                    $errors['allergens'] = lang('App.allergen.invalid');
                }
            }
        }

        $errors += $this->translations->validate(ProductTranslationModel::class, $translations, $context['codes'], $context['required_code']);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return ['category_id' => $categoryId, 'price' => (string) $price, 'allergens' => $levels];
    }
}
