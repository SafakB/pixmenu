<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\MenuService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Herkese açık JSON menü: GET /api/v1/menu/{public_code}?lang=en
 *
 * Durumsuzdur: çerez okumaz/yazmaz (yanıt `Cache-Control: public` ile
 * paylaşımlı cache'lere girebildiği için). Dil yalnızca ?lang ile seçilir;
 * geçersizse şubenin varsayılan dili kullanılır.
 */
final class MenuController extends BaseController
{
    public function show(string $publicCode): ResponseInterface
    {
        $lang = $this->request->getGet('lang');
        $lang = is_string($lang) && $lang !== '' ? $lang : null;

        $menu = (new MenuService())->build(strtolower($publicCode), $lang);

        if ($menu === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(json_error_body(lang('App.notFound')));
        }

        // CI4 yanıta varsayılan olarak `no-store` koyar ve setHeader() mevcut değere EKLER;
        // bu yüzden önce kaldırılır.
        return $this->response
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'public, max-age=60')
            ->setJSON(['status' => 'success', 'data' => $menu]);
    }
}
