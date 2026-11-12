<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Askıdaki restoranın panel içerik rotalarını kapatır (docs/ABONELIK_PLANI.md §6).
 * `panelauth` filtresinden SONRA çalışmalıdır.
 *
 *  - HTML: owner → panel/subscription, manager → panel (gösterge paneli bilgi bandı)
 *  - JSON: 403
 *
 * Serbest rotalar (filtre uygulanmaz): gösterge paneli, profil, çıkış, abonelik/fatura sayfaları.
 */
final class PanelSubscriptionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('pixmenu');

        $tenant = service('tenant');

        if (! $tenant->isSet() || ! $tenant->isSuspended()) {
            return null;
        }

        if (wants_json($request)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(json_error_body(lang('App.subscription.suspendedPanel')));
        }

        return redirect()
            ->to(site_url($tenant->isOwner() ? 'panel/subscription' : 'panel'))
            ->with('error', lang('App.subscription.suspendedPanel'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
