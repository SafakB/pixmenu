<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Exceptions\ValidationException;
use App\Models\AllergenModel;
use App\Models\AllergenTranslationModel;
use App\Models\BranchTranslationModel;
use App\Models\CategoryModel;
use App\Models\CategoryTranslationModel;
use App\Models\ProductAllergenModel;
use App\Models\ProductModel;
use App\Models\ProductTranslationModel;
use App\Services\CategoryService;
use App\Services\ProductService;
use App\Services\Scope;
use App\Services\TranslationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Ürünler (owner + manager; manager yalnızca atanmış şubeler).
 * Her action ilk iş olarak requireBranch()/requireProduct() çağırır.
 */
final class ProductController extends PanelBaseController
{
    /**
     * ?category=ID&q=&min_price=&max_price=
     *
     * View: panel/products/index — branch{id, name}, categories[{id, name}], filters{category, q, min_price, max_price},
     *       price_range{min, max}, currency,
     *       products[{id, name, category_id, category_name, price, thumb_url, is_active, sort_order}]
     */
    public function index(int $branchId): string
    {
        $branch = $this->requireBranch($branchId);

        $categoryFilter = null;
        $rawCategory    = (string) ($this->request->getGet('category') ?? '');

        if ($rawCategory !== '') {
            // Filtredeki kategori de sahiplik kontrolünden geçer.
            if (! ctype_digit($rawCategory)) {
                $this->notFound();
            }

            $categoryFilter = (int) $this->requireCategory($branchId, (int) $rawCategory)['id'];
        }

        $search   = mb_substr(trim((string) ($this->request->getGet('q') ?? '')), 0, 100);
        $minPrice = $this->priceParam('min_price');
        $maxPrice = $this->priceParam('max_price');

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        $model    = new ProductModel();
        $products = $model->forBranch($branchId, $categoryFilter, $search, $minPrice, $maxPrice);

        $resolver      = service('languageResolver');
        $chain         = $resolver->chain((int) $branch['default_language_id'], (int) $this->restaurant['default_language_id']);
        $categories    = $this->categoryOptions($branchId, $chain);
        $categoryNames = array_column($categories, 'name', 'id');
        $names         = $resolver->resolveMany(ProductTranslationModel::class, array_map('intval', array_column($products, 'id')), $chain);

        return $this->render('panel/products/index', [
            'branch'     => $this->branchSummary($branch),
            'categories' => $categories,
            'filters'    => ['category' => $categoryFilter, 'q' => $search, 'min_price' => $minPrice, 'max_price' => $maxPrice],
            'price_range' => $model->priceRange($branchId),
            'currency'    => $this->restaurant['currency_code'],
            'products'   => array_map(static fn (array $product): array => [
                'id'            => (int) $product['id'],
                'name'          => $names[(int) $product['id']]['name'] ?? '#' . $product['id'],
                'category_id'   => (int) $product['category_id'],
                'category_name' => $categoryNames[(int) $product['category_id']] ?? '',
                'price'         => $product['price'],
                'thumb_url'     => upload_url($product['thumb_path']),
                'is_active'     => (int) $product['is_active'] === 1,
                'sort_order'    => (int) $product['sort_order'],
            ], $products),
        ]);
    }

    /**
     * View: panel/products/form — branch, product(null), image_url, categories, allergens,
     *       selectedAllergens, languages, translations, currency, action
     */
    public function new(int $branchId): string
    {
        return $this->form($this->requireBranch($branchId), null);
    }

    public function create(int $branchId): ResponseInterface
    {
        $branch = $this->requireBranch($branchId);

        return $this->handle(function () use ($branch, $branchId): ResponseInterface {
            $this->validateOrFail($this->imageRules('image', 'product'));

            (new ProductService(Scope::tenant()))->create(
                $branch,
                $this->input(),
                $this->translationsInput(),
                $this->request->getPost('allergens'),
                $this->uploaded('image'),
            );

            return $this->done("panel/branches/{$branchId}/products", lang('App.created'));
        });
    }

    /**
     * View: panel/products/form — (new ile aynı) + product
     */
    public function edit(int $branchId, int $productId): string
    {
        $product = $this->requireProduct($branchId, $productId);

        return $this->form($this->requireBranch($branchId), $product);
    }

    public function update(int $branchId, int $productId): ResponseInterface
    {
        $product = $this->requireProduct($branchId, $productId);
        $branch  = $this->requireBranch($branchId);

        return $this->handle(function () use ($branch, $product, $branchId): ResponseInterface {
            $this->validateOrFail($this->imageRules('image', 'product'));

            (new ProductService(Scope::tenant()))->update(
                $branch,
                $product,
                $this->input(),
                $this->translationsInput(),
                $this->request->getPost('allergens'),
                $this->uploaded('image'),
            );

            return $this->done("panel/branches/{$branchId}/products", lang('App.saved'));
        });
    }

    public function toggle(int $branchId, int $productId): ResponseInterface
    {
        (new ProductService(Scope::tenant()))->toggle($this->requireProduct($branchId, $productId));

        return $this->done("panel/branches/{$branchId}/products", lang('App.statusChanged'));
    }

    public function deleteImage(int $branchId, int $productId): ResponseInterface
    {
        (new ProductService(Scope::tenant()))->deleteImage($this->requireProduct($branchId, $productId));

        return $this->done("panel/branches/{$branchId}/products/{$productId}/edit", lang('App.upload.imageDeleted'));
    }

    public function delete(int $branchId, int $productId): ResponseInterface
    {
        (new ProductService(Scope::tenant()))->delete($this->requireProduct($branchId, $productId));

        return $this->done("panel/branches/{$branchId}/products", lang('App.deleted'));
    }

    /**
     * JSON: {"ids":[12,5,9]} — biri bile bu şubeye ait değilse 422, hiçbir sıra değişmez.
     */
    public function reorder(int $branchId): ResponseInterface
    {
        $branch = $this->requireBranch($branchId);

        try {
            (new ProductService(Scope::tenant()))->reorder($branch, $this->jsonIds());
        } catch (ValidationException $e) {
            return $this->jsonError(lang('App.validationFailed'), $e->errors(), 422);
        }

        return $this->jsonSuccess([], lang('App.reorder.saved'));
    }

    /**
     * Fiyat filtresi parametresi: "129,90" de kabul edilir; sayı değilse veya negatifse yok sayılır.
     */
    private function priceParam(string $key): ?float
    {
        $raw = str_replace(',', '.', trim((string) ($this->request->getGet($key) ?? '')));

        if ($raw === '' || ! is_numeric($raw) || (float) $raw < 0) {
            return null;
        }

        return (float) $raw;
    }

    /**
     * Alanlar tek tek seçilir; restaurant_id ve branch_id request'ten OKUNMAZ
     * (branch_id URL'de doğrulanan şubeden, restaurant_id TenantContext'ten gelir).
     *
     * @return array{category_id: mixed, price: string, is_active: int, sort_order: int|null}
     */
    private function input(): array
    {
        return [
            'category_id' => $this->request->getPost('category_id'),
            'price'       => (string) $this->request->getPost('price'),
            'is_active'   => $this->flag('is_active'),
            'sort_order'  => trim((string) $this->request->getPost('sort_order')) === '' ? null : $this->sortOrder(),
        ];
    }

    /**
     * @param array<string, mixed>      $branch
     * @param array<string, mixed>|null $product
     */
    private function form(array $branch, ?array $product): string
    {
        $branchId     = (int) $branch['id'];
        $translations = new TranslationService();
        $context      = (new CategoryService(Scope::tenant()))->languageContext($branch);
        $resolver     = service('languageResolver');
        $chain        = $resolver->chain((int) $branch['default_language_id'], (int) $this->restaurant['default_language_id']);

        // Alerjenler global ve salt okunur: yalnızca aktif olanlar seçilebilir.
        $allergens     = (new AllergenModel())->ordered(true);
        $allergenNames = $resolver->resolveMany(
            AllergenTranslationModel::class,
            array_map('intval', array_column($allergens, 'id')),
            $resolver->allergenChain(null, (int) $branch['default_language_id']),
        );

        return $this->render('panel/products/form', [
            'branch'     => $this->branchSummary($branch),
            'product'    => $product,
            'image_url'  => upload_url($product['image_path'] ?? null),
            'categories' => $this->categoryOptions($branchId, $chain),
            'allergens'  => array_map(static fn (array $allergen): array => [
                'id'       => (int) $allergen['id'],
                'code'     => $allergen['code'],
                'name'     => $allergenNames[(int) $allergen['id']]['name'] ?? $allergen['code'],
                'icon_url' => upload_url($allergen['icon_path']),
            ], $allergens),
            'selectedAllergens' => $product === null ? [] : (new ProductAllergenModel())->forProduct((int) $product['id']),
            'languages'         => $translations->formLanguages($context['languages'], $context['required_id']),
            'translations'      => $product === null ? [] : $translations->load(ProductTranslationModel::class, (int) $product['id']),
            'currency'          => $this->restaurant['currency_code'],
            'action'            => "panel/branches/{$branchId}/products" . ($product === null ? '' : '/' . $product['id']),
        ]);
    }

    /**
     * @param list<int> $chain
     *
     * @return list<array{id: int, name: string}>
     */
    private function categoryOptions(int $branchId, array $chain): array
    {
        $categories = (new CategoryModel())->forBranch($branchId);
        $names      = service('languageResolver')->resolveMany(
            CategoryTranslationModel::class,
            array_map('intval', array_column($categories, 'id')),
            $chain,
        );

        return array_map(static fn (array $category): array => [
            'id'   => (int) $category['id'],
            'name' => (string) ($names[(int) $category['id']]['name'] ?? '#' . $category['id']),
        ], $categories);
    }

    /**
     * @param array<string, mixed> $branch
     *
     * @return array{id: int, name: string}
     */
    private function branchSummary(array $branch): array
    {
        $name = service('languageResolver')->resolveOne(
            BranchTranslationModel::class,
            (int) $branch['id'],
            [(int) $this->restaurant['default_language_id']],
        )['name'] ?? $branch['slug'];

        return ['id' => (int) $branch['id'], 'name' => (string) $name];
    }
}
