<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\RestaurantUserBranchModel;
use App\Services\BranchService;
use App\Services\Scope;
use App\Services\SiteService;
use App\Services\SubscriptionService;
use App\Services\TranslationService;
use CodeIgniter\HTTP\ResponseInterface;

final class BranchController extends PanelBaseController
{
    /**
     * Owner: tüm şubeler; manager: yalnızca atanmış şubeler.
     *
     * View: panel/branches/index — branches[{id, slug, name, public_code, menu_url, is_active, sort_order,
     *       category_count, product_count, managers[{name, initials}]}],
     *       canAddBranch (bool: paket şube limiti dolu değil), maxBranches (int|null; null = sınırsız)
     */
    public function index(): string
    {
        $branches      = (new BranchModel())->accessible($this->tenant->allowedBranchIds());
        $branchIds     = array_map('intval', array_column($branches, 'id'));
        $subscriptions = new SubscriptionService();
        $names         = service('languageResolver')->resolveMany(
            BranchTranslationModel::class,
            $branchIds,
            [(int) $this->restaurant['default_language_id']],
        );

        $categoryCounts = (new CategoryModel())->countsByBranch($branchIds);
        $productCounts  = (new ProductModel())->countsByBranch($branchIds);
        $managers       = (new RestaurantUserBranchModel())->activeManagersByBranch($branchIds);
        $menuBase       = (new SiteService())->canonicalBase($this->tenant->restaurantId(), (string) $this->restaurant['slug']);

        return $this->render('panel/branches/index', [
            'branches' => array_map(static fn (array $branch): array => [
                'id'             => (int) $branch['id'],
                'slug'           => $branch['slug'],
                'name'           => $names[(int) $branch['id']]['name'] ?? $branch['slug'],
                'public_code'    => $branch['public_code'],
                'menu_url'       => SiteService::branchUrl($menuBase, $branch),
                'is_active'      => (int) $branch['is_active'] === 1,
                'sort_order'     => (int) $branch['sort_order'],
                'category_count' => $categoryCounts[(int) $branch['id']] ?? 0,
                'product_count'  => $productCounts[(int) $branch['id']] ?? 0,
                'managers'       => array_map(static fn (array $user): array => [
                    'name'     => $user['name'],
                    'initials' => pix_initials($user['name']),
                ], $managers[(int) $branch['id']] ?? []),
            ], $branches),
            'canAddBranch' => $subscriptions->canAddBranch($this->tenant->restaurantId()),
            'maxBranches'  => $subscriptions->entitlements($this->tenant->restaurantId())['max_branches'],
        ]);
    }

    /**
     * View: panel/branches/form — branch(null), languages, translations, action
     */
    public function new(): string
    {
        $this->requireOwner();

        return $this->form(null);
    }

    public function create(): ResponseInterface
    {
        $this->requireOwner();

        return $this->handle(function (): ResponseInterface {
            (new BranchService(Scope::tenant()))->create($this->input(), $this->translationsInput());

            return $this->done('panel/branches', lang('App.created'));
        });
    }

    /**
     * View: panel/branches/form — branch, languages, translations, action
     */
    public function edit(int $branchId): string
    {
        $this->requireOwner();

        return $this->form($this->requireBranch($branchId));
    }

    public function update(int $branchId): ResponseInterface
    {
        $this->requireOwner();
        $branch = $this->requireBranch($branchId);

        return $this->handle(function () use ($branch): ResponseInterface {
            (new BranchService(Scope::tenant()))->update($branch, $this->input(), $this->translationsInput());

            return $this->done('panel/branches', lang('App.saved'));
        });
    }

    public function toggle(int $branchId): ResponseInterface
    {
        $this->requireOwner();
        $branch = $this->requireBranch($branchId);

        // Aktifleştirme paket şube limitine takılabilir (BusinessRuleException → error flash).
        return $this->handle(function () use ($branch): ResponseInterface {
            (new BranchService(Scope::tenant()))->toggle($branch);

            return $this->done('panel/branches', lang('App.statusChanged'));
        });
    }

    public function delete(int $branchId): ResponseInterface
    {
        $this->requireOwner();
        (new BranchService(Scope::tenant()))->delete($this->requireBranch($branchId));

        return $this->done('panel/branches', lang('App.deleted'));
    }

    /**
     * Alanlar tek tek seçilir; restaurant_id ve public_code request'ten OKUNMAZ.
     *
     * @return array{slug: string, default_language_id: int, phone: string, is_active: int, sort_order: int}
     */
    private function input(): array
    {
        return [
            'slug'                => trim((string) $this->request->getPost('slug')),
            'default_language_id' => (int) $this->request->getPost('default_language_id'),
            'phone'               => trim((string) $this->request->getPost('phone')),
            'is_active'           => $this->flag('is_active'),
            'sort_order'          => $this->sortOrder(),
        ];
    }

    /**
     * @param array<string, mixed>|null $branch
     */
    private function form(?array $branch): string
    {
        $translations = new TranslationService();

        return $this->render('panel/branches/form', [
            'branch'       => $branch,
            // is_default: şubenin kendi bilgilerinde zorunlu dil = RESTORANIN varsayılan dili
            'languages'    => $translations->formLanguages(
                (new BranchService(Scope::tenant()))->languages(),
                (int) $this->restaurant['default_language_id'],
            ),
            'translations' => $branch === null ? [] : $translations->load(BranchTranslationModel::class, (int) $branch['id']),
            'action'       => $branch === null ? 'panel/branches' : 'panel/branches/' . $branch['id'],
        ]);
    }
}
