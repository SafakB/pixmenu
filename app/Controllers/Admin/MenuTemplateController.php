<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Exceptions\ValidationException;
use App\Models\BranchModel;
use App\Models\MenuTemplateModel;
use App\Services\MenuTemplateService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Menü tasarım şablonları (global). Şubeye atama şube formundan yapılır.
 * Tasarımlar ön uç projesindedir; burada yalnızca katalog ve kodlar tutulur.
 */
final class MenuTemplateController extends AdminBaseController
{
    private const RULES = [
        'name'        => 'required|max_length[60]',
        'description' => 'permit_empty|max_length[255]',
    ];

    /**
     * View: admin/templates/index — templates[{code, name, description, is_active, sort_order, branch_count, is_default}]
     */
    public function index(): string
    {
        $usage = (new BranchModel())->templateUsageCounts();

        return $this->render('admin/templates/index', [
            'templates' => array_map(static fn (array $t): array => $t + [
                'branch_count' => $usage[$t['code']] ?? 0,
                'is_default'   => $t['code'] === MenuTemplateModel::DEFAULT_CODE,
            ], (new MenuTemplateModel())->ordered()),
        ]);
    }

    /**
     * View: admin/templates/form — template(null), action
     */
    public function new(): string
    {
        return $this->render('admin/templates/form', [
            'template' => null,
            'action'   => 'admin/templates',
        ]);
    }

    public function create(): ResponseInterface
    {
        return $this->handle(function (): ResponseInterface {
            $this->validateOrFail(self::RULES + ['code' => 'required|max_length[30]']);

            (new MenuTemplateService())->create([
                'code' => trim((string) $this->request->getPost('code')),
            ] + $this->input());

            return $this->done('admin/templates', lang('App.created'));
        });
    }

    /**
     * View: admin/templates/form — template (+ is_default), action
     */
    public function edit(string $code): string
    {
        $template = $this->requireTemplate($code);

        return $this->render('admin/templates/form', [
            'template' => $template + [
                'is_default' => $template['code'] === MenuTemplateModel::DEFAULT_CODE,
            ],
            'action'   => 'admin/templates/' . $template['code'],
        ]);
    }

    public function update(string $code): ResponseInterface
    {
        $template = $this->requireTemplate($code);

        return $this->handle(function () use ($template): ResponseInterface {
            $this->validateOrFail(self::RULES);

            // `code` bilerek okunmaz: oluşturulduktan sonra değiştirilemez.
            (new MenuTemplateService())->update($template, $this->input());

            return $this->done('admin/templates', lang('App.saved'));
        });
    }

    public function toggle(string $code): ResponseInterface
    {
        $template = $this->requireTemplate($code);

        return $this->handle(function () use ($template): ResponseInterface {
            (new MenuTemplateService())->toggle($template);

            return $this->done('admin/templates', lang('App.statusChanged'));
        });
    }

    public function delete(string $code): ResponseInterface
    {
        $template = $this->requireTemplate($code);

        return $this->handle(function () use ($template): ResponseInterface {
            (new MenuTemplateService())->delete($template);

            return $this->done('admin/templates', lang('App.deleted'));
        });
    }

    /**
     * JSON: {"ids":["standart","modern"]} — anahtar kod olduğu için string listesi.
     */
    public function reorder(): ResponseInterface
    {
        try {
            (new MenuTemplateService())->reorder($this->jsonCodes());
        } catch (ValidationException $e) {
            return $this->jsonError(lang('App.validationFailed'), $e->errors(), 422);
        }

        return $this->jsonSuccess([], lang('App.reorder.saved'));
    }

    /**
     * @return array{name: string, description: string, is_active: int, sort_order: int}
     */
    private function input(): array
    {
        return [
            'name'        => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
            'is_active'   => $this->flag('is_active'),
            'sort_order'  => $this->sortOrder(),
        ];
    }

    /**
     * @return list<string>
     */
    private function jsonCodes(): array
    {
        try {
            $body = $this->request->getJSON(true);
        } catch (\Throwable) {
            $body = null;
        }

        $codes = is_array($body) ? ($body['ids'] ?? null) : null;

        if (! is_array($codes) || $codes === [] || count($codes) > 1000) {
            throw ValidationException::single('ids', lang('App.reorder.invalid'));
        }

        foreach ($codes as $code) {
            if (! is_string($code) || preg_match(MenuTemplateService::CODE_PATTERN, $code) !== 1) {
                throw ValidationException::single('ids', lang('App.reorder.invalid'));
            }
        }

        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::single('ids', lang('App.reorder.invalid'));
        }

        return array_values($codes);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireTemplate(string $code): array
    {
        if (preg_match(MenuTemplateService::CODE_PATTERN, $code) !== 1) {
            $this->notFound();
        }

        return (new MenuTemplateModel())->find($code) ?? $this->notFound();
    }
}
