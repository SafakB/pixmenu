<?php

declare(strict_types=1);

namespace App\Controllers\Menu;

use App\Controllers\BaseController;
use App\Services\MenuService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Herkese açık HTML menünün ortak işleyişi: dil seçimi (?lang → çerez → şube varsayılanı),
 * dil çerezi ve view. /m/{public_code} ve özel alan adı controller'ları kullanır.
 */
abstract class BaseMenuController extends BaseController
{
    /**
     * @param string $menuUrl dil bağlantılarının taban adresi (sorgu dizesi hariç)
     */
    protected function renderMenu(string $publicCode, string $menuUrl): ResponseInterface
    {
        $publicCode = strtolower($publicCode);
        $cookieName = 'pixmenu_lang_' . preg_replace('/[^a-z0-9]/', '', $publicCode);

        $lang   = $this->request->getGet('lang');
        $lang   = is_string($lang) && $lang !== '' ? $lang : null;
        $cookie = $this->request->getCookie($cookieName);
        $cookie = is_string($cookie) && $cookie !== '' ? $cookie : null;

        $menu = (new MenuService())->build($publicCode, $lang, $cookie);

        if ($menu === null) {
            $this->notFound();
        }

        // ?lang geçerliyse (restoranın aktif dillerindeyse) 30 gün çereze yazılır.
        if ($lang !== null && $menu['language']['code'] === $lang) {
            $this->response->setCookie($cookieName, $lang, config('Pixmenu')->langCookieTtl);
        }

        return $this->response->setBody(view('menu/show', ['menu' => $menu, 'menuUrl' => $menuUrl]));
    }
}
