<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Models\CurrencyModel;
use App\Models\LanguageModel;
use App\Models\PlanModel;
use App\Models\RestaurantLanguageModel;
use App\Models\RestaurantModel;
use App\Models\RestaurantTranslationModel;
use App\Models\RestaurantUserBranchModel;
use App\Models\RestaurantUserModel;
use App\Models\InvoiceModel;
use App\Models\SubscriptionModel;
use App\Services\DomainService;
use App\Services\PlanService;
use App\Services\RestaurantService;
use App\Services\SiteService;
use App\Services\SubscriptionService;
use App\Services\TranslationService;
use CodeIgniter\HTTP\ResponseInterface;

final class RestaurantController extends AdminBaseController
{
    /**
     * View: admin/restaurants/index — restaurants[{id, slug, name, currency_code, is_active,
     *       plan_name|null, plan_badge_class|null, subscription_status|null, suspended (bool), suspension_reason|null}]
     */
    public function index(): string
    {
        $restaurants = (new RestaurantModel())->orderBy('id', 'DESC')->findAll();
        $chains      = [];
        $current     = [];

        foreach ((new SubscriptionModel())->withoutTenantScope()->where('is_current', 1)->findAll() as $sub) {
            $current[(int) $sub['restaurant_id']] = $sub;
        }

        $planIds    = array_values(array_unique(array_map('intval', array_column($current, 'plan_id'))));
        $planNames  = (new PlanService())->texts($planIds);
        $planBadges = $planIds === [] ? [] : array_column((new PlanModel())->select('id, badge_class')->whereIn('id', $planIds)->findAll(), 'badge_class', 'id');

        foreach ($restaurants as $restaurant) {
            $chains[(int) $restaurant['id']] = [(int) $restaurant['default_language_id']];
        }

        $names = service('languageResolver')->resolveManyWithChains(RestaurantTranslationModel::class, $chains);

        return $this->render('admin/restaurants/index', [
            'restaurants' => array_map(static fn (array $restaurant): array => [
                'id'            => (int) $restaurant['id'],
                'slug'          => $restaurant['slug'],
                'name'          => $names[(int) $restaurant['id']]['name'] ?? $restaurant['slug'],
                'currency_code' => $restaurant['currency_code'],
                'is_active'     => (int) $restaurant['is_active'] === 1,
                'plan_name'     => isset($current[(int) $restaurant['id']])
                    ? ($planNames[(int) $current[(int) $restaurant['id']]['plan_id']]['name'] ?? null)
                    : null,
                'plan_badge_class' => isset($current[(int) $restaurant['id']])
                    ? (($planBadges[(int) $current[(int) $restaurant['id']]['plan_id']] ?? '') ?: 'bg-primary')
                    : null,
                'subscription_status' => $current[(int) $restaurant['id']]['status'] ?? null,
                'suspended'           => $restaurant['suspended_at'] !== null,
                'suspension_reason'   => $restaurant['suspension_reason'],
            ], $restaurants),
        ]);
    }

    /**
     * View: admin/restaurants/new — languages[] (tüm aktif diller; çeviri alanları ve dil seçimi için),
     *       plans[{id, code, name, trial_days, price_monthly, price_yearly, currency_code}] (aktif), defaultPlanId,
     *       currencies[{code, name, symbol}] (aktif)
     */
    public function new(): string
    {
        $languages = (new LanguageModel())->ordered(true);
        $plans     = (new PlanService())->listing(true);
        $default   = array_values(array_filter($plans, static fn (array $p): bool => $p['code'] === config('Billing')->defaultPlanCode));

        return $this->render('admin/restaurants/new', [
            'allLanguages'  => $languages,
            'languages'     => (new TranslationService())->formLanguages($languages, 0),
            'plans'         => $plans,
            'defaultPlanId' => isset($default[0]) ? (int) $default[0]['id'] : null,
            'currencies'    => (new CurrencyModel())->options(),
        ]);
    }

    public function create(): ResponseInterface
    {
        return $this->handle(function (): ResponseInterface {
            $this->validateOrFail([
                'default_language_id' => 'required|is_natural_no_zero',
                'currency_code'       => 'required|exact_length[3]',
            ] + $this->imageRules('logo', 'logo'));

            $ownerEmail = trim((string) $this->request->getPost('owner_email'));
            $owner      = $ownerEmail === '' ? null : [
                'name'     => (string) $this->request->getPost('owner_name'),
                'email'    => $ownerEmail,
                'password' => (string) $this->request->getPost('owner_password'),
                // Admin'in girdiği adres güvenilir kabul edilir (Google bağlamada şifre korunur).
                'email_verified' => true,
            ];

            $id = (new RestaurantService())->create(
                [
                    'slug'                => trim((string) $this->request->getPost('slug')),
                    'default_language_id' => (int) $this->request->getPost('default_language_id'),
                    'currency_code'       => strtoupper(trim((string) $this->request->getPost('currency_code'))),
                    'is_active'           => $this->flag('is_active'),
                    'language_ids'        => $this->intList('language_ids'),
                    'plan_id'             => (int) $this->request->getPost('plan_id') ?: null,
                    'billing_cycle'       => (string) $this->request->getPost('billing_cycle'),
                ],
                $this->translationsInput(),
                $this->uploaded('logo'),
                $owner,
            );

            $activity = service('activity');
            $activity->restaurant($id);
            $activity->detail(['id' => $id, 'slug' => trim((string) $this->request->getPost('slug'))] + ($ownerEmail === '' ? [] : ['owner' => $ownerEmail]));

            return $this->done('admin/restaurants/' . $id, lang('App.created'), ['id' => $id]);
        });
    }

    /**
     * View: admin/restaurants/show — restaurant{id, slug, name, currency_code, is_active, logo_url},
     *       counts{branches, users, languages},
     *       branches[{id, slug, name, public_code, menu_url, is_active}],
     *       users[{id, name, email, role, is_active, branch_count}],
     *       languages[{id, code, native_name, is_default}] (restoranın aktif dilleri),
     *       billing{summary (SubscriptionService::summary), suspended, suspension_reason, open_invoices, overdue_invoices, domain|null}
     */
    public function show(int $id): string
    {
        $restaurant = $this->requireRestaurant($id);
        $defaultId  = (int) $restaurant['default_language_id'];
        $resolver   = service('languageResolver');
        $name       = $resolver->resolveOne(RestaurantTranslationModel::class, $id, [$defaultId])['name'] ?? $restaurant['slug'];

        $branches    = (new BranchModel())->forRestaurant($id)->accessible(null);
        $branchNames = $resolver->resolveMany(
            BranchTranslationModel::class,
            array_map('intval', array_column($branches, 'id')),
            [$defaultId],
        );
        $users     = (new RestaurantUserModel())->forRestaurant($id)->ordered();
        $branchMap = (new RestaurantUserBranchModel())->forRestaurant($id)
            ->branchIdsForUsers(array_map('intval', array_column($users, 'id')));
        $languages = (new RestaurantLanguageModel())->forRestaurant($id)->languages();

        $invoices = (new InvoiceModel())->forRestaurant($id)->pending();
        $today    = date('Y-m-d');
        $menuBase = (new SiteService())->canonicalBase($id, (string) $restaurant['slug']);

        return $this->render('admin/restaurants/show', [
            'billing' => [
                'summary'           => (new SubscriptionService())->summary($id),
                'suspended'         => $restaurant['suspended_at'] !== null,
                'suspension_reason' => $restaurant['suspension_reason'],
                'open_invoices'     => count($invoices),
                'overdue_invoices'  => count(array_filter($invoices, static fn (array $i): bool => (string) $i['due_date'] < $today)),
                'domain'            => (new DomainService())->current($id),
            ],
            'restaurant' => [
                'id'            => $id,
                'slug'          => $restaurant['slug'],
                'name'          => $name,
                'currency_code' => $restaurant['currency_code'],
                'is_active'     => (int) $restaurant['is_active'] === 1,
                'logo_url'      => upload_url($restaurant['logo_path']),
            ],
            'counts' => [
                'branches'  => count($branches),
                'users'     => count($users),
                'languages' => count((new RestaurantLanguageModel())->forRestaurant($id)->languages(false)),
            ],
            'branches' => array_map(static fn (array $branch): array => [
                'id'          => (int) $branch['id'],
                'slug'        => $branch['slug'],
                'name'        => $branchNames[(int) $branch['id']]['name'] ?? $branch['slug'],
                'public_code' => $branch['public_code'],
                'menu_url'    => SiteService::branchUrl($menuBase, $branch),
                'is_active'   => (int) $branch['is_active'] === 1,
            ], $branches),
            'users' => array_map(static fn (array $user): array => [
                'id'           => (int) $user['id'],
                'name'         => $user['name'],
                'email'        => $user['email'],
                'role'         => $user['role'],
                'is_active'    => (int) $user['is_active'] === 1,
                'branch_count' => count($branchMap[(int) $user['id']] ?? []),
            ], $users),
            'languages' => array_map(static fn (array $language): array => [
                'id'          => $language['id'],
                'code'        => $language['code'],
                'native_name' => $language['native_name'],
                'is_default'  => $language['id'] === $defaultId,
            ], $languages),
        ]);
    }

    /**
     * View: admin/restaurants/edit — restaurant, logo_url, languages, translations,
     *       currencies[{code, name, symbol}] (aktif + mevcut), currencyLocked (ürün varsa true)
     */
    public function edit(int $id): string
    {
        $restaurant = $this->requireRestaurant($id);
        $service    = new TranslationService();

        return $this->render('admin/restaurants/edit', [
            'restaurant'   => $restaurant,
            'logo_url'     => upload_url($restaurant['logo_path']),
            'languages'    => $service->formLanguages(
                (new RestaurantLanguageModel())->forRestaurant($id)->languages(true),
                (int) $restaurant['default_language_id'],
            ),
            'translations'   => $service->load(RestaurantTranslationModel::class, $id),
            'currencies'     => (new CurrencyModel())->options((string) $restaurant['currency_code']),
            'currencyLocked' => (new RestaurantService())->hasProducts($id),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $restaurant = $this->requireRestaurant($id);

        return $this->handle(function () use ($restaurant, $id): ResponseInterface {
            $this->validateOrFail([
                'default_language_id' => 'required|is_natural_no_zero',
                'currency_code'       => 'required|exact_length[3]',
            ] + $this->imageRules('logo', 'logo'));

            (new RestaurantService())->update(
                $restaurant,
                [
                    'slug'                => trim((string) $this->request->getPost('slug')),
                    'default_language_id' => (int) $this->request->getPost('default_language_id'),
                    'currency_code'       => strtoupper(trim((string) $this->request->getPost('currency_code'))),
                    'is_active'           => $this->flag('is_active'),
                ],
                $this->translationsInput(),
                $this->uploaded('logo'),
            );

            return $this->done('admin/restaurants/' . $id, lang('App.saved'));
        });
    }

    public function toggle(int $id): ResponseInterface
    {
        $restaurant = $this->requireRestaurant($id);
        (new RestaurantService())->toggle($restaurant);
        service('activity')->detail(['is_active' => (int) $restaurant['is_active'] === 1 ? 0 : 1]);

        return $this->done('admin/restaurants', lang('App.statusChanged'));
    }

    public function delete(int $id): ResponseInterface
    {
        $restaurant = $this->requireRestaurant($id);
        (new RestaurantService())->delete($restaurant);
        service('activity')->detail(['slug' => $restaurant['slug']]);

        return $this->done('admin/restaurants', lang('App.deleted'));
    }
}
