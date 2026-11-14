<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * /admin/* ve /panel/* — başarılı yazma işlemlerini (POST) ve giriş/çıkışı kaydeder.
 * Neyin kaydedileceğine controller karar verir (ActivityLogger::succeed()); bu filtre
 * yalnızca yanıt hazırlandıktan sonra kaydı yazar. Config\Filters::$filters ile bağlanır.
 */
final class ActivityLogFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if ($request instanceof IncomingRequest && $request->is('post')) {
            service('activity')->flush($request);
        }

        return null;
    }
}
