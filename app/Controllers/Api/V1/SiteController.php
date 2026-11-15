<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\SiteService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Menü ön ucunun (menu-ui/) tek isteği:
 *
 *   GET /api/v1/site?restaurant={slug}&branch={şube-slug}&lang=en
 *   GET /api/v1/site?host={hostname}&branch={şube-slug}&lang=en
 *
 * Durumsuz ve çerezsizdir (MenuController ile aynı ilke); CORS herkese açıktır (Config\Cors::$menu).
 */
final class SiteController extends BaseController
{
    public function show(): ResponseInterface
    {
        $service    = new SiteService();
        $restaurant = $service->find($this->query('restaurant'), $this->query('host'));
        $payload    = $restaurant === null ? null : $service->payload($restaurant, $this->query('branch'), $this->query('lang'));

        if ($payload === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(json_error_body(lang('App.notFound')));
        }

        // CI4 yanıta varsayılan olarak `no-store` koyar ve setHeader() mevcut değere EKLER;
        // bu yüzden önce kaldırılır.
        return $this->response
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'public, max-age=60')
            ->setJSON(['status' => 'success', 'data' => $payload]);
    }

    private function query(string $name): ?string
    {
        $value = $this->request->getGet($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
