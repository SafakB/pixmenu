<?php

declare(strict_types=1);

namespace App\Controllers\Menu;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Herkese açık HTML menü: GET /m/{public_code}?lang=en
 *
 * Parametre branches.public_code'dur; restoran/şube ID'si dışarı açılmaz.
 */
final class MenuController extends BaseMenuController
{
    /**
     * View: menu/show — menu (spec §11.3 `data` yapısı), menuUrl
     */
    public function show(string $publicCode): ResponseInterface
    {
        return $this->renderMenu($publicCode, site_url('m/' . strtolower($publicCode)));
    }
}
