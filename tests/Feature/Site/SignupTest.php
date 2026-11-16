<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use CodeIgniter\Events\Events;
use CodeIgniter\Honeypot\Exceptions\HoneypotException;
use Tests\Support\DbTestCase;

/**
 * Self-servis kayıt (/kayit): restoran + owner + şube + abonelik tek transaction'da,
 * otomatik giriş, hoş geldin e-postası, spam önlemleri.
 *
 * @internal
 */
final class SignupTest extends DbTestCase
{
    /** @var list<array<string, mixed>> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config('Email')->fromEmail = 'merhaba@pixmenu.test';
        $this->sent                = [];
        Events::on('email', function (array $archive): void {
            $this->sent[] = $archive;
        });
    }

    protected function tearDown(): void
    {
        Events::removeAllListeners('email');
        config('Email')->fromEmail = '';

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function form(array $override = []): array
    {
        return $override + [
            'name'             => 'Ayşe Yılmaz',
            'email'            => 'Ayse@Lokanta.test',
            'password'         => 'GucluSifre123',
            'password_confirm' => 'GucluSifre123',
            'terms'            => '1',
            'restaurant_name'  => 'Yeni Lokanta',
            'branch_name'      => 'Moda',
            'phone'            => '0216 000 00 00',
            'address'          => 'Moda Cad. No:1 Kadıköy',
            'currency_code'    => 'TRY',
            'language_ids'     => [(string) $this->fx->lang['en']],
            'plan_id'          => (string) $this->fx->plan['demo'],
            'billing_cycle'    => 'monthly',
        ];
    }

    private function restaurantCount(): int
    {
        return $this->db->table('restaurants')->countAllResults();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function newUser(string $email = 'ayse@lokanta.test'): ?array
    {
        return $this->db->table('restaurant_users')->where('email', $email)->get()->getRowArray();
    }

    public function testSignupPageRendersWizardWithPreselectedPlan(): void
    {
        $response = $this->get('kayit?plan=pro&cycle=yearly');

        $response->assertOK();
        $html = (string) $response->getBody();
        $pro  = $this->fx->plan['pro'];

        $this->assertMatchesRegularExpression('/id="plan-' . $pro . '" value="' . $pro . '"[^>]*checked/', $html);
        $this->assertStringContainsString('name="billing_cycle" value="yearly"', $html);
        $this->assertStringContainsString('name="pix_hp"', $html);          // honeypot
        $this->assertStringContainsString('name="terms"', $html);
        $this->assertStringNotContainsString('Google ile devam et', $html);  // Google yapılandırılmamış
        $response->assertSee('Kurumsal');
    }

    public function testLoggedInOwnerIsRedirectedToPanel(): void
    {
        $this->assertRedirectsTo($this->asPanelUser($this->fx->ownerA)->get('kayit'), 'panel');
    }

    public function testTrialSignupCreatesRestaurantBranchOwnerAndLogsIn(): void
    {
        $before   = $this->restaurantCount();
        $response = $this->post('kayit', $this->form());

        $this->assertRedirectsTo($response, 'panel');
        $this->assertSame($before + 1, $this->restaurantCount());

        $user = $this->newUser();
        $this->assertNotNull($user);
        $rid = (int) $user['restaurant_id'];

        $this->assertSame('owner', $user['role']);
        $this->assertSame('Ayşe Yılmaz', $user['name']);
        $this->assertNull($user['email_verified_at']);
        $this->assertNull($user['google_id']);
        $this->assertTrue(password_verify('GucluSifre123', (string) $user['password_hash']));

        $this->seeInDatabase('restaurant_translations', ['restaurant_id' => $rid, 'language_id' => $this->fx->lang['tr'], 'name' => 'Yeni Lokanta']);
        $this->seeInDatabase('restaurants', ['id' => $rid, 'slug' => 'yeni-lokanta', 'currency_code' => 'TRY', 'default_language_id' => $this->fx->lang['tr'], 'is_active' => 1]);
        $this->seeInDatabase('restaurant_languages', ['restaurant_id' => $rid, 'language_id' => $this->fx->lang['en']]);

        $branch = $this->db->table('branches')->where('restaurant_id', $rid)->get()->getRowArray();
        $this->assertNotNull($branch);
        $this->assertSame('0216 000 00 00', $branch['phone']);
        $this->assertSame('modern', $branch['template_code']);
        $this->seeInDatabase('branch_translations', ['branch_id' => $branch['id'], 'name' => 'Moda', 'address' => 'Moda Cad. No:1 Kadıköy']);

        $this->seeInDatabase('subscriptions', ['restaurant_id' => $rid, 'plan_id' => $this->fx->plan['demo'], 'billing_cycle' => 'trial', 'status' => 'trialing', 'is_current' => 1]);
        $this->assertSame(0, $this->db->table('invoices')->where('restaurant_id', $rid)->countAllResults());

        // Otomatik giriş
        $this->assertSame((int) $user['id'], $_SESSION['panel_auth']['id'] ?? null);
        $this->assertSame($rid, $_SESSION['panel_auth']['restaurant_id'] ?? null);
        $this->assertStringContainsString('14 günlük ücretsiz denemeniz başladı', (string) ($_SESSION['success'] ?? ''));

        // İşlem kaydı + hoş geldin e-postası
        $log = $this->db->table('activity_logs')->get()->getRowArray();
        $this->assertSame('signup.register', $log['action']);
        $this->assertSame('panel', $log['area']);
        $this->assertSame($rid, (int) $log['restaurant_id']);
        $this->assertSame(['plan' => 'demo', 'cycle' => 'trial', 'via' => 'email'], json_decode((string) $log['detail'], true));

        $this->assertCount(1, $this->sent);
        $this->assertSame(['ayse@lokanta.test'], array_values((array) $this->sent[0]['recipients']));
        $this->assertStringContainsString('Yeni Lokanta', (string) ($this->sent[0]['body'] ?? ''));
    }

    public function testPaidSignupIssuesPendingInvoiceAndOpensCheckout(): void
    {
        $response = $this->post('kayit', $this->form(['plan_id' => (string) $this->fx->plan['standard'], 'billing_cycle' => 'yearly']));

        $rid          = (int) $this->newUser()['restaurant_id'];
        $subscription = $this->db->table('subscriptions')->where('restaurant_id', $rid)->where('is_current', 1)->get()->getRowArray();
        $invoice      = $this->db->table('invoices')->where('restaurant_id', $rid)->get()->getRowArray();

        $this->assertSame('active', $subscription['status']);
        $this->assertSame('yearly', $subscription['billing_cycle']);
        $this->assertSame('pending', $invoice['status']);
        $this->assertSame(date('Y-m-d', strtotime('+7 days')), $invoice['due_date']);
        $this->assertRedirectsTo($response, 'panel/subscription/invoices/' . $invoice['id'] . '/pay');

        $checkout = $this->withSession()->get('panel/subscription/invoices/' . $invoice['id'] . '/pay');
        $checkout->assertOK();
        $checkout->assertSee((string) $invoice['number']);
        $checkout->assertSee('Kart ile ödeme');
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'restoran adı yok' => [['restaurant_name' => ''], 'restaurant_name'];
        yield 'zayıf şifre' => [['password' => 'kisa', 'password_confirm' => 'kisa'], 'password'];
        yield 'şifre tekrarı' => [['password_confirm' => 'BaskaSifre123'], 'password_confirm'];
        yield 'koşullar onaysız' => [['terms' => ''], 'terms'];
        yield 'kayıtlı e-posta' => [['email' => 'OWNER-A@test.local'], 'email'];
        yield 'pasif para birimi' => [['currency_code' => 'XAU'], 'currency_code'];
        yield 'geçersiz dil' => [['language_ids' => ['999999']], 'language_ids'];
        yield 'ücretli pakette dönem yok' => [['plan_id' => 'standard', 'billing_cycle' => 'weekly'], 'billing_cycle'];
    }

    /**
     * @dataProvider invalidInputs
     *
     * @param array<string, mixed> $override
     */
    public function testInvalidInputCreatesNothing(array $override, string $field): void
    {
        if (($override['plan_id'] ?? null) === 'standard') {
            $override['plan_id'] = (string) $this->fx->plan['standard'];
        }

        $before   = $this->restaurantCount();
        $response = $this->post('kayit', $this->form($override));

        $response->assertRedirect();
        $this->assertArrayHasKey($field, $_SESSION['errors'] ?? []);
        $this->assertSame($before, $this->restaurantCount());
        $this->assertArrayNotHasKey('panel_auth', $_SESSION);
        $this->assertSame([], $this->sent);
    }

    public function testPlanDeactivatedAfterFormLoadIsRejected(): void
    {
        $this->db->table('plans')->where('id', $this->fx->plan['pro'])->update(['is_active' => 0]);
        $before = $this->restaurantCount();

        $this->post('kayit', $this->form(['plan_id' => (string) $this->fx->plan['pro']]));

        $this->assertArrayHasKey('plan_id', $_SESSION['errors'] ?? []);
        $this->assertSame($before, $this->restaurantCount());
    }

    public function testSlugCollisionGetsSuffix(): void
    {
        $this->post('kayit', $this->form(['restaurant_name' => 'Restoran A']));

        $rid = (int) $this->newUser()['restaurant_id'];
        $this->seeInDatabase('restaurants', ['id' => $rid, 'slug' => 'restoran-a-2']);
    }

    public function testSignupAttemptsAreThrottledPerMinute(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('kayit', $this->form(['terms' => '']))->assertRedirect();
        }

        $sixth = $this->post('kayit', $this->form());
        $sixth->assertStatus(429);
        $sixth->assertSee('Çok fazla kayıt denemesi');
        $this->assertNull($this->newUser());
    }

    public function testDailyQuotaCountsOnlyValidSignups(): void
    {
        config('Pixmenu')->signupsPerIpPerDay = 1;

        $this->post('kayit', $this->form(['terms' => '']))->assertRedirect(); // geçersiz: kotayı tüketmez
        $this->assertRedirectsTo($this->post('kayit', $this->form()), 'panel');

        $second = $this->post('kayit', $this->form(['email' => 'ikinci@lokanta.test', 'restaurant_name' => 'İkinci']));
        $second->assertStatus(429);
        $this->assertNull($this->newUser('ikinci@lokanta.test'));
    }

    public function testHoneypotRejectsBots(): void
    {
        $before = $this->restaurantCount();

        try {
            $this->post('kayit', $this->form(['pix_hp' => 'http://spam.test']))->assertStatus(403);
        } catch (HoneypotException $e) {
            $this->assertSame(403, $e->getCode());
        }

        $this->assertSame($before, $this->restaurantCount());
    }
}
