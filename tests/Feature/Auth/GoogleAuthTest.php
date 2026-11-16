<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Config\Services;
use Tests\Support\DbTestCase;
use Tests\Support\FakeGoogleOAuth;
use Tests\Support\Fixture;

/**
 * Google ile giriş/kayıt (Panel\GoogleAuthController + PanelAuth::attemptGoogle) ve
 * şifresiz (yalnızca Google) hesapların güvenliği.
 *
 * @internal
 */
final class GoogleAuthTest extends DbTestCase
{
    private FakeGoogleOAuth $google;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google = new FakeGoogleOAuth();
        Services::injectMock('googleOAuth', $this->google);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('googleOAuth');

        parent::tearDown();
    }

    /**
     * Google'a yönlendirme adımını çalıştırır ve dönen state'i verir.
     */
    private function start(string $query = 'intent=login'): string
    {
        $this->get('panel/auth/google?' . $query)->assertRedirect();

        return (string) ($_SESSION['google_oauth']['state'] ?? '');
    }

    private function identity(string $sub, string $email, bool $verified = true, string $name = 'Google Kullanıcı'): void
    {
        $this->google->identity = ['sub' => $sub, 'email' => $email, 'email_verified' => $verified, 'name' => $name];
    }

    private function callback(string $state, string $extra = '&code=kod-123'): \CodeIgniter\Test\TestResponse
    {
        return $this->withSession()->get('panel/auth/google/callback?state=' . rawurlencode($state) . $extra);
    }

    private function verify(int $userId): void
    {
        $this->db->table('restaurant_users')->where('id', $userId)->update(['email_verified_at' => date('Y-m-d H:i:s')]);
    }

    // ------------------------------------------------------------------

    public function testNotConfiguredHidesButtonsAndRoutes(): void
    {
        $this->google->enabled = false;

        $this->get('panel/login')->assertDontSee('Google ile giriş yap');
        $this->get('kayit')->assertDontSee('Google ile devam et');
        $this->get('panel/auth/google')->assertStatus(404);
        $this->get('panel/auth/google/callback?state=x&code=y')->assertStatus(404);
    }

    public function testLoginPageShowsGoogleButtonAndSignupLinkButAdminDoesNot(): void
    {
        $panel = $this->get('panel/login');
        $panel->assertSee('Google ile giriş yap');
        $panel->assertSee('Ücretsiz kayıt olun');

        $admin = $this->get('admin/login');
        $admin->assertDontSee('Google ile giriş yap');
        $admin->assertDontSee('Ücretsiz kayıt olun');
    }

    public function testRedirectStoresSingleUseStateWithPkce(): void
    {
        $response = $this->get('panel/auth/google?intent=register&plan=pro&cycle=yearly');

        $this->assertSame('https://accounts.google.test/auth?state=' . $this->google->lastState, $response->getRedirectUrl());
        $flow = $_SESSION['google_oauth'];
        $this->assertSame($this->google->lastState, $flow['state']);
        $this->assertSame(['register', 'pro', 'yearly'], [$flow['intent'], $flow['plan'], $flow['cycle']]);
        $this->assertSame(
            rtrim(strtr(base64_encode(hash('sha256', $flow['verifier'], true)), '+/', '-_'), '='),
            $this->google->lastChallenge,
        );
    }

    public function testStateMismatchAndExpiredFlowAreRejected(): void
    {
        $this->identity('g-1', 'owner-a@test.local');
        $this->db->table('restaurant_users')->where('id', $this->fx->ownerA['id'])->update(['google_id' => 'g-1']);

        $this->start();
        $this->assertRedirectsTo($this->callback('yanlis-state'), 'panel/login');
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
        $this->assertSame([], $this->google->exchanges);

        // Süresi geçmiş akış
        $state = $this->start();
        $_SESSION['google_oauth']['at'] = time() - 3600;
        $this->callback($state);
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);

        // Tek kullanımlık: aynı state ikinci kez kullanılamaz
        $state = $this->start();
        $this->callback($state);
        $this->assertArrayHasKey('panel_auth', $_SESSION);
        unset($_SESSION['panel_auth']);
        $this->callback($state);
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
    }

    public function testCancelledAtGoogleReturnsToLoginWithInfo(): void
    {
        $state    = $this->start();
        $response = $this->callback($state, '&error=access_denied');

        $this->assertRedirectsTo($response, 'panel/login');
        $this->assertSame('Google ile giriş iptal edildi.', $_SESSION['info'] ?? null);
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
    }

    public function testKnownGoogleIdLogsInAndIsLogged(): void
    {
        $this->db->table('restaurant_users')->where('id', $this->fx->ownerA['id'])->update(['google_id' => 'g-1']);
        $this->identity('g-1', 'baska-adres@gmail.test');

        $response = $this->callback($this->start());

        $this->assertRedirectsTo($response, 'panel');
        $this->assertSame((int) $this->fx->ownerA['id'], $_SESSION['panel_auth']['id'] ?? null);
        $this->assertSame([['code' => 'kod-123', 'verifier' => $this->google->exchanges[0]['verifier']]], $this->google->exchanges);

        $log = $this->db->table('activity_logs')->get()->getRowArray();
        $this->assertSame('auth.login', $log['action']);
        $this->assertSame('panel', $log['area']);
        $this->assertSame(['via' => 'google'], json_decode((string) $log['detail'], true));
    }

    public function testVerifiedAccountIsLinkedAndKeepsPassword(): void
    {
        $id = (int) $this->fx->ownerA['id'];
        $this->verify($id);
        $this->identity('g-2', 'Owner-A@test.local');

        $this->assertRedirectsTo($this->callback($this->start()), 'panel');

        $row = $this->row('restaurant_users', $id);
        $this->assertSame('g-2', $row['google_id']);
        $this->assertTrue(password_verify(Fixture::PASSWORD, (string) $row['password_hash']));
        $this->assertSame('Google hesabınız bağlandı.', $_SESSION['success'] ?? null);
    }

    public function testUnverifiedAccountLinkClearsPasswordAndOldSessions(): void
    {
        $id = (int) $this->fx->ownerA['id'];
        $this->identity('g-3', 'owner-a@test.local');

        $this->callback($this->start());

        $row = $this->row('restaurant_users', $id);
        $this->assertSame('g-3', $row['google_id']);
        $this->assertNull($row['password_hash']);
        $this->assertNotNull($row['email_verified_at']);
        $this->assertNotNull($row['password_changed_at']);
        $this->assertStringContainsString('önceki şifreniz kaldırıldı', (string) ($_SESSION['warning'] ?? ''));
        $this->assertSame($id, $_SESSION['panel_auth']['id'] ?? null);

        // Bağlamadan önce açılmış (ör. hesabı önceden açan kişinin) oturumu düşer.
        $old = $this->withSession(['panel_auth' => ['id' => $id, 'restaurant_id' => $this->fx->restaurantA, 'login_at' => time() - 120]])->get('panel');
        $this->assertRedirectsTo($old, 'panel/login');

        // Eski şifre artık çalışmaz.
        $this->withSession([])->post('panel/login', ['email' => 'owner-a@test.local', 'password' => Fixture::PASSWORD]);
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
    }

    public function testAccountLinkedToAnotherGoogleIdentityIsNotOverwritten(): void
    {
        $id = (int) $this->fx->ownerA['id'];
        $this->db->table('restaurant_users')->where('id', $id)->update(['google_id' => 'baska-g']);
        $this->identity('g-4', 'owner-a@test.local');

        $this->assertRedirectsTo($this->callback($this->start()), 'panel/login');

        $this->assertSame('baska-g', $this->row('restaurant_users', $id)['google_id']);
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
        $this->assertSame('Bu e-posta adresi başka bir Google hesabına bağlı.', $_SESSION['error'] ?? null);
    }

    public function testInactiveUserAndUnverifiedGoogleEmailAreRejected(): void
    {
        $this->db->table('restaurant_users')->where('id', $this->fx->ownerA['id'])->update(['google_id' => 'g-5', 'is_active' => 0]);
        $this->identity('g-5', 'owner-a@test.local');
        $this->callback($this->start());
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);

        $this->identity('g-6', 'owner-b@test.local', false);
        $this->callback($this->start());
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
        $this->assertNull($this->row('restaurant_users', (int) $this->fx->ownerB['id'])['google_id']);
    }

    public function testUnknownIdentitySignsUpWithoutPassword(): void
    {
        $this->identity('g-new', 'mehmet@gmail.test', true, 'Mehmet Kaya');

        $response = $this->callback($this->start('intent=register&plan=demo'));
        $this->assertSame(site_url('kayit') . '?plan=demo&cycle=monthly', $response->getRedirectUrl());
        $this->assertSame('g-new', $_SESSION['signup_google']['sub'] ?? null);

        $page = $this->withSession()->get('kayit');
        $page->assertOK();
        $page->assertSee('Google hesabınızla kaydoluyorsunuz');
        $page->assertSee('mehmet@gmail.test');
        $this->assertStringNotContainsString('name="password"', (string) $page->getBody());

        // E-posta/şifre formdan gelse bile yok sayılır; kimlik oturumdan okunur.
        $done = $this->withSession()->post('kayit', [
            'via'             => 'google',
            'name'            => 'Mehmet Kaya',
            'email'           => 'sahte@test.local',
            'password'        => 'SaldirganSifre1',
            'terms'           => '1',
            'restaurant_name' => 'Kaya Kebap',
            'branch_name'     => 'Merkez',
            'currency_code'   => 'TRY',
            'plan_id'         => (string) $this->fx->plan['demo'],
            'billing_cycle'   => 'monthly',
        ]);
        $this->assertRedirectsTo($done, 'panel');

        $user = $this->db->table('restaurant_users')->where('google_id', 'g-new')->get()->getRowArray();
        $this->assertSame('mehmet@gmail.test', $user['email']);
        $this->assertNull($user['password_hash']);
        $this->assertNotNull($user['email_verified_at']);
        $this->assertSame((int) $user['id'], $_SESSION['panel_auth']['id'] ?? null);
        $this->assertArrayNotHasKey('signup_google', $_SESSION);
        $this->dontSeeInDatabase('restaurant_users', ['email' => 'sahte@test.local']);

        // Şifresiz hesaba hiçbir şifreyle girilemez.
        foreach (['SaldirganSifre1', '', 'password'] as $password) {
            $this->withSession([])->post('panel/login', ['email' => 'mehmet@gmail.test', 'password' => $password]);
            $this->assertArrayNotHasKey('panel_auth', $_SESSION);
        }
    }

    public function testExpiredGoogleSignupIsNotUsed(): void
    {
        $before = $this->db->table('restaurants')->countAllResults();

        $response = $this->withSession([
            'signup_google' => ['sub' => 'g-old', 'email' => 'eski@gmail.test', 'name' => 'Eski', 'at' => time() - 4000],
        ])->post('kayit', [
            'via' => 'google', 'name' => 'Eski', 'terms' => '1', 'restaurant_name' => 'Eski Lokanta',
            'currency_code' => 'TRY', 'plan_id' => (string) $this->fx->plan['demo'],
        ]);

        $this->assertRedirectsTo($response, 'kayit');
        $this->assertSame($before, $this->db->table('restaurants')->countAllResults());
        $this->assertStringContainsString('süresi doldu', (string) ($_SESSION['error'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Şifresiz hesap: profil ve hesap yaşam döngüsü
    // ------------------------------------------------------------------

    public function testPasswordlessUserSetsFirstPasswordWithoutCurrent(): void
    {
        $id = (int) $this->fx->ownerA['id'];
        $this->db->table('restaurant_users')->where('id', $id)->update(['password_hash' => null, 'google_id' => 'g-7']);
        $owner = $this->row('restaurant_users', $id);

        $page = $this->asPanelUser($owner)->get('panel/profile');
        $page->assertOK();
        $page->assertSee('Şifre belirle');
        $this->assertStringNotContainsString('name="current_password"', (string) $page->getBody());

        $this->asPanelUser($owner)->post('panel/profile/password', ['password' => 'IlkSifrem2026', 'password_confirm' => 'IlkSifrem2026']);
        $this->assertTrue(password_verify('IlkSifrem2026', (string) $this->row('restaurant_users', $id)['password_hash']));

        $this->withSession([])->post('panel/login', ['email' => 'owner-a@test.local', 'password' => 'IlkSifrem2026']);
        $this->assertSame($id, $_SESSION['panel_auth']['id'] ?? null);
    }

    public function testUserWithPasswordStillNeedsCurrentPassword(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post('panel/profile/password', ['password' => 'YeniSifre2026', 'password_confirm' => 'YeniSifre2026']);

        $this->assertArrayHasKey('current_password', $_SESSION['errors'] ?? []);
    }

    public function testOwnEmailChangeDropsVerificationAndDeletedUserReleasesGoogleId(): void
    {
        $id = (int) $this->fx->ownerA['id'];
        $this->verify($id);

        $this->asPanelUser($this->fx->ownerA)->post('panel/profile', ['name' => 'Owner A', 'email' => 'yeni-owner@test.local']);
        $this->assertNull($this->row('restaurant_users', $id)['email_verified_at']);

        $manager = (int) $this->fx->managerA1['id'];
        $this->db->table('restaurant_users')->where('id', $manager)->update(['google_id' => 'g-manager']);
        $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$manager}/delete", []);

        $this->assertNull($this->db->table('restaurant_users')->where('id', $manager)->get()->getRowArray()['google_id']);
    }
}
