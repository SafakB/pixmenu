<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Config\Services;
use Tests\Support\DbTestCase;

/**
 * Herkese açık site: tanıtım sayfası (/), yasal metinler, robots.txt ve sitemap.xml.
 *
 * @internal
 */
final class LandingTest extends DbTestCase
{
    protected function tearDown(): void
    {
        service('superglobals')->unsetServer('HTTP_HOST');
        Services::resetSingle('routes');
        Services::resetSingle('router');

        parent::tearDown();
    }

    public function testLandingRendersPlansFromDatabaseAndSeoTags(): void
    {
        $response = $this->get('/');
        $response->assertOK();

        $html    = (string) $response->getBody();
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // esc(..., 'attr') URL'leri entity'ler

        // Paketler DB'den gelir (Fixture adları), KDV notu ve kayıt linkleri vardır.
        foreach (['Demo', 'Standart', 'Pro', 'Kurumsal'] as $name) {
            $this->assertStringContainsString('>' . $name . '<', $html);
        }

        $response->assertSee('+KDV');
        $response->assertSee('Popüler');
        $this->assertStringContainsString('kayit?plan=pro&cycle=monthly', $decoded);
        $this->assertStringContainsString('kayit?plan=demo"', $decoded);

        // SEO: tek h1, indekslenebilir, canonical, OG ve geçerli JSON-LD.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $html);
        $this->assertStringNotContainsString('noindex', $html);
        $this->assertStringContainsString('<link rel="canonical" href="' . base_url() . '">', $decoded);
        $this->assertStringContainsString('property="og:image"', $html);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $this->assertCount(3, $m[1]);
        $types = array_map(static fn (string $json): string => json_decode($json, true, 512, JSON_THROW_ON_ERROR)['@type'], $m[1]);
        $this->assertSame(['Organization', 'SoftwareApplication', 'FAQPage'], $types);
    }

    public function testLandingDoesNotLeakInternalPlanData(): void
    {
        $this->db->table('plans')->where('code', 'enterprise')->update(['is_active' => 0]);

        $response = $this->get('/');

        $response->assertOK();
        $this->assertStringNotContainsString('>Kurumsal<', (string) $response->getBody());
        $response->assertDontSee('current_count');
        $response->assertDontSee('Restoran A');
    }

    public function testLegalPagesRender(): void
    {
        $privacy = $this->get('kvkk');
        $privacy->assertOK();
        $privacy->assertSee('KVKK Aydınlatma Metni');

        $terms = $this->get('kullanim-kosullari');
        $terms->assertOK();
        $terms->assertSee('Kullanım Koşulları');
    }

    public function testRobotsAndSitemap(): void
    {
        $robots = $this->get('robots.txt');
        $robots->assertOK();
        $this->assertStringStartsWith('text/plain', $robots->response()->getHeaderLine('Content-Type'));
        $body = (string) $robots->getBody();
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Disallow: /panel', $body);
        $this->assertStringContainsString('Sitemap: ' . site_url('sitemap.xml'), $body);

        $sitemap = $this->get('sitemap.xml');
        $sitemap->assertOK();
        $this->assertStringStartsWith('application/xml', $sitemap->response()->getHeaderLine('Content-Type'));
        $xml = simplexml_load_string((string) $sitemap->getBody());
        $this->assertNotFalse($xml);
        $this->assertStringContainsString('<loc>' . base_url() . '</loc>', (string) $sitemap->getBody());
    }

    public function testMenuDomainKeepsMenuAtRootAndAllowsCrawling(): void
    {
        $this->fx->insert('restaurant_domains', ['restaurant_id' => $this->fx->restaurantB, 'domain' => 'restoran-b.test', 'is_active' => 1]);
        service('superglobals')->setServer('HTTP_HOST', 'restoran-b.test');
        Services::resetSingle('routes');
        Services::resetSingle('router');

        $this->get('/')->assertSee('Gizli Ürün B');

        $robots = (string) $this->get('robots.txt')->getBody();
        $this->assertStringContainsString('Disallow:', $robots);
        $this->assertStringNotContainsString('Sitemap', $robots);
        $this->assertStringNotContainsString('/panel', $robots);

        // Tanıtım/kayıt rotaları menü alan adında yoktur.
        $this->get('kayit')->assertStatus(404);
    }
}
