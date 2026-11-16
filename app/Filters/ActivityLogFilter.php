<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * /admin/* ve /panel/* (+ /kayit POST rota filtresi) — başarılı yazma işlemlerini (POST) ve
 * giriş/çıkışı kaydeder. Neyin kaydedileceğine controller karar verir (ActivityLogger::succeed()
 * veya record()); bu filtre yalnızca yanıt hazırlandıktan sonra kaydı yazar. GET istekleri yalnızca
 * açık kayıt (record(): ör. Google ile giriş callback'i) varsa yazılır.
 */
final class ActivityLogFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if ($request instanceof IncomingRequest && ($request->is('post') || service('activity')->hasExplicitRecord())) {
            service('activity')->flush($request);
        }

        return null;
    }
}
