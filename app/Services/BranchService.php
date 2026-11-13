<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Models\MenuTemplateModel;
use App\Models\RestaurantLanguageModel;
use App\Models\RestaurantModel;
use App\Services\Concerns\Transactional;
use RuntimeException;

/**
 * Şubeler (admin + owner) — spec §10.1.
 *
 * Şubenin kendi bilgilerinde (ad, adres, açıklama) zorunlu dil RESTORANIN
 * varsayılan dilidir (spec §8.2).
 */
final class BranchService
{
    use Transactional;

    public function __construct(
        private readonly Scope $scope,
        private readonly TranslationService $translations = new TranslationService(),
    ) {
    }

    /**
     * Restoranın aktif dilleri (form ve doğrulama için).
     *
     * @return list<array<string, mixed>>
     */
    public function languages(): array
    {
        return $this->scope->model(RestaurantLanguageModel::class)->languages(true);
    }

    /**
     * @param array{slug: string, default_language_id: int, phone: string, is_active: int, sort_order: int, template_code?: string} $data
     * @param array<string, array<string, string>>                                                        $translations
     */
    public function create(array $data, array $translations): int
    {
        [$codes, $requiredCode] = $this->prepare($data, $translations, null);

        // Şablonu yalnızca admin seçer; panelden açılan şube varsayılanı alır.
        $data['template_code'] ??= MenuTemplateModel::DEFAULT_CODE;

        if ($data['is_active'] === 1) {
            $this->subscriptions()->assertBranchLimit($this->scope->restaurantId());
        }

        $id = $this->atomic(function () use ($data, $translations, $codes, $requiredCode): int {
            $id = (int) $this->branches()->insert([
                'slug'                => $this->resolveSlug($data['slug'], $translations[$requiredCode]['name'] ?? '', null),
                'public_code'         => $this->newPublicCode(),
                'default_language_id' => $data['default_language_id'],
                'template_code'       => $data['template_code'],
                'phone'               => $data['phone'] === '' ? null : $data['phone'],
                'is_active'           => $data['is_active'],
                'sort_order'          => $data['sort_order'],
            ]);

            $this->translations->sync(BranchTranslationModel::class, 'branch_id', $id, $translations, $codes, $requiredCode);

            return $id;
        });

        (new MenuService())->invalidate($id);

        return $id;
    }

    /**
     * @param array<string, mixed>                                                                         $branch       mevcut kayıt (bu kapsamda yüklenmiş)
     * @param array{slug: string, default_language_id: int, phone: string, is_active: int, sort_order: int, template_code?: string} $data
     * @param array<string, array<string, string>>                                                        $translations
     */
    public function update(array $branch, array $data, array $translations): void
    {
        $id                     = (int) $branch['id'];
        [$codes, $requiredCode] = $this->prepare($data, $translations, $id, (string) ($branch['template_code'] ?? ''));

        if ($data['is_active'] === 1 && (int) $branch['is_active'] !== 1) {
            $this->subscriptions()->assertBranchLimit($this->scope->restaurantId(), $id);
        }

        $this->atomic(function () use ($id, $branch, $data, $translations, $codes, $requiredCode): void {
            $slug = $data['slug'] === ''
                ? (string) $branch['slug']
                : $this->resolveSlug($data['slug'], '', $id);

            $row = [
                'slug'                => $slug,
                'default_language_id' => $data['default_language_id'],
                'phone'               => $data['phone'] === '' ? null : $data['phone'],
                'is_active'           => $data['is_active'],
                'sort_order'          => $data['sort_order'],
            ];

            // Anahtar yoksa (panel) şablona dokunulmaz; admin seçimi korunur.
            if (isset($data['template_code'])) {
                $row['template_code'] = $data['template_code'];
            }

            $this->branches()->update($id, $row);

            $this->translations->sync(BranchTranslationModel::class, 'branch_id', $id, $translations, $codes, $requiredCode);
        });

        (new MenuService())->invalidate($id);
    }

    /**
     * @param array<string, mixed> $branch
     */
    public function toggle(array $branch): void
    {
        if ((int) $branch['is_active'] !== 1) {
            $this->subscriptions()->assertBranchLimit($this->scope->restaurantId(), (int) $branch['id']);
        }

        $this->branches()->update((int) $branch['id'], [
            'is_active' => (int) $branch['is_active'] === 1 ? 0 : 1,
        ]);

        (new MenuService())->invalidate((int) $branch['id']);
    }

    /**
     * Soft delete; slug'a `--deleted-{id}` eklenir (unique çakışmasın). Dosyalar kalır.
     *
     * @param array<string, mixed> $branch
     */
    public function delete(array $branch): void
    {
        $id = (int) $branch['id'];

        $this->atomic(function () use ($id, $branch): void {
            $this->branches()->update($id, ['slug' => pix_deleted_slug((string) $branch['slug'], $id)]);
            $this->branches()->delete($id);
        });

        (new MenuService())->invalidate($id);
    }

    /**
     * Yeni public_code üretir; eski QR kodları geçersiz olur.
     *
     * @param array<string, mixed> $branch
     */
    public function regenerateCode(array $branch): string
    {
        $code = $this->newPublicCode();

        $this->branches()->update((int) $branch['id'], ['public_code' => $code]);
        (new MenuService())->invalidate((int) $branch['id']);

        return $code;
    }

    // ------------------------------------------------------------------

    /**
     * Paket şube limiti (docs/ABONELIK_PLANI.md §6): admin ve panel aynı servisi kullandığı için tek nokta.
     */
    private function subscriptions(): SubscriptionService
    {
        return new SubscriptionService();
    }

    private function branches(): BranchModel
    {
        return $this->scope->model(BranchModel::class);
    }

    /**
     * Ortak doğrulama. [izinli dil kodları, zorunlu dil kodu] döndürür.
     *
     * @param array{slug: string, default_language_id: int, phone: string, is_active: int, sort_order: int, template_code?: string} $data
     * @param array<string, array<string, string>>                                                        $translations
     *
     * @return array{0: list<string>, 1: string}
     */
    private function prepare(array $data, array $translations, ?int $exceptId, ?string $currentTemplate = null): array
    {
        $languages = $this->languages();
        $byId      = array_column($languages, 'code', 'id');
        $codes     = array_map('strval', array_values($byId));
        $errors    = [];

        // default_language_id restoranın AKTİF dillerinden biri olmalı.
        if (! isset($byId[$data['default_language_id']])) {
            $errors['default_language_id'] = lang('App.language.notInRestaurant');
        }

        if (isset($data['template_code'])
            && ! (new MenuTemplateModel())->isSelectable($data['template_code'], $currentTemplate)) {
            $errors['template_code'] = lang('App.template.notSelectable');
        }

        if (mb_strlen($data['phone']) > 30) {
            $errors['phone'] = lang('App.field.phone');
        }

        if ($data['slug'] !== '') {
            if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $data['slug']) !== 1 || strlen($data['slug']) > 100) {
                $errors['slug'] = lang('App.restaurant.slugInvalid');
            } elseif ($this->branches()->slugExists($data['slug'], $exceptId)) {
                $errors['slug'] = lang('App.branch.slugTaken');
            }
        }

        $restaurant   = (new RestaurantModel())->find($this->scope->restaurantId());
        $requiredCode = (string) ($byId[(int) ($restaurant['default_language_id'] ?? 0)] ?? ($codes[0] ?? ''));

        $errors += $this->translations->validate(BranchTranslationModel::class, $translations, $codes, $requiredCode);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$codes, $requiredCode];
    }

    /**
     * Slug verilmişse onu (önceden doğrulanmış), verilmemişse isimden üretileni
     * restoran içinde tekil hale getirerek döndürür (-2, -3…).
     */
    private function resolveSlug(string $slug, string $name, ?int $exceptId): string
    {
        if ($slug !== '') {
            return $slug;
        }

        $base = pix_slug($name);
        $base = $base === '' ? 'sube' : $base;
        $slug = $base;

        for ($i = 2; $this->branches()->slugExists($slug, $exceptId); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    private function newPublicCode(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = bin2hex(random_bytes(5));

            if (! $this->branches()->publicCodeExistsGlobally($code)) {
                return $code;
            }
        }

        throw new RuntimeException('public_code üretilemedi.');
    }
}
