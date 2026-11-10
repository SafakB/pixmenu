<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\LanguageModel;
use App\Models\TranslationModel;
use InvalidArgumentException;

/**
 * Çeviri yazma/okuma (spec §8.2).
 *
 * Çeviri tablolarına doğrudan erişilmez: $parentId, çağıran servis tarafından
 * tenant kapsamlı modelle doğrulanmış bir ebeveyn kaydın ID'si olmalıdır.
 */
final class TranslationService
{
    /**
     * Girdiyi doğrular; hata listesi döndürür (boşsa geçerli).
     *
     * @param class-string<TranslationModel>       $translationModel
     * @param array<string, array<string, string>> $input            translations[code][field]
     * @param list<string>                         $allowedLangCodes
     *
     * @return array<string, string> "translations.{code}.{field}" => mesaj
     */
    public function validate(string $translationModel, array $input, array $allowedLangCodes, string $requiredLangCode): array
    {
        $errors = [];

        if (trim((string) ($input[$requiredLangCode]['name'] ?? '')) === '' || ! in_array($requiredLangCode, $allowedLangCodes, true)) {
            $errors["translations.{$requiredLangCode}.name"] = lang('App.translation.nameRequired', [$requiredLangCode]);
        }

        foreach ($allowedLangCodes as $code) {
            if (! isset($input[$code]) || ! is_array($input[$code])) {
                continue;
            }

            foreach ($translationModel::TEXT_FIELDS as $field => $maxLength) {
                $value = trim((string) ($input[$code][$field] ?? ''));

                if ($maxLength !== null && mb_strlen($value) > $maxLength) {
                    $errors["translations.{$code}.{$field}"] = lang('App.translation.tooLong', [$code, $field, $maxLength]);
                } elseif ($maxLength === null && mb_strlen($value) > 20000) {
                    $errors["translations.{$code}.{$field}"] = lang('App.translation.tooLong', [$code, $field, 20000]);
                }
            }
        }

        return $errors;
    }

    /**
     * Çevirileri eşitler. Çağıran servis bunu KENDİ transaction'ı içinde çağırır.
     *
     *  1. $input anahtarları $allowedLangCodes ile kesiştirilir; izinsiz diller yok sayılır.
     *  2. $requiredLangCode için `name` zorunludur.
     *  3. Diğer dillerde `name` boşsa o dilin satırı silinir (fallback devreye girer).
     *  4. Var olan satırlar güncellenir, yenileri eklenir.
     *
     * @param class-string<TranslationModel>       $translationModel
     * @param array<string, array<string, string>> $input
     * @param list<string>                         $allowedLangCodes
     *
     * @throws ValidationException
     */
    public function sync(
        string $translationModel,
        string $fkColumn,
        int $parentId,
        array $input,
        array $allowedLangCodes,
        string $requiredLangCode,
    ): void {
        if ($fkColumn !== $translationModel::FK) {
            throw new InvalidArgumentException($translationModel . ' için beklenen FK kolonu: ' . $translationModel::FK);
        }

        if ($parentId <= 0) {
            throw new InvalidArgumentException('Geçersiz ebeveyn kimliği.');
        }

        $errors = $this->validate($translationModel, $input, $allowedLangCodes, $requiredLangCode);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $codes = array_values(array_intersect($allowedLangCodes, array_map('strval', array_keys($input))));
        $ids   = (new LanguageModel())->idsByCodes($codes);
        $model = new $translationModel();

        $existing = [];

        foreach ($model->where($fkColumn, $parentId)->findAll() as $row) {
            $existing[(int) $row['language_id']] = (int) $row['id'];
        }

        foreach ($codes as $code) {
            if (! isset($ids[$code])) {
                continue;
            }

            $languageId = $ids[$code];
            $name       = trim((string) ($input[$code]['name'] ?? ''));

            if ($name === '') {
                // Zorunlu dil yukarıda doğrulandı; buraya yalnızca diğer diller düşer.
                if (isset($existing[$languageId])) {
                    $model->delete($existing[$languageId]);
                }

                continue;
            }

            $data = [];

            foreach (array_keys($translationModel::TEXT_FIELDS) as $field) {
                $value        = trim((string) ($input[$code][$field] ?? ''));
                $data[$field] = ($value === '' && $field !== 'name') ? null : $value;
            }

            if (isset($existing[$languageId])) {
                $model->update($existing[$languageId], $data);
            } else {
                $model->insert($data + [$fkColumn => $parentId, 'language_id' => $languageId]);
            }
        }
    }

    /**
     * Düzenleme formu için: bir ebeveynin tüm çevirileri, dil koduna göre.
     *
     * @param class-string<TranslationModel> $translationModel
     *
     * @return array<string, array<string, string|null>> code => alanlar
     */
    public function load(string $translationModel, int $parentId): array
    {
        $model = new $translationModel();
        $table = $model->getTable();
        $rows  = $model
            ->select($table . '.*, languages.code AS language_code')
            ->join('languages', 'languages.id = ' . $table . '.language_id')
            ->where($translationModel::FK, $parentId)
            ->findAll();

        $result = [];

        foreach ($rows as $row) {
            $fields = [];

            foreach (array_keys($translationModel::TEXT_FIELDS) as $field) {
                $fields[$field] = $row[$field];
            }

            $result[(string) $row['language_code']] = $fields;
        }

        return $result;
    }

    /**
     * View'a giden dil listesi (spec §8.2).
     *
     * @param list<array<string, mixed>> $languages id, code, native_name, direction içeren diller
     *
     * @return list<array{id: int, code: string, native_name: string, direction: string, is_default: bool}>
     */
    public function formLanguages(array $languages, int $defaultLanguageId): array
    {
        return array_map(static fn (array $language): array => [
            'id'          => (int) $language['id'],
            'code'        => (string) $language['code'],
            'native_name' => (string) $language['native_name'],
            'direction'   => (string) $language['direction'],
            'is_default'  => (int) $language['id'] === $defaultLanguageId,
        ], $languages);
    }

    /**
     * Bir ebeveynin tüm çevirilerini siler (kalıcı silmede; normalde FK CASCADE yapar).
     *
     * @param class-string<TranslationModel> $translationModel
     */
    public function deleteAll(string $translationModel, int $parentId): void
    {
        (new $translationModel())->where($translationModel::FK, $parentId)->delete();
    }
}
