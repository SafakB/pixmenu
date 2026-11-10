<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DbTestCase;
use Tests\Support\Fixture;

/**
 * Kimlik doğrulama (spec §7) ve oturum ayrımı (spec §2.2).
 *
 * @internal
 */
final class AuthTest extends DbTestCase
{
    public function testPanelLoginSucceedsAndStoresOnlyPanelKey(): void
    {
        $response = $this->post('panel/login', ['email' => 'owner-a@test.local', 'password' => Fixture::PASSWORD]);

        $this->assertRedirectsTo($response, 'panel');
        $auth = $_SESSION['panel_auth'] ?? null;
        $this->assertIsArray($auth);
        $this->assertSame((int) $this->fx->ownerA['id'], $auth['id']);
        $this->assertSame($this->fx->restaurantA, $auth['restaurant_id']);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertNotNull($this->row('restaurant_users', (int) $this->fx->ownerA['id'])['last_login_at']);
    }

    public function testAdminLoginSucceeds(): void
    {
        $response = $this->post('admin/login', ['email' => 'admin@test.local', 'password' => Fixture::PASSWORD]);

        $this->assertRedirectsTo($response, 'admin');
        $this->assertSame($this->fx->adminId, $_SESSION['admin_auth']['id'] ?? null);
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
    }

    public function testFailedLoginMessageIsIdenticalForUnknownUserAndWrongPassword(): void
    {
        $wrongPassword = $this->post('panel/login', ['email' => 'owner-a@test.local', 'password' => 'yanlis-sifre-1']);
        $messageA      = $_SESSION['error'] ?? null;

        $unknownUser = $this->post('panel/login', ['email' => 'yok@test.local', 'password' => 'yanlis-sifre-1']);
        $messageB    = $_SESSION['error'] ?? null;

        $wrongPassword->assertRedirect();
        $unknownUser->assertRedirect();
        $this->assertSame('E-posta veya şifre hatalı.', $messageA);
        $this->assertSame($messageA, $messageB);
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
    }

    public function testAdminCredentialsDoNotWorkOnPanelAndViceVersa(): void
    {
        $this->post('panel/login', ['email' => 'admin@test.local', 'password' => Fixture::PASSWORD]);
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);

        $this->post('admin/login', ['email' => 'owner-a@test.local', 'password' => Fixture::PASSWORD]);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
    }

    public function testInactiveUserCannotLogin(): void
    {
        $this->db->table('restaurant_users')->where('id', $this->fx->ownerA['id'])->update(['is_active' => 0]);

        $this->post('panel/login', ['email' => 'owner-a@test.local', 'password' => Fixture::PASSWORD]);

        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
    }

    public function testLoginIsThrottledOnSixthAttempt(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('panel/login', ['email' => 'owner-a@test.local', 'password' => 'yanlis-sifre-1']);
            $this->assertSame(302, $response->response()->getStatusCode(), "{$i}. deneme throttle edilmemeli");
        }

        $sixth = $this->post('panel/login', ['email' => 'owner-a@test.local', 'password' => 'yanlis-sifre-1']);
        $sixth->assertStatus(429);
        $sixth->assertSee('Çok fazla deneme');

        // Doğru parola bile throttle süresince kabul edilmez.
        $this->post('panel/login', ['email' => 'owner-a@test.local', 'password' => Fixture::PASSWORD])->assertStatus(429);

        // Başka e-posta ayrı sayılır.
        $other = $this->post('panel/login', ['email' => 'owner-b@test.local', 'password' => Fixture::PASSWORD]);
        $this->assertRedirectsTo($other, 'panel');
    }

    public function testAdminLoginIsThrottledOnSixthAttempt(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('admin/login', ['email' => 'admin@test.local', 'password' => 'yanlis-sifre-1']);
        }

        $this->post('admin/login', ['email' => 'admin@test.local', 'password' => 'yanlis-sifre-1'])->assertStatus(429);
    }

    /** Test 11 */
    public function testAdminSessionIsNotAcceptedOnPanel(): void
    {
        foreach (['panel', 'panel/branches', 'panel/profile', 'panel/users'] as $path) {
            $this->assertRedirectsTo($this->asAdmin()->get($path), 'panel/login');
        }
    }

    /** Test 11 */
    public function testPanelSessionIsNotAcceptedOnAdmin(): void
    {
        foreach (['admin', 'admin/restaurants', 'admin/languages', 'admin/allergens'] as $path) {
            $this->assertRedirectsTo($this->asPanelUser($this->fx->ownerA)->get($path), 'admin/login');
        }
    }

    public function testForgedPanelKeyShapedLikeAdminIsRejected(): void
    {
        // admin_auth içeriğini panel_auth anahtarına kopyalamak işe yaramamalı (restaurant_id eşleşmez).
        $response = $this->withSession(['panel_auth' => ['id' => $this->fx->adminId, 'login_at' => time()]])->get('panel');

        $this->assertRedirectsTo($response, 'panel/login');
    }

    public function testSessionWithMismatchedRestaurantIsRejected(): void
    {
        $response = $this->withSession([
            'panel_auth' => ['id' => (int) $this->fx->ownerA['id'], 'restaurant_id' => $this->fx->restaurantB, 'login_at' => time()],
        ])->get('panel');

        $this->assertRedirectsTo($response, 'panel/login');
    }

    public function testBothSessionsCoexistAndLogoutOnlyRemovesOwnKey(): void
    {
        $both = [
            'admin_auth' => ['id' => $this->fx->adminId, 'login_at' => time()],
            'panel_auth' => ['id' => (int) $this->fx->ownerA['id'], 'restaurant_id' => $this->fx->restaurantA, 'login_at' => time()],
        ];

        $this->withSession($both)->get('admin')->assertOK();
        $this->withSession($both)->get('panel')->assertOK();

        $this->withSession($both)->post('panel/logout');
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
        $this->assertArrayHasKey('admin_auth', $_SESSION);

        $this->withSession($both)->post('admin/logout');
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('panel_auth', $_SESSION);
    }

    public function testLogoutIsPostOnly(): void
    {
        $this->asPanelUser($this->fx->ownerA)->get('panel/logout')->assertStatus(404);
        $this->asAdmin()->get('admin/logout')->assertStatus(404);
    }

    public function testUnauthenticatedAjaxGets401Json(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('panel/branches');

        $response->assertStatus(401);
        $this->assertSame('error', json_decode((string) $response->getJSON(), true)['status']);
    }

    public function testLoginPageRedirectsAuthenticatedUser(): void
    {
        $this->assertRedirectsTo($this->asPanelUser($this->fx->ownerA)->get('panel/login'), 'panel');
        $this->assertRedirectsTo($this->asAdmin()->get('admin/login'), 'admin');
        $this->get('panel/login')->assertOK();
    }

    /** Test 12 (oturum kısmı; menü kısmı PublicMenuTest'te) */
    public function testDeactivatedRestaurantDropsSessionOnNextRequest(): void
    {
        $this->asPanelUser($this->fx->ownerB)->get('panel')->assertOK();

        $this->db->table('restaurants')->where('id', $this->fx->restaurantB)->update(['is_active' => 0]);

        $this->assertRedirectsTo($this->asPanelUser($this->fx->ownerB)->get('panel'), 'panel/login');
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);

        // A etkilenmez.
        $this->asPanelUser($this->fx->ownerA)->get('panel')->assertOK();
    }

    public function testSoftDeletedRestaurantAndDeactivatedUserDropSession(): void
    {
        $this->db->table('restaurants')->where('id', $this->fx->restaurantB)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->assertRedirectsTo($this->asPanelUser($this->fx->ownerB)->get('panel'), 'panel/login');

        $this->db->table('restaurant_users')->where('id', $this->fx->managerA1['id'])->update(['is_active' => 0]);
        $this->assertRedirectsTo($this->asPanelUser($this->fx->managerA1)->get('panel'), 'panel/login');

        $this->db->table('restaurant_users')->where('id', $this->fx->ownerA['id'])->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->assertRedirectsTo($this->asPanelUser($this->fx->ownerA)->get('panel'), 'panel/login');
    }

    public function testSessionOlderThanPasswordChangeIsDropped(): void
    {
        $this->db->table('restaurant_users')->where('id', $this->fx->ownerA['id'])
            ->update(['password_changed_at' => date('Y-m-d H:i:s', time() + 60)]);

        $this->assertRedirectsTo($this->asPanelUser($this->fx->ownerA)->get('panel'), 'panel/login');
    }

    public function testPanelResponsesAreNotCacheable(): void
    {
        $response = $this->asPanelUser($this->fx->ownerA)->get('panel');

        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
    }

    public function testCsrfProtectionRejectsPostWithoutToken(): void
    {
        // Bu testte global CSRF filtresi açık bırakılır.
        config('Filters')->globals['before']['csrf'] = ['except' => ['api/*']];

        try {
            $response = $this->post('panel/login', ['email' => 'owner-a@test.local', 'password' => Fixture::PASSWORD]);
            $this->assertNotSame(302, $response->response()->getStatusCode());
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }

        $this->assertArrayNotHasKey('panel_auth', $_SESSION ?? []);
    }
}
