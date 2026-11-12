<?php

declare(strict_types=1);

namespace App\Controllers\Menu;

use App\Models\BranchModel;
use App\Models\BranchTranslationModel;
use App\Models\RestaurantModel;
use App\Models\RestaurantTranslationModel;
use App\Services\DomainService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restoranın özel alan adındaki menü — docs/ABONELIK_PLANI.md §9.2.
 *
 *  GET /         tek aktif şube → doğrudan menü; birden fazla → şube seçimi
 *  GET /{slug}   şubenin menüsü
 *
 * Bu host'ta yalnızca bu iki rota vardır (Routes.php başı).
 */
final class DomainMenuController extends BaseMenuController
{
    /**
     * View: menu/branches — restaurant{name, logo_url}, branches[{slug, name, address, url}]
     */
    public function index(): ResponseInterface
    {
        $site     = $this->site();
        $branches = $this->branches($site['restaurant_id']);

        if ($branches === []) {
            $this->notFound();
        }

        if (count($branches) === 1) {
            return $this->renderMenu((string) $branches[0]['public_code'], '/');
        }

        $restaurant = (new RestaurantModel())->find($site['restaurant_id']) ?? $this->notFound();
        $resolver   = service('languageResolver');
        $chain      = [(int) $restaurant['default_language_id']];
        $names      = $resolver->resolveMany(BranchTranslationModel::class, array_map('intval', array_column($branches, 'id')), $chain);

        return $this->response->setBody(view('menu/branches', [
            'restaurant' => [
                'name'     => (string) ($resolver->resolveOne(RestaurantTranslationModel::class, (int) $restaurant['id'], $chain)['name'] ?? ''),
                'logo_url' => upload_url($restaurant['logo_path']),
            ],
            'branches' => array_map(static fn (array $b): array => [
                'slug'    => $b['slug'],
                'name'    => $names[(int) $b['id']]['name'] ?? $b['slug'],
                'address' => $names[(int) $b['id']]['address'] ?? null,
                'url'     => '/' . rawurlencode((string) $b['slug']),
            ], $branches),
        ]));
    }

    public function show(string $slug): ResponseInterface
    {
        $site = $this->site();

        foreach ($this->branches($site['restaurant_id']) as $branch) {
            if ($branch['slug'] === strtolower($slug)) {
                return $this->renderMenu((string) $branch['public_code'], '/' . rawurlencode((string) $branch['slug']));
            }
        }

        $this->notFound();
    }

    /**
     * @return array{restaurant_id: int, domain: string, default_language_id: int}
     */
    private function site(): array
    {
        $host = (string) (service('superglobals')->server('HTTP_HOST') ?? '');

        return (new DomainService())->resolve($host) ?? $this->notFound();
    }

    /**
     * Restoranın aktif, silinmemiş şubeleri (sıralı). Herkese açık istek: tenant bağlamı yok,
     * sorgu çözümlenen restorana sabitlenir.
     *
     * @return list<array<string, mixed>>
     */
    private function branches(int $restaurantId): array
    {
        return (new BranchModel())->forRestaurant($restaurantId)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
