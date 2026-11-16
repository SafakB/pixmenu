<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\BaseController;

/**
 * Yasal metinler (KVKK aydınlatma metni, kullanım koşulları).
 * TODO(hukuk): metinler yer tutucudur; yayına almadan önce hukuk danışmanınızın metniyle değiştirin.
 */
final class PageController extends BaseController
{
    /**
     * View: site/legal — page ('privacy' | 'terms'), title, description, updatedAt, contact{email, phone, address}
     */
    public function privacy(): string
    {
        return $this->legal('privacy', 'KVKK Aydınlatma Metni', 'PixMenu kişisel verilerin korunması ve işlenmesine ilişkin aydınlatma metni.');
    }

    public function terms(): string
    {
        return $this->legal('terms', 'Kullanım Koşulları', 'PixMenu dijital menü hizmetinin kullanım koşulları.');
    }

    private function legal(string $page, string $title, string $description): string
    {
        $site = config('Site');

        return view('site/legal', [
            'page'        => $page,
            'title'       => $title,
            'description' => $description,
            'updatedAt'   => '16.11.2026',
            'contact'     => ['email' => $site->contactEmail, 'phone' => $site->contactPhone, 'address' => $site->contactAddress],
            'signupUrl'   => site_url('kayit'),
            'loginUrl'    => site_url('panel/login'),
            'seo'         => [
                'title'       => $title . ' | PixMenu',
                'description' => $description,
                'canonical'   => site_url($page === 'privacy' ? 'kvkk' : 'kullanim-kosullari'),
                'ogImage'     => base_url('assets/images/landing/og-image.png'),
            ],
            'jsonLd' => [],
        ]);
    }
}
