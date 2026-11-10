<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AJAX/JSON isteklerinin yanıtına güncel CSRF token'ını `X-CSRF-TOKEN` başlığında ekler
 * (spec §11.2 — token regenerate açık olduğu için istemci bir sonraki istekte yenisini
 * göndermek zorundadır). Herkese açık API (`api/*`) kapsam dışıdır.
 */
final class CsrfHeaderFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        helper('pixmenu');

        if (wants_json($request)) {
            $response->setHeader(config('Security')->headerName, csrf_hash());
        }

        return $response;
    }
}
