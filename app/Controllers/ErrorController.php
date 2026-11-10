<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * 404 override. "Bulunamadı", "başka tenant'a ait" ve "yetkisiz şube/rota"
 * durumlarının hepsi buraya düşer ve AYNI yanıtı alır (spec §2.1.6).
 */
final class ErrorController extends BaseController
{
    public function show404(): ResponseInterface
    {
        $this->response->setStatusCode(404);

        if ($this->wantsJson() || str_starts_with(trim(uri_string(), '/'), 'api/')) {
            return $this->response->setJSON(json_error_body(lang('App.notFound')));
        }

        return $this->response->setBody(view('errors/not_found', ['message' => lang('App.notFound')]));
    }
}
