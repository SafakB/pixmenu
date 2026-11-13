<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Exceptions\ValidationException;
use App\Models\CurrencyModel;
use App\Models\PlanModel;
use App\Models\PlanTranslationModel;
use App\Services\PlanService;
use App\Services\TranslationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Paket yönetimi — docs/ABONELIK_PLANI.md §2, §8.
 */
final class PlanController extends AdminBaseController
{
    /**
     * View: admin/plans/index — plans[{id, code, name, description, max_branches, allow_custom_domain, allow_custom_design,
     *       trial_days, price_monthly, price_yearly, currency_code, is_featured, is_active, sort_order, current_count}]
     */
    public function index(): string
    {
        return $this->render('admin/plans/index', [
            'plans' => (new PlanService())->listing(),
        ]);
    }

    /**
     * View: admin/plans/form — plan(null), languages, translations, action
     */
    public function new(): string
    {
        return $this->form(null);
    }

    public function create(): ResponseInterface
    {
        return $this->handle(function (): ResponseInterface {
            (new PlanService())->create($this->input(), $this->translationsInput());

            return $this->done('admin/plans', lang('App.created'));
        });
    }

    /**
     * View: admin/plans/form — plan, languages, translations, action
     */
    public function edit(int $id): string
    {
        return $this->form($this->requirePlan($id));
    }

    public function update(int $id): ResponseInterface
    {
        $plan = $this->requirePlan($id);

        return $this->handle(function () use ($plan): ResponseInterface {
            (new PlanService())->update($plan, $this->input(), $this->translationsInput());

            return $this->done('admin/plans', lang('App.saved'));
        });
    }

    public function toggle(int $id): ResponseInterface
    {
        $plan = $this->requirePlan($id);

        return $this->handle(function () use ($plan): ResponseInterface {
            (new PlanService())->toggle($plan);

            return $this->done('admin/plans', lang('App.statusChanged'));
        });
    }

    public function delete(int $id): ResponseInterface
    {
        $plan = $this->requirePlan($id);

        return $this->handle(function () use ($plan): ResponseInterface {
            (new PlanService())->delete($plan);

            return $this->done('admin/plans', lang('App.deleted'));
        });
    }

    /**
     * JSON: {"ids":[3,1,2]}
     */
    public function reorder(): ResponseInterface
    {
        try {
            (new PlanService())->reorder($this->jsonIds());
        } catch (ValidationException $e) {
            return $this->jsonError(lang('App.validationFailed'), $e->errors(), 422);
        }

        return $this->jsonSuccess([], lang('App.reorder.saved'));
    }

    /**
     * @param array<string, mixed>|null $plan
     */
    private function form(?array $plan): string
    {
        $service      = new PlanService();
        $translations = new TranslationService();
        $tr           = array_column($service->languages(), 'id', 'code')[PlanService::REQUIRED_LANG] ?? 0;

        return $this->render('admin/plans/form', [
            'plan'         => $plan,
            'languages'    => $translations->formLanguages($service->languages(), (int) $tr),
            'translations' => $plan === null ? [] : $translations->load(PlanTranslationModel::class, (int) $plan['id']),
            'action'       => $plan === null ? 'admin/plans' : 'admin/plans/' . $plan['id'],
            'currencies'   => (new CurrencyModel())->options($plan['currency_code'] ?? null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        $post = fn (string $key): string => trim((string) $this->request->getPost($key));

        return [
            'code'                => strtolower($post('code')),
            'badge_class'         => $post('badge_class'),
            'max_branches'        => $post('max_branches'),
            'trial_days'          => $post('trial_days'),
            'price_monthly'       => $post('price_monthly'),
            'price_yearly'        => $post('price_yearly'),
            'currency_code'       => $post('currency_code'),
            'allow_custom_domain' => $this->flag('allow_custom_domain'),
            'allow_custom_design' => $this->flag('allow_custom_design'),
            'is_featured'         => $this->flag('is_featured'),
            'is_active'           => $this->flag('is_active'),
            'sort_order'          => $this->sortOrder(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePlan(int $id): array
    {
        return (new PlanModel())->find($id) ?? $this->notFound();
    }
}
