<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * /panel/* (login hariç). Kullanıcı ve restoran her istekte veritabanından
 * yeniden doğrulanır; TenantContext::set() YALNIZCA burada çağrılır (spec §6.1, §7.2).
 *
 * `admin_auth` oturumu bu filtrede hiçbir şekilde kabul edilmez.
 */
final class PanelAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('pixmenu');

        // Her istek temiz başlar: önceki bağlam ve önbellek atılır, DB'den yeniden doğrulanır.
        service('tenant')->clear();

        $auth = service('panelAuth');
        $auth->forget();
        $user = $auth->user();

        if ($user === null) {
            if (wants_json($request)) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON(json_error_body(lang('App.auth.required')));
            }

            return redirect()->to(site_url('panel/login'));
        }

        $role       = (string) $user['role'];
        $restaurant = $auth->restaurant();

        service('tenant')->set(
            (int) $user['restaurant_id'],
            (int) $user['id'],
            $role,
            $role === 'manager' ? $auth->managerBranchIds($user) : null,
            ! empty($restaurant['suspended_at']),
        );

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
