<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\AllergenModel;
use App\Models\AllergenTranslationModel;
use App\Models\LanguageModel;
use App\Services\Concerns\Transactional;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Global alerjen yönetimi (yalnızca admin) — spec §4.4, §10.1.
 * Panel kullanıcıları alerjenleri yalnızca okur.
 */
final class AllergenService
{
    use Transactional;

    /** Alerjen çevirilerinde zorunlu dil. */
    public const REQUIRED_LANG = 'tr';

    public function __construct(
        private readonly TranslationService $translations = new TranslationService(),
    ) {
    }

    /**
     * Çeviri girilebilecek diller: sistemdeki tüm AKTİF diller.
     *
     * @return list<array<string, mixed>>
     */
    public function languages(): array
    {
        return (new LanguageModel())->ordered(true);
    }

    /**
     * @param array{code: string, is_active: int, sort_order: int} $data
     * @param array<string, array<string, string>>                 $translations
     */
    public function create(array $data, array $translations, ?UploadedFile $icon): int
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,39}$/', $data['code']) !== 1) {
            throw ValidationException::single('code', lang('App.allergen.codeInvalid'));
        }

        $model = new AllergenModel();

        if ($model->where('code', $data['code'])->countAllResults() > 0) {
            throw ValidationException::single('code', lang('App.allergen.codeTaken'));
        }

        $codes = $this->languageCodes();
        $this->assertTranslations($translations, $codes);

        $images = service('images');
        $stored = $icon === null ? null : $images->store($icon, 'allergen', null, 'icon');

        $id = $this->atomic(function () use ($data, $translations, $codes, $stored, $model): int {
            $id = (int) $model->insert([
                'code'       => $data['code'],
                'icon_path'  => $stored['path'] ?? null,
                'is_active'  => $data['is_active'],
                'sort_order' => $data['sort_order'],
            ]);

            $this->translations->sync(AllergenTranslationModel::class, 'allergen_id', $id, $translations, $codes, self::REQUIRED_LANG);

            return $id;
        }, static fn () => $images->delete($stored['path'] ?? null));

        (new MenuService())->invalidateAll();

        return $id;
    }

    /**
     * @param array<string, mixed>                 $allergen     mevcut kayıt
     * @param array{code: string, is_active: int, sort_order: int} $data
     * @param array<string, array<string, string>> $translations
     */
    public function update(array $allergen, array $data, array $translations, ?UploadedFile $icon): void
    {
        $id    = (int) $allergen['id'];
        $model = new AllergenModel();

        if (preg_match('/^[a-z][a-z0-9_]{1,39}$/', $data['code']) !== 1) {
            throw ValidationException::single('code', lang('App.allergen.codeInvalid'));
        }

        if ($model->where('code', $data['code'])->where('id !=', $id)->countAllResults() > 0) {
            throw ValidationException::single('code', lang('App.allergen.codeTaken'));
        }

        $codes = $this->languageCodes();
        $this->assertTranslations($translations, $codes);

        $images = service('images');
        $stored = $icon === null ? null : $images->store($icon, 'allergen', null, 'icon');

        $this->atomic(function () use ($id, $data, $translations, $codes, $stored, $model): void {
            $fields = [
                'code'       => $data['code'],
                'is_active'  => $data['is_active'],
                'sort_order' => $data['sort_order'],
            ];

            if ($stored !== null) {
                $fields['icon_path'] = $stored['path'];
            }

            $model->update($id, $fields);
            $this->translations->sync(AllergenTranslationModel::class, 'allergen_id', $id, $translations, $codes, self::REQUIRED_LANG);
        }, static fn () => $images->delete($stored['path'] ?? null));

        // Commit sonrası: değiştirilen eski ikon diskten silinir (yol DB kaydından gelir).
        if ($stored !== null) {
            $images->delete($allergen['icon_path']);
        }

        (new MenuService())->invalidateAll();
    }

    /**
     * @param array<string, mixed> $allergen
     */
    public function toggle(array $allergen): void
    {
        (new AllergenModel())->update((int) $allergen['id'], [
            'is_active' => (int) $allergen['is_active'] === 1 ? 0 : 1,
        ]);

        (new MenuService())->invalidateAll();
    }

    /**
     * @param array<string, mixed> $allergen
     */
    public function deleteIcon(array $allergen): void
    {
        (new AllergenModel())->update((int) $allergen['id'], ['icon_path' => null]);
        service('images')->delete($allergen['icon_path']);

        (new MenuService())->invalidateAll();
    }

    /**
     * Herhangi bir üründe kullanılıyorsa silinemez, yalnızca pasifleştirilir.
     *
     * @param array<string, mixed> $allergen
     */
    public function delete(array $allergen): void
    {
        $id    = (int) $allergen['id'];
        $usage = db_connect()->table('product_allergens')->where('allergen_id', $id)->countAllResults();

        if ($usage > 0) {
            throw new BusinessRuleException(lang('App.allergen.inUse', [$usage]));
        }

        // Çeviriler FK CASCADE ile silinir.
        (new AllergenModel())->delete($id);
        service('images')->delete($allergen['icon_path']);

        (new MenuService())->invalidateAll();
    }

    /**
     * @param list<int> $ids
     */
    public function reorder(array $ids): void
    {
        $model = new AllergenModel();

        if ($model->whereIn('id', $ids)->countAllResults() !== count($ids)) {
            throw ValidationException::single('ids', lang('App.reorder.invalid'));
        }

        $this->atomic(static function () use ($ids, $model): void {
            foreach ($ids as $index => $id) {
                $model->update($id, ['sort_order' => $index]);
            }
        });

        (new MenuService())->invalidateAll();
    }

    /**
     * @return list<string>
     */
    private function languageCodes(): array
    {
        return array_map('strval', array_column($this->languages(), 'code'));
    }

    /**
     * @param array<string, array<string, string>> $translations
     * @param list<string>                         $codes
     */
    private function assertTranslations(array $translations, array $codes): void
    {
        $errors = $this->translations->validate(AllergenTranslationModel::class, $translations, $codes, self::REQUIRED_LANG);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
