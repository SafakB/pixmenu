<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use LogicException;

/**
 * `adminperm:<izin>` — oturumdaki adminin rolü bu izni vermiyorsa 403.
 * `adminauth` filtresinden SONRA çalışmalıdır. İzinler: Config\AdminRoles.
 */
final class AdminPermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $permission = is_array($arguments) ? (string) ($arguments[0] ?? '') : '';

        if ($permission === '') {
            // Yanlış rota tanımı sessizce "herkese açık" olmasın.
            throw new LogicException('adminperm filtresi bir izin argümanı ister (ör. adminperm:restaurants.manage).');
        }

        helper('pixmenu');

        $auth = service('adminAuth');

        if ($auth->can($permission)) {
            return null;
        }

        $response = service('response')->setStatusCode(403);

        if (wants_json($request)) {
            return $response->setJSON(json_error_body(lang('App.auth.forbidden')));
        }

        return $response->setBody(view('admin/forbidden', [
            'currentAdmin' => $auth->user() ?? [],
            'title'        => 'Yetkisiz işlem',
        ]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
