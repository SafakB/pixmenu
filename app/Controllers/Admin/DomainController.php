<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\DomainService;
use App\Services\SubscriptionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restoranın özel alan adı — docs/ABONELIK_PLANI.md §9.
 */
final class DomainController extends AdminBaseController
{
    /**
     * View: admin/restaurants/domain — restaurant (satır + name), domain (satır|null), allowed (bool: paket izin veriyor mu),
     *       appHost (string: CNAME hedefi), menuUrl (string|null)
     */
    public function show(int $restaurantId): string
    {
        $restaurant = $this->requireRestaurant($restaurantId);
        $service    = new DomainService();
        $domain     = $service->current($restaurantId);

        return $this->render('admin/restaurants/domain', [
            'restaurant' => $restaurant + ['name' => $this->restaurantName($restaurant)],
            'domain'     => $domain,
            'allowed'    => (new SubscriptionService())->entitlements($restaurantId)['allow_custom_domain'],
            'appHost'    => $service->appHosts()[0] ?? '',
            'menuUrl'    => $domain === null ? null : 'https://' . $domain['domain'] . '/',
        ]);
    }

    /**
     * Alanlar: domain, is_active
     */
    public function save(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurantId): ResponseInterface {
            (new DomainService())->save($restaurantId, (string) $this->request->getPost('domain'), $this->flag('is_active') === 1);

            return $this->done($this->base($restaurantId), lang('App.domain.saved'));
        });
    }

    /**
     * Alan: verified (1 = doğrulandı, 0 = doğrulamayı kaldır)
     */
    public function verify(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurantId): ResponseInterface {
            $verified = $this->flag('verified') === 1;
            (new DomainService())->setVerified($restaurantId, $verified);

            return $this->done($this->base($restaurantId), lang($verified ? 'App.domain.verified' : 'App.domain.unverified'));
        });
    }

    public function delete(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurantId): ResponseInterface {
            (new DomainService())->remove($restaurantId);

            return $this->done($this->base($restaurantId), lang('App.domain.removed'));
        });
    }

    private function base(int $restaurantId): string
    {
        return 'admin/restaurants/' . $restaurantId . '/domain';
    }
}
