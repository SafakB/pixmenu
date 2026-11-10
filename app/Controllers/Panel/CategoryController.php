<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Exceptions\ValidationException;
use App\Models\BranchTranslationModel;
use App\Models\CategoryModel;
use App\Models\CategoryTranslationModel;
use App\Models\ProductModel;
use App\Services\CategoryService;
use App\Services\Scope;
use App\Services\TranslationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Kategoriler (owner + manager; manager yalnızca atanmış şubeler).
 * Her action ilk iş olarak requireBranch()/requireCategory() çağırır.
 */
final class CategoryController extends PanelBaseController
{
    /**
     * View: panel/categories/index — branch{id, name},
     *       categories[{id, name, image_url, is_active, sort_order, product_count}]
     */
    public function index(int $branchId): string
    {
        $branch     = $this->requireBranch($branchId);
        $categories = (new CategoryModel())->forBranch($branchId);
        $resolver   = service('languageResolver');
        $chain      = $resolver->chain((int) $branch['default_language_id'], (int) $this->restaurant['default_language_id']);
        $names      = $resolver->resolveMany(CategoryTranslationModel::class, array_map('intval', array_column($categories, 'id')), $chain);
        $counts     = (new ProductModel())->countsByCategory($branchId);

        return $this->render('panel/categories/index', [
            'branch'     => $this->branchSummary($branch),
            'categories' => array_map(static fn (array $category): array => [
                'id'            => (int) $category['id'],
                'name'          => $names[(int) $category['id']]['name'] ?? '#' . $category['id'],
                'image_url'     => upload_url($category['image_path']),
                'is_active'     => (int) $category['is_active'] === 1,
                'sort_order'    => (int) $category['sort_order'],
                'product_count' => $counts[(int) $category['id']] ?? 0,
            ], $categories),
        ]);
    }

    /**
     * View: panel/categories/form — branch, category(null), image_url, languages, translations, action
     */
    public function new(int $branchId): string
    {
        return $this->form($this->requireBranch($branchId), null);
    }

    public function create(int $branchId): ResponseInterface
    {
        $branch = $this->requireBranch($branchId);

        return $this->handle(function () use ($branch, $branchId): ResponseInterface {
            $this->validateOrFail($this->imageRules('image', 'category'));

            (new CategoryService(Scope::tenant()))->create(
                $branch,
                ['is_active' => $this->flag('is_active'), 'sort_order' => $this->optionalSortOrder()],
                $this->translationsInput(),
                $this->uploaded('image'),
            );

            return $this->done("panel/branches/{$branchId}/categories", lang('App.created'));
        });
    }

    /**
     * View: panel/categories/form — branch, category, image_url, languages, translations, action
     */
    public function edit(int $branchId, int $categoryId): string
    {
        $category = $this->requireCategory($branchId, $categoryId);

        return $this->form($this->requireBranch($branchId), $category);
    }

    public function update(int $branchId, int $categoryId): ResponseInterface
    {
        $category = $this->requireCategory($branchId, $categoryId);
        $branch   = $this->requireBranch($branchId);

        return $this->handle(function () use ($branch, $category, $branchId): ResponseInterface {
            $this->validateOrFail($this->imageRules('image', 'category'));

            (new CategoryService(Scope::tenant()))->update(
                $branch,
                $category,
                [
                    'is_active'    => $this->flag('is_active'),
                    'sort_order'   => $this->optionalSortOrder(),
                    'remove_image' => $this->flag('remove_image') === 1,
                ],
                $this->translationsInput(),
                $this->uploaded('image'),
            );

            return $this->done("panel/branches/{$branchId}/categories", lang('App.saved'));
        });
    }

    public function toggle(int $branchId, int $categoryId): ResponseInterface
    {
        (new CategoryService(Scope::tenant()))->toggle($this->requireCategory($branchId, $categoryId));

        return $this->done("panel/branches/{$branchId}/categories", lang('App.statusChanged'));
    }

    public function delete(int $branchId, int $categoryId): ResponseInterface
    {
        $category = $this->requireCategory($branchId, $categoryId);

        return $this->handle(function () use ($category, $branchId): ResponseInterface {
            (new CategoryService(Scope::tenant()))->delete($category);

            return $this->done("panel/branches/{$branchId}/categories", lang('App.deleted'));
        });
    }

    /**
     * JSON: {"ids":[12,5,9]} — biri bile bu şubeye ait değilse 422, hiçbir sıra değişmez.
     */
    public function reorder(int $branchId): ResponseInterface
    {
        $branch = $this->requireBranch($branchId);

        try {
            (new CategoryService(Scope::tenant()))->reorder($branch, $this->jsonIds());
        } catch (ValidationException $e) {
            return $this->jsonError(lang('App.validationFailed'), $e->errors(), 422);
        }

        return $this->jsonSuccess([], lang('App.reorder.saved'));
    }

    /**
     * @param array<string, mixed>      $branch
     * @param array<string, mixed>|null $category
     */
    private function form(array $branch, ?array $category): string
    {
        $translations = new TranslationService();
        $context      = (new CategoryService(Scope::tenant()))->languageContext($branch);
        $branchId     = (int) $branch['id'];

        return $this->render('panel/categories/form', [
            'branch'       => $this->branchSummary($branch),
            'category'     => $category,
            'image_url'    => upload_url($category['image_path'] ?? null),
            // is_default: zorunlu dil = ŞUBENİN varsayılan dili
            'languages'    => $translations->formLanguages($context['languages'], $context['required_id']),
            'translations' => $category === null ? [] : $translations->load(CategoryTranslationModel::class, (int) $category['id']),
            'action'       => "panel/branches/{$branchId}/categories" . ($category === null ? '' : '/' . $category['id']),
        ]);
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

    /**
     * sort_order boş gönderilirse null (servis sona ekler / mevcut değeri korur).
     */
    private function optionalSortOrder(): ?int
    {
        return trim((string) $this->request->getPost('sort_order')) === '' ? null : $this->sortOrder();
    }
}
