<?php

declare(strict_types=1);

namespace App\Controllers\Menu;

use App\Services\SiteService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Herkese açık HTML menü: GET /m/{public_code}?lang=en
 *
 * Parametre branches.public_code'dur; restoran/şube ID'si dışarı açılmaz.
 *
 * Restoranın kanonik menü adresi varsa (özel alan adı veya `slug.{menuRootDomain}`)
 * istek oraya 302 ile yönlenir; böylece önceden paylaşılan /m/ bağlantıları menu-ui'ye
 * ulaşır. 302: restoran sonradan alan adı alırsa hedef değişir.
 */
final class MenuController extends BaseMenuController
{
    /**
     * View: menu/show — menu (spec §11.3 `data` yapısı), menuUrl
     */
    public function show(string $publicCode): ResponseInterface
    {
        $publicCode = strtolower($publicCode);
        $canonical  = (new SiteService())->canonicalBranchUrl($publicCode);

        if ($canonical !== null) {
            $lang = $this->request->getGet('lang');

            if (is_string($lang) && preg_match('/^[A-Za-z0-9\-]{1,10}$/', $lang) === 1) {
                $canonical .= '?lang=' . rawurlencode($lang);
            }

            return $this->response->redirect($canonical, 'auto', 302);
        }

        return $this->renderMenu($publicCode, site_url('m/' . $publicCode));
    }
}
