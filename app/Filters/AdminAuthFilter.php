<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * /admin/* (login hariç). `panel_auth` oturumu burada asla kabul edilmez ve
 * bu filtre TenantContext'e DOKUNMAZ (spec §7.2).
 */
final class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('pixmenu');

        $auth = service('adminAuth');
        $auth->forget();

        if ($auth->user() !== null) {
            return null;
        }

        if (wants_json($request)) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(json_error_body(lang('App.auth.required')));
        }

        return redirect()->to(site_url('admin/login'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
