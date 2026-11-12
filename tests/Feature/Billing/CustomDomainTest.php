<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Services\DomainService;
use App\Services\SubscriptionService;
use Config\Services;
use Tests\Support\DbTestCase;

/**
 * Özel alan adı çözümleme ve host bazlı rotalar (docs/ABONELIK_PLANI.md §9).
 *
 * @internal
 */
final class CustomDomainTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fx->insert('restaurant_domains', ['restaurant_id' => $this->fx->restaurantA, 'domain' => 'menu.restoran-a.test', 'is_active' => 1]);
        $this->fx->insert('restaurant_domains', ['restaurant_id' => $this->fx->restaurantB, 'domain' => 'restoran-b.test', 'is_active' => 1]);
    }

    protected function tearDown(): void
    {
        service('superglobals')->unsetServer('HTTP_HOST');
        Services::resetSingle('routes');
        Services::resetSingle('router');   // router, yüklendiği rota koleksiyonunu tutar

        parent::tearDown();
    }

    private function onHost(string $host): void
    {
        service('superglobals')->setServer('HTTP_HOST', $host);
        Services::resetSingle('routes');
        Services::resetSingle('router');
    }

    public function testSingleBranchRestaurantShowsMenuAtRoot(): void
    {
        $this->onHost('restoran-b.test');

        $response = $this->get('/');
        $response->assertOK();
        $response->assertSee('Gizli Ürün B');
    }

    public function testWwwPrefixAndPortAreIgnored(): void
    {
        $this->onHost('www.restoran-b.test:443');

        $response = $this->get('/');
        $response->assertOK();
        $response->assertSee('Gizli Ürün B');
    }

    public function testMultiBranchRestaurantShowsBranchPickerAndBranchMenus(): void
    {
        $this->onHost('menu.restoran-a.test');

        $root = $this->get('/');
        $root->assertOK();
        $root->assertSee('Şube A1');
        $root->assertSee('Şube A2');
        $this->assertStringContainsString('href="/a1"', (string) $root->getBody());
        $root->assertDontSee('Gizli');

        $a1 = $this->get('a1');
        $a1->assertOK();
        $a1->assertSee('Mercimek A1');
        $a1->assertDontSee('Mercimek A2');
        $this->get('a2')->assertSee('Mercimek A2');
        $this->get('yok')->assertStatus(404);
    }

    public function testAdminPanelAndApiDoNotExistOnCustomDomain(): void
    {
        $this->onHost('menu.restoran-a.test');

        foreach (['admin', 'admin/login', 'panel/login', 'api/v1/menu/aaaaaaaaa1', 'm/aaaaaaaaa1'] as $path) {
            $this->get($path)->assertStatus(404);
        }
    }

    public function testUnknownSuspendedOrNotEntitledDomainsAre404(): void
    {
        $this->onHost('bilinmeyen.test');
        $this->get('/')->assertStatus(404);

        (new SubscriptionService())->suspendManually($this->fx->restaurantB);
        $this->onHost('restoran-b.test');
        $this->get('/')->assertStatus(404);

        // Paket özel alan adını kapsamıyorsa çalışmaz.
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionA)->update(['plan_id' => $this->fx->plan['demo']]);
        (new DomainService())->forget('menu.restoran-a.test');
        $this->onHost('menu.restoran-a.test');
        $this->get('/')->assertStatus(404);
    }

    public function testInactiveDomainIs404(): void
    {
        $this->db->table('restaurant_domains')->where('restaurant_id', $this->fx->restaurantB)->update(['is_active' => 0]);
        $this->onHost('restoran-b.test');

        $this->get('/')->assertStatus(404);
    }

    public function testAppHostKeepsNormalRoutes(): void
    {
        $this->onHost((string) parse_url(config('App')->baseURL, PHP_URL_HOST));
        $this->get('m/aaaaaaaaa1')->assertOK();
        $this->get('admin/login')->assertOK();
    }

    public function testNormalize(): void
    {
        $this->assertSame('menu.ornek.com', DomainService::normalize(' HTTPS://WWW.Menu.Ornek.com:8080/a/b?c '));
        $this->assertSame('ornek.com.tr', DomainService::normalize('ornek.com.tr.'));
        $this->assertNull(DomainService::normalize('localhost'));
        $this->assertNull(DomainService::normalize('ornek'));
        $this->assertNull(DomainService::normalize('-kotu-.com'));
        $this->assertNull(DomainService::normalize('a b.com'));
    }
}
