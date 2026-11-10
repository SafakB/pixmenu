<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Yalnızca owner'a açık panel rotaları. Manager için yanıt 403 değil 404'tür
 * (rotanın varlığı sızdırılmaz). `panelauth` filtresinden SONRA çalışmalıdır.
 */
final class PanelOwnerFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $tenant = service('tenant');

        if (! $tenant->isSet() || ! $tenant->isOwner()) {
            throw PageNotFoundException::forPageNotFound();
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
