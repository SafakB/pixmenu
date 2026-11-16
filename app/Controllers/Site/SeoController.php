<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use App\Services\DomainService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * robots.txt ve sitemap.xml (statik public/robots.txt yerine; mutlak sitemap adresi için dinamik).
 *
 * robots.txt menü alan adlarında (özel alan adı / alt alan adı) da sunulur: orada yalnızca
 * menü vardır, her şeye izin verilir ve sitemap verilmez.
 */
final class SeoController extends BaseController
{
    public function robots(): ResponseInterface
    {
        $host = (string) (service('superglobals')->server('HTTP_HOST') ?? '');
        $app  = $host === '' || (new DomainService())->isAppHost($host);

        $lines = ['User-agent: *'];

        if ($app) {
            $lines[] = 'Disallow: /admin';
            $lines[] = 'Disallow: /panel';
            $lines[] = 'Disallow: /api';
            $lines[] = 'Disallow: /kayit';
            $lines[] = '';
            $lines[] = 'Sitemap: ' . site_url('sitemap.xml');
        } else {
            $lines[] = 'Disallow:';
        }

        return $this->response
            ->setContentType('text/plain', 'UTF-8')
            ->setBody(implode("\n", $lines) . "\n");
    }

    public function sitemap(): ResponseInterface
    {
        $today = date('Y-m-d');
        $urls  = [
            ['loc' => base_url(), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => site_url('kvkk'), 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['loc' => site_url('kullanim-kosullari'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . esc($u['loc']) . '</loc><lastmod>' . $today . '</lastmod>'
                . '<changefreq>' . $u['changefreq'] . '</changefreq><priority>' . $u['priority'] . '</priority></url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";

        return $this->response->setContentType('application/xml', 'UTF-8')->setBody($xml);
    }
}
