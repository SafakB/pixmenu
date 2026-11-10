<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\LanguageModel;
use App\Services\LanguageService;
use CodeIgniter\HTTP\ResponseInterface;

final class LanguageController extends AdminBaseController
{
    private const RULES = [
        'name'        => 'required|max_length[60]',
        'native_name' => 'required|max_length[60]',
        'direction'   => 'required|in_list[ltr,rtl]',
    ];

    /**
     * View: admin/languages/index — languages[]
     */
    public function index(): string
    {
        return $this->render('admin/languages/index', [
            'languages' => (new LanguageModel())->ordered(),
        ]);
    }

    /**
     * View: admin/languages/form — language(null), action
     */
    public function new(): string
    {
        return $this->render('admin/languages/form', [
            'language' => null,
            'action'   => 'admin/languages',
        ]);
    }

    public function create(): ResponseInterface
    {
        return $this->handle(function (): ResponseInterface {
            $this->validateOrFail(self::RULES + ['code' => 'required|max_length[10]']);

            (new LanguageService())->create([
                'code'        => trim((string) $this->request->getPost('code')),
                'name'        => trim((string) $this->request->getPost('name')),
                'native_name' => trim((string) $this->request->getPost('native_name')),
                'direction'   => (string) $this->request->getPost('direction'),
                'is_active'   => $this->flag('is_active'),
                'sort_order'  => $this->sortOrder(),
            ]);

            return $this->done('admin/languages', lang('App.created'));
        });
    }

    /**
     * View: admin/languages/form — language, action
     */
    public function edit(int $id): string
    {
        $language = $this->requireLanguage($id);

        return $this->render('admin/languages/form', [
            'language' => $language,
            'action'   => 'admin/languages/' . $language['id'],
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $language = $this->requireLanguage($id);

        return $this->handle(function () use ($language): ResponseInterface {
            $this->validateOrFail(self::RULES);

            // `code` bilerek okunmaz: oluşturulduktan sonra değiştirilemez.
            (new LanguageService())->update($language, [
                'name'        => trim((string) $this->request->getPost('name')),
                'native_name' => trim((string) $this->request->getPost('native_name')),
                'direction'   => (string) $this->request->getPost('direction'),
                'is_active'   => $this->flag('is_active'),
                'sort_order'  => $this->sortOrder(),
            ]);

            return $this->done('admin/languages', lang('App.saved'));
        });
    }

    public function toggle(int $id): ResponseInterface
    {
        $language = $this->requireLanguage($id);

        return $this->handle(function () use ($language): ResponseInterface {
            (new LanguageService())->toggle($language);

            return $this->done('admin/languages', lang('App.statusChanged'));
        });
    }

    public function delete(int $id): ResponseInterface
    {
        $language = $this->requireLanguage($id);

        return $this->handle(function () use ($language): ResponseInterface {
            (new LanguageService())->delete($language);

            return $this->done('admin/languages', lang('App.deleted'));
        });
    }

    /**
     * JSON: {"ids":[3,1,2]}
     */
    public function reorder(): ResponseInterface
    {
        try {
            (new LanguageService())->reorder($this->jsonIds());
        } catch (\App\Exceptions\ValidationException $e) {
            return $this->jsonError(lang('App.validationFailed'), $e->errors(), 422);
        }

        return $this->jsonSuccess([], lang('App.reorder.saved'));
    }

    /**
     * @return array<string, mixed>
     */
    private function requireLanguage(int $id): array
    {
        return (new LanguageModel())->find($id) ?? $this->notFound();
    }
}
