<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LanguageModel;
use App\Models\TranslationModel;

/**
 * Çeviri okuma ve fallback (spec §8.3) + ziyaretçi dil seçimi (spec §8.4).
 */
final class LanguageResolver
{
    /** @var array<string, int>|null */
    private ?array $allergenFallbackIds = null;

    /**
     * Fallback zinciri: verilen dil ID'leri sırayla, null'lar ve tekrarlar atılarak.
     *
     * İçerik için: istenen dil → şubenin varsayılan dili → restoranın varsayılan dili.
     *
     * @return list<int>
     */
    public function chain(?int ...$languageIds): array
    {
        $chain = [];

        foreach ($languageIds as $id) {
            if ($id !== null && $id > 0 && ! in_array($id, $chain, true)) {
                $chain[] = $id;
            }
        }

        return $chain;
    }

    /**
     * Alerjen zinciri: istenen dil → şubenin varsayılan dili → tr → en.
     *
     * @return list<int>
     */
    public function allergenChain(?int $requestedId, ?int $branchDefaultId): array
    {
        $this->allergenFallbackIds ??= (new LanguageModel())->idsByCodes(['tr', 'en']);

        return $this->chain(
            $requestedId,
            $branchDefaultId,
            $this->allergenFallbackIds['tr'] ?? null,
            $this->allergenFallbackIds['en'] ?? null,
        );
    }

    /**
     * Bir kaydın çevirilerinden zincire göre ilk uygun olanı seçer;
     * zincirdeki hiçbir dilde yoksa MEVCUT İLK çeviriyi döndürür.
     *
     * @param array<int, array<string, mixed>> $rowsByLanguageId language_id => satır (eklenme sırasıyla)
     * @param list<int>                        $chain
     *
     * @return array<string, mixed>|null
     */
    public function pick(array $rowsByLanguageId, array $chain): ?array
    {
        foreach ($chain as $languageId) {
            if (isset($rowsByLanguageId[$languageId])) {
                return $rowsByLanguageId[$languageId];
            }
        }

        if ($rowsByLanguageId === []) {
            return null;
        }

        return $rowsByLanguageId[array_key_first($rowsByLanguageId)];
    }

    /**
     * Birden çok ebeveyn için çevirileri N+1 olmadan çözer.
     *
     * Ebeveyn ID'leri çağıran tarafça tenant kapsamlı modelle elde edilmiş
     * olmalıdır (spec §2.1.7). Önce yalnızca zincirdeki diller çekilir; zincirde
     * hiç çevirisi olmayan kayıtlar için tek bir ek sorgu ile ilk çeviri alınır.
     *
     * @param class-string<TranslationModel> $translationModel
     * @param list<int>                      $parentIds
     * @param list<int>                      $chain
     *
     * @return array<int, array<string, mixed>> parent_id => çeviri satırı
     */
    public function resolveMany(string $translationModel, array $parentIds, array $chain): array
    {
        $parentIds = array_values(array_unique(array_map('intval', $parentIds)));

        if ($parentIds === []) {
            return [];
        }

        $fk      = $translationModel::FK;
        $grouped = [];

        if ($chain !== []) {
            $rows = (new $translationModel())
                ->whereIn($fk, $parentIds)
                ->whereIn('language_id', $chain)
                ->orderBy('id', 'ASC')
                ->findAll();

            foreach ($rows as $row) {
                $grouped[(int) $row[$fk]][(int) $row['language_id']] = $row;
            }
        }

        $missing = array_values(array_diff($parentIds, array_keys($grouped)));

        if ($missing !== []) {
            $rows = (new $translationModel())->whereIn($fk, $missing)->orderBy('id', 'ASC')->findAll();

            foreach ($rows as $row) {
                $grouped[(int) $row[$fk]][(int) $row['language_id']] = $row;
            }
        }

        $resolved = [];

        foreach ($grouped as $parentId => $byLanguage) {
            $picked = $this->pick($byLanguage, $chain);

            if ($picked !== null) {
                $resolved[$parentId] = $picked;
            }
        }

        return $resolved;
    }

    /**
     * Tek ebeveyn için çözülmüş çeviri.
     *
     * @param class-string<TranslationModel> $translationModel
     * @param list<int>                      $chain
     *
     * @return array<string, mixed>|null
     */
    public function resolveOne(string $translationModel, int $parentId, array $chain): ?array
    {
        return $this->resolveMany($translationModel, [$parentId], $chain)[$parentId] ?? null;
    }

    /**
     * Ziyaretçi dili (spec §8.4):
     *  1. ?lang=xx restoranın AKTİF dillerindeyse kullanılır (ve çereze yazılmalıdır),
     *  2. yoksa çerez değeri (hâlâ geçerliyse),
     *  3. yoksa şubenin varsayılan dili.
     * Accept-Language bilinçli olarak KULLANILMAZ.
     *
     * @param list<array<string, mixed>> $activeLanguages id + code içeren aktif diller
     *
     * @return array{language: array<string, mixed>|null, persist: bool}
     */
    public function visitorLanguage(array $activeLanguages, int $branchDefaultLanguageId, ?string $queryLang, ?string $cookieLang): array
    {
        $byCode = [];
        $byId   = [];

        foreach ($activeLanguages as $language) {
            $byCode[(string) $language['code']] = $language;
            $byId[(int) $language['id']]        = $language;
        }

        if ($queryLang !== null && $queryLang !== '' && isset($byCode[$queryLang])) {
            return ['language' => $byCode[$queryLang], 'persist' => true];
        }

        if ($cookieLang !== null && $cookieLang !== '' && isset($byCode[$cookieLang])) {
            return ['language' => $byCode[$cookieLang], 'persist' => false];
        }

        // Şubenin varsayılan dili pasifleşmiş olabilir; o durumda ilk aktif dil.
        $language = $byId[$branchDefaultLanguageId] ?? ($activeLanguages[0] ?? null);

        return ['language' => $language, 'persist' => false];
    }
}
