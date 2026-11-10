<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\LanguageModel;
use App\Services\Concerns\Transactional;

/**
 * Global dil yönetimi (yalnızca admin) — spec §4.4, §8.5, §10.1.
 */
final class LanguageService
{
    use Transactional;

    /** Bir dile referans veren tablolar: tablo => kolon. */
    private const REFERENCES = [
        'restaurants'             => 'default_language_id',
        'branches'                => 'default_language_id',
        'restaurant_languages'    => 'language_id',
        'allergen_translations'   => 'language_id',
        'restaurant_translations' => 'language_id',
        'branch_translations'     => 'language_id',
        'category_translations'   => 'language_id',
        'product_translations'    => 'language_id',
    ];

    /**
     * @param array{code: string, name: string, native_name: string, direction: string, is_active: int, sort_order: int} $data
     */
    public function create(array $data): int
    {
        if (preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/', $data['code']) !== 1) {
            throw ValidationException::single('code', lang('App.language.codeInvalid'));
        }

        $model = new LanguageModel();

        if ($model->findByCode($data['code']) !== null) {
            throw ValidationException::single('code', lang('App.language.codeTaken'));
        }

        return (int) $model->insert($data);
    }

    /**
     * `code` oluşturulduktan sonra DEĞİŞTİRİLEMEZ; $data içinde gelse de yok sayılır.
     *
     * @param array<string, mixed> $language mevcut kayıt
     * @param array{name: string, native_name: string, direction: string, is_active: int, sort_order: int} $data
     */
    public function update(array $language, array $data): void
    {
        $id = (int) $language['id'];

        if ((int) $language['is_active'] === 1 && $data['is_active'] === 0) {
            $this->assertNotDefaultAnywhere($id);
        }

        (new LanguageModel())->update($id, [
            'name'        => $data['name'],
            'native_name' => $data['native_name'],
            'direction'   => $data['direction'],
            'is_active'   => $data['is_active'],
            'sort_order'  => $data['sort_order'],
        ]);

        (new MenuService())->invalidateAll();
    }

    /**
     * @param array<string, mixed> $language
     */
    public function toggle(array $language): void
    {
        $id      = (int) $language['id'];
        $newFlag = (int) $language['is_active'] === 1 ? 0 : 1;

        if ($newFlag === 0) {
            $this->assertNotDefaultAnywhere($id);
        }

        (new LanguageModel())->update($id, ['is_active' => $newFlag]);
        (new MenuService())->invalidateAll();
    }

    /**
     * Başka bir tabloda referansı varsa silinemez (yalnızca pasifleştirilir).
     *
     * @param array<string, mixed> $language
     */
    public function delete(array $language): void
    {
        $id = (int) $language['id'];
        $db = db_connect();

        foreach (self::REFERENCES as $table => $column) {
            if ($db->table($table)->where($column, $id)->countAllResults() > 0) {
                throw new BusinessRuleException(lang('App.language.inUseDeactivate'));
            }
        }

        (new LanguageModel())->delete($id);
        (new MenuService())->invalidateAll();
    }

    /**
     * @param list<int> $ids
     */
    public function reorder(array $ids): void
    {
        $model = new LanguageModel();

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
     * Dil, silinmemiş bir restoranın veya şubenin varsayılan diliyse pasifleştirilemez.
     */
    private function assertNotDefaultAnywhere(int $languageId): void
    {
        $db = db_connect();

        $restaurants = $db->table('restaurants')->where('default_language_id', $languageId)->where('deleted_at', null)->countAllResults();
        $branches    = $db->table('branches')->where('default_language_id', $languageId)->where('deleted_at', null)->countAllResults();

        if ($restaurants + $branches > 0) {
            throw new BusinessRuleException(lang('App.language.defaultInUse'));
        }
    }
}
