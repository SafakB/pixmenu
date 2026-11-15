<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DomainService;
use App\Services\MenuService;
use App\Services\SubscriptionService;
use CodeIgniter\Test\TestResponse;
use Tests\Support\DbTestCase;

/**
 * Menü ön ucunun (menu-ui/) site API'si: GET /api/v1/site — docs/VIEW_CONTRACT.md "Menü sitesi API".
 *
 * Restoran A: şubeler a1 (aaaaaaaaa1), a2 (aaaaaaaaa2). Restoran B: tek şube b1 (bbbbbbbbb1).
 *
 * @internal
 */
final class SiteApiTest extends DbTestCase
{
    private function site(string $query): TestResponse
    {
        return $this->get('api/v1/site?' . $query);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(string $query): array
    {
        $response = $this->site($query);
        $response->assertOK();
        $body = json_decode((string) $response->getJSON(), true);
        $this->assertSame('success', $body['status']);

        return $body['data'];
    }

    private function assertNotFound(string $query): void
    {
        $response = $this->site($query);
        $response->assertStatus(404);
        $this->assertSame('error', json_decode((string) $response->getJSON(), true)['status'], $query);
    }

    private function enableSubdomains(): void
    {
        config('Pixmenu')->menuRootDomain = 'pixmenu.test';
    }

    public function testSlugModeMultiBranchRestaurantReturnsBranchListWithoutMenu(): void
    {
        $data = $this->data('restaurant=restoran-a');

        $this->assertSame(['restaurant', 'language', 'languages', 'branches', 'canonical_url', 'menu'], array_keys($data));
        $this->assertSame(['slug' => 'restoran-a', 'name' => 'Restoran A', 'logo_url' => null], $data['restaurant']);
        $this->assertSame(['code' => 'tr', 'direction' => 'ltr'], $data['language']);
        $this->assertSame(['tr', 'en'], array_column($data['languages'], 'code'));
        $this->assertSame([
            ['slug' => 'a1', 'code' => 'aaaaaaaaa1', 'name' => 'Şube A1', 'address' => 'Şube A1 adresi'],
            ['slug' => 'a2', 'code' => 'aaaaaaaaa2', 'name' => 'Şube A2', 'address' => 'Şube A2 adresi'],
        ], $data['branches']);
        $this->assertNull($data['canonical_url'], 'Alt alan adı kapalı ve özel alan adı yok');
        $this->assertNull($data['menu']);
    }

    public function testBranchParamReturnsExactlyTheMenuApiPayloadOfThatBranch(): void
    {
        $data = $this->data('restaurant=restoran-a&branch=A2');

        $this->assertSame((new MenuService())->build('aaaaaaaaa2', null), $data['menu']);
        $this->assertSame('aaaaaaaaa2', $data['menu']['branch']['code']);
        $this->assertSame('standart', $data['menu']['branch']['template']);
        $this->assertCount(2, $data['branches'], 'Şube seçiliyken de şube listesi döner');
    }

    public function testSingleBranchRestaurantGetsItsMenuWithoutBranchParam(): void
    {
        $data = $this->data('restaurant=restoran-b');

        $this->assertSame('bbbbbbbbb1', $data['menu']['branch']['code']);
        $this->assertSame('Gizli Ürün B', $data['menu']['categories'][0]['products'][0]['name']);
    }

    public function testLangParamTranslatesRestaurantAndMenuWithFallback(): void
    {
        $data = $this->data('restaurant=restoran-a&branch=a1&lang=en');

        $this->assertSame('Restaurant A', $data['restaurant']['name']);
        $this->assertSame('en', $data['language']['code']);
        $this->assertSame('en', $data['menu']['language']['code']);
        $this->assertSame('Soups A1', $data['menu']['categories'][0]['name']);
        $this->assertSame('Şube A1', $data['branches'][0]['name'], 'EN çevirisi yok → varsayılan dil');

        // Restoranın aktif dillerinde olmayan dil → varsayılan.
        $this->assertSame('tr', $this->data('restaurant=restoran-a&lang=de')['language']['code']);
    }

    public function testUnknownOrInvalidInputIs404(): void
    {
        foreach (['', 'restaurant=yok', 'restaurant=restoran-a&branch=yok', 'restaurant=restoran-a&branch=b1',
            'restaurant=../restoran-a', 'restaurant=Restoran%20A', 'host=bilinmeyen.test', 'host=a%20b'] as $query) {
            $this->assertNotFound($query);
        }
    }

    public function testInactiveSuspendedOrDeletedRestaurantIs404(): void
    {
        (new SubscriptionService())->suspendManually($this->fx->restaurantB);
        $this->assertNotFound('restaurant=restoran-b');

        $this->db->table('restaurants')->where('id', $this->fx->restaurantA)->update(['is_active' => 0]);
        $this->assertNotFound('restaurant=restoran-a');

        $this->db->table('restaurants')->where('id', $this->fx->restaurantA)->update(['is_active' => 1, 'deleted_at' => date('Y-m-d H:i:s')]);
        $this->assertNotFound('restaurant=restoran-a');
    }

    public function testInactiveBranchIsHiddenAndTheOnlyActiveBranchOpensDirectly(): void
    {
        $this->db->table('branches')->where('id', $this->fx->branchA2)->update(['is_active' => 0]);

        $data = $this->data('restaurant=restoran-a');
        $this->assertSame(['a1'], array_column($data['branches'], 'slug'));
        $this->assertSame('aaaaaaaaa1', $data['menu']['branch']['code']);

        $this->assertNotFound('restaurant=restoran-a&branch=a2');
    }

    public function testSubdomainHostResolvesRestaurantBySlug(): void
    {
        $this->enableSubdomains();

        $data = $this->data('host=restoran-a.pixmenu.test&branch=a1');
        $this->assertSame('restoran-a', $data['restaurant']['slug']);
        $this->assertSame('aaaaaaaaa1', $data['menu']['branch']['code']);
        $this->assertSame('https://restoran-a.pixmenu.test', $data['canonical_url']);

        // Büyük harf, www. ve port yok sayılır.
        $this->assertSame('restoran-a', $this->data('host=WWW.Restoran-A.pixmenu.test:443')['restaurant']['slug']);
    }

    public function testReservedNestedOrRootHostsAre404(): void
    {
        $this->enableSubdomains();
        $this->db->table('restaurants')->where('id', $this->fx->restaurantB)->update(['slug' => 'admin']);

        foreach (['host=admin.pixmenu.test', 'host=x.restoran-a.pixmenu.test', 'host=pixmenu.test', 'host=restoran-a.baska.test'] as $query) {
            $this->assertNotFound($query);
        }
    }

    public function testSubdomainsAreOffWhenRootDomainIsEmpty(): void
    {
        $this->assertNotFound('host=restoran-a.pixmenu.test');
    }

    public function testCustomDomainHostAndCanonicalUrlFollowPlanEntitlement(): void
    {
        $this->enableSubdomains();
        $this->fx->insert('restaurant_domains', ['restaurant_id' => $this->fx->restaurantA, 'domain' => 'menu.restoran-a.test', 'is_active' => 1]);

        $data = $this->data('host=menu.restoran-a.test');
        $this->assertSame('restoran-a', $data['restaurant']['slug']);
        $this->assertSame('https://menu.restoran-a.test', $data['canonical_url']);

        // Alt alan adından da açılır ama kanonik adres özel alan adıdır (ön uç oraya yönlenir).
        $this->assertSame('https://menu.restoran-a.test', $this->data('host=restoran-a.pixmenu.test')['canonical_url']);

        // Paket özel alan adını kapsamıyorsa: özel alan adı çalışmaz, kanonik adres alt alan adına düşer.
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionA)->update(['plan_id' => $this->fx->plan['demo']]);
        (new DomainService())->forget('menu.restoran-a.test');

        $this->assertNotFound('host=menu.restoran-a.test');
        $this->assertSame('https://restoran-a.pixmenu.test', $this->data('restaurant=restoran-a')['canonical_url']);
    }

    public function testCanonicalUrlUsesConfiguredSchemeAndPort(): void
    {
        $this->enableSubdomains();
        config('Pixmenu')->menuScheme = 'http';
        config('Pixmenu')->menuPort   = '5173';

        $this->assertSame('http://restoran-a.pixmenu.test:5173', $this->data('restaurant=restoran-a')['canonical_url']);
    }

    public function testCustomDomainCannotBeUnderMenuRootDomain(): void
    {
        $this->enableSubdomains();

        $this->assertTrue((new DomainService())->isReserved('restoran-a.pixmenu.test'));
        $this->assertTrue((new DomainService())->isReserved('pixmenu.test'));
        $this->assertFalse((new DomainService())->isReserved('menu.restoran-a.test'));
    }

    public function testResponseIsPubliclyCacheableAndCorsEnabled(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://menu.ornek.com'])->site('restaurant=restoran-b');
        $response->assertOK();
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertSame('public, max-age=60', $response->response()->getHeaderLine('Cache-Control'));
        $this->assertNull($response->response()->getCookie('pixmenu_session'), 'API çerez yazmaz');

        // Eski menü API'si de CORS'a açık.
        $menu = $this->withHeaders(['Origin' => 'https://menu.ornek.com'])->get('api/v1/menu/bbbbbbbbb1');
        $menu->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function testCorsPreflightIsAnswered(): void
    {
        $response = $this->withHeaders([
            'Origin'                        => 'https://menu.ornek.com',
            'Access-Control-Request-Method' => 'GET',
        ])->options('api/v1/site');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET');
    }

    public function testLegacyMenuLinkRedirectsToCanonicalAddress(): void
    {
        // Kanonik adres yoksa eski HTML menü aynen çalışır.
        $this->get('m/aaaaaaaaa1')->assertOK();

        $this->enableSubdomains();

        $response = $this->get('m/AAAAAAAAA2?lang=en');
        $response->assertStatus(302);
        $this->assertSame('https://restoran-a.pixmenu.test/a2?lang=en', $response->getRedirectUrl());

        // Bilinmeyen kod yönlenmez, 404 olur.
        $this->get('m/zzzzzzzzz9')->assertStatus(404);
    }

    public function testPanelAndAdminShowCanonicalMenuUrls(): void
    {
        $this->enableSubdomains();

        foreach ([
            [$this->asPanelUser($this->fx->ownerA)->get('panel/branches'), 'a1'],
            [$this->asPanelUser($this->fx->ownerA)->get('panel'), 'a2'],
            [$this->asAdmin()->get("admin/restaurants/{$this->fx->restaurantA}/branches"), 'a2'],
            [$this->asAdmin()->get("admin/restaurants/{$this->fx->restaurantA}"), 'a1'],
        ] as [$response, $branch]) {
            $response->assertOK();
            $this->assertStringContainsString('href="https://restoran-a.pixmenu.test/' . $branch . '"', (string) $response->getBody());
        }
    }
}
