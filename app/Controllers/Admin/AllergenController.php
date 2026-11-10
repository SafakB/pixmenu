<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AllergenModel;
use App\Models\AllergenTranslationModel;
use App\Services\AllergenService;
use App\Services\TranslationService;
use CodeIgniter\HTTP\ResponseInterface;

final class AllergenController extends AdminBaseController
{
    /**
     * View: admin/allergens/index — allergens[{id, code, name, icon_url, is_active, sort_order}]
     */
    public function index(): string
    {
        $allergens = (new AllergenModel())->ordered();
        $resolver  = service('languageResolver');
        $names     = $resolver->resolveMany(
            AllergenTranslationModel::class,
            array_column($allergens, 'id'),
            $resolver->allergenChain(null, null),
        );

        return $this->render('admin/allergens/index', [
            'allergens' => array_map(static fn (array $allergen): array => [
                'id'         => (int) $allergen['id'],
                'code'       => $allergen['code'],
                'name'       => $names[(int) $allergen['id']]['name'] ?? $allergen['code'],
                'icon_url'   => upload_url($allergen['icon_path']),
                'is_active'  => (int) $allergen['is_active'] === 1,
                'sort_order' => (int) $allergen['sort_order'],
            ], $allergens),
        ]);
    }

    /**
     * View: admin/allergens/form — allergen(null), languages, translations, action
     */
    public function new(): string
    {
        return $this->form(null);
    }

    public function create(): ResponseInterface
    {
        return $this->handle(function (): ResponseInterface {
            $this->validateOrFail($this->rules());

            (new AllergenService())->create($this->input(), $this->translationsInput(), $this->uploaded('icon'));

            return $this->done('admin/allergens', lang('App.created'));
        });
    }

    /**
     * View: admin/allergens/form — allergen, languages, translations, action
     */
    public function edit(int $id): string
    {
        return $this->form($this->requireAllergen($id));
    }

    public function update(int $id): ResponseInterface
    {
        $allergen = $this->requireAllergen($id);

        return $this->handle(function () use ($allergen): ResponseInterface {
            $this->validateOrFail($this->rules());

            (new AllergenService())->update($allergen, $this->input(), $this->translationsInput(), $this->uploaded('icon'));

            return $this->done('admin/allergens', lang('App.saved'));
        });
    }

    public function toggle(int $id): ResponseInterface
    {
        $allergen = $this->requireAllergen($id);
        (new AllergenService())->toggle($allergen);

        return $this->done('admin/allergens', lang('App.statusChanged'));
    }

    public function deleteIcon(int $id): ResponseInterface
    {
        $allergen = $this->requireAllergen($id);
        (new AllergenService())->deleteIcon($allergen);

        return $this->done('admin/allergens/' . $allergen['id'] . '/edit', lang('App.upload.imageDeleted'));
    }

    public function delete(int $id): ResponseInterface
    {
        $allergen = $this->requireAllergen($id);

        return $this->handle(function () use ($allergen): ResponseInterface {
            (new AllergenService())->delete($allergen);

            return $this->done('admin/allergens', lang('App.deleted'));
        });
    }

    /**
     * JSON: {"ids":[3,1,2]}
     */
    public function reorder(): ResponseInterface
    {
        try {
            (new AllergenService())->reorder($this->jsonIds());
        } catch (\App\Exceptions\ValidationException $e) {
            return $this->jsonError(lang('App.validationFailed'), $e->errors(), 422);
        }

        return $this->jsonSuccess([], lang('App.reorder.saved'));
    }

    /**
     * @param array<string, mixed>|null $allergen
     */
    private function form(?array $allergen): string
    {
        $service      = new AllergenService();
        $translations = new TranslationService();
        $tr           = array_column($service->languages(), 'id', 'code')[AllergenService::REQUIRED_LANG] ?? 0;

        return $this->render('admin/allergens/form', [
            'allergen'     => $allergen,
            'icon_url'     => upload_url($allergen['icon_path'] ?? null),
            'languages'    => $translations->formLanguages($service->languages(), (int) $tr),
            'translations' => $allergen === null ? [] : $translations->load(AllergenTranslationModel::class, (int) $allergen['id']),
            'action'       => $allergen === null ? 'admin/allergens' : 'admin/allergens/' . $allergen['id'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return ['code' => 'required|max_length[40]'] + $this->imageRules('icon', 'allergen');
    }

    /**
     * @return array{code: string, is_active: int, sort_order: int}
     */
    private function input(): array
    {
        return [
            'code'       => trim((string) $this->request->getPost('code')),
            'is_active'  => $this->flag('is_active'),
            'sort_order' => $this->sortOrder(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requireAllergen(int $id): array
    {
        return (new AllergenModel())->find($id) ?? $this->notFound();
    }
}
