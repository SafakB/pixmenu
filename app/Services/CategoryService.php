<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\CategoryModel;
use App\Models\CategoryTranslationModel;
use App\Models\ProductModel;
use App\Models\RestaurantLanguageModel;
use App\Models\RestaurantModel;
use App\Services\Concerns\Transactional;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Kategoriler (owner + manager) — spec §10.1.
 *
 * Tüm metodlar, çağıran controller'ın `requireBranch()` / `requireCategory()` ile
 * DOĞRULADIĞI şube ve kategori kayıtlarını alır; ID'ler burada request'ten okunmaz.
 * Zorunlu çeviri dili ŞUBENİN varsayılan dilidir.
 */
final class CategoryService
{
    use Transactional;

    public function __construct(
        private readonly Scope $scope,
        private readonly TranslationService $translations = new TranslationService(),
    ) {
    }

    /**
     * Şube içeriği için dil bağlamı.
     *
     * @param array<string, mixed> $branch
     *
     * @return array{languages: list<array<string, mixed>>, codes: list<string>, required_code: string, required_id: int}
     */
    public function languageContext(array $branch): array
    {
        $languages = $this->scope->model(RestaurantLanguageModel::class)->languages(true);
        $codesById = array_column($languages, 'code', 'id');
        $codes     = array_map('strval', array_values($codesById));

        $requiredId = (int) $branch['default_language_id'];

        if (! isset($codesById[$requiredId])) {
            // Şubenin varsayılan dili pasifleşmişse restoranın varsayılanına düş.
            $restaurant = (new RestaurantModel())->find($this->scope->restaurantId());
            $requiredId = (int) ($restaurant['default_language_id'] ?? 0);

            if (! isset($codesById[$requiredId])) {
                $requiredId = (int) (array_key_first($codesById) ?? 0);
            }
        }

        return [
            'languages'     => $languages,
            'codes'         => $codes,
            'required_code' => (string) ($codesById[$requiredId] ?? ''),
            'required_id'   => $requiredId,
        ];
    }

    /**
     * @param array<string, mixed>                 $branch       doğrulanmış şube
     * @param array{is_active: int, sort_order: int|null, emoji?: string|null} $data
     * @param array<string, array<string, string>> $translations
     */
    public function create(array $branch, array $data, array $translations, ?UploadedFile $image): int
    {
        $branchId = (int) $branch['id'];
        $context  = $this->languageContext($branch);
        $this->assertTranslations($translations, $context);
        $emoji = $data['emoji'] ?? null;
        $this->assertEmoji($emoji);

        $images = service('images');
        $stored = $image === null ? null : $images->store($image, 'category', $this->scope->restaurantId());

        $id = $this->atomic(function () use ($branchId, $data, $emoji, $translations, $context, $stored): int {
            $model = $this->categories();
            $id    = (int) $model->insert([
                'branch_id'  => $branchId,
                'image_path' => $stored['path'] ?? null,
                'emoji'      => $emoji,
                'is_active'  => $data['is_active'],
                'sort_order' => $data['sort_order'] ?? $this->categories()->nextSortOrder($branchId),
            ]);

            $this->translations->sync(CategoryTranslationModel::class, 'category_id', $id, $translations, $context['codes'], $context['required_code']);

            return $id;
        }, static fn () => $images->delete($stored['path'] ?? null));

        (new MenuService())->invalidate($branchId);

        return $id;
    }

    /**
     * @param array<string, mixed>                 $branch       doğrulanmış şube
     * @param array<string, mixed>                 $category     doğrulanmış kategori (bu şubeye ait)
     * @param array{is_active: int, sort_order: int|null, remove_image: bool, emoji?: string|null} $data  emoji gönderilmezse mevcut değer korunur
     * @param array<string, array<string, string>> $translations
     */
    public function update(array $branch, array $category, array $data, array $translations, ?UploadedFile $image): void
    {
        $id      = (int) $category['id'];
        $context = $this->languageContext($branch);
        $this->assertTranslations($translations, $context);
        $emoji = array_key_exists('emoji', $data) ? $data['emoji'] : $category['emoji'];

        if ($emoji !== $category['emoji']) {
            $this->assertEmoji($emoji); // değişmeyen (listeden sonradan çıkarılmış olabilir) değer yeniden denetlenmez
        }

        $images = service('images');
        $stored = $image === null ? null : $images->store($image, 'category', $this->scope->restaurantId());

        $this->atomic(function () use ($id, $category, $data, $emoji, $translations, $context, $stored): void {
            $fields = [
                'emoji'      => $emoji,
                'is_active'  => $data['is_active'],
                'sort_order' => $data['sort_order'] ?? (int) $category['sort_order'],
            ];

            if ($stored !== null) {
                $fields['image_path'] = $stored['path'];
            } elseif ($data['remove_image']) {
                $fields['image_path'] = null;
            }

            $this->categories()->update($id, $fields);
            $this->translations->sync(CategoryTranslationModel::class, 'category_id', $id, $translations, $context['codes'], $context['required_code']);
        }, static fn () => $images->delete($stored['path'] ?? null));

        // Commit sonrası: değiştirilen/kaldırılan eski görsel silinir (yol DB kaydından).
        if ($stored !== null || $data['remove_image']) {
            $images->delete($category['image_path']);
        }

        (new MenuService())->invalidate((int) $branch['id']);
    }

    /**
     * @param array<string, mixed> $category
     */
    public function toggle(array $category): void
    {
        $this->categories()->update((int) $category['id'], [
            'is_active' => (int) $category['is_active'] === 1 ? 0 : 1,
        ]);

        (new MenuService())->invalidate((int) $category['branch_id']);
    }

    /**
     * İçinde (silinmemiş) ürün varsa reddedilir; kullanıcıya ürün sayısı bildirilir.
     * Soft delete — dosyalar kalır.
     *
     * @param array<string, mixed> $category
     */
    public function delete(array $category): void
    {
        $id    = (int) $category['id'];
        $count = $this->scope->model(ProductModel::class)->countInCategory($id);

        if ($count > 0) {
            throw new BusinessRuleException(lang('App.category.hasProducts', [$count]));
        }

        $this->categories()->delete($id);

        (new MenuService())->invalidate((int) $category['branch_id']);
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
        $owned    = $this->categories()->idsInBranchAmong($branchId, $ids);

        if (count($owned) !== count($ids)) {
            throw ValidationException::single('ids', lang('App.reorder.foreign'));
        }

        $this->atomic(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                $this->categories()->update($id, ['sort_order' => $index]);
            }
        });

        (new MenuService())->invalidate($branchId);
    }

    private function categories(): CategoryModel
    {
        return $this->scope->model(CategoryModel::class);
    }

    /**
     * @param array<string, array<string, string>>                 $translations
     * @param array{codes: list<string>, required_code: string} $context
     */
    private function assertTranslations(array $translations, array $context): void
    {
        $errors = $this->translations->validate(CategoryTranslationModel::class, $translations, $context['codes'], $context['required_code']);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Emoji yalnızca `Config\CategoryEmojis` listesinden seçilebilir; null = emoji yok.
     * Görsel diske yazılmadan ÖNCE çağrılır (hata durumunda sahipsiz dosya kalmasın).
     */
    private function assertEmoji(?string $emoji): void
    {
        if ($emoji !== null && ! config('CategoryEmojis')->isAllowed($emoji)) {
            throw ValidationException::single('emoji', lang('App.category.invalidEmoji'));
        }
    }
}
