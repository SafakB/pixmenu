<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Services\BillingRunner;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\StreamFilterTrait;
use Tests\Support\DbTestCase;

/**
 * Günlük faturalama işi — uçtan uca senaryo, idempotentlik ve bildirimler
 * (docs/ABONELIK_PLANI.md §5, §7).
 *
 * @internal
 */
final class BillingRunnerTest extends DbTestCase
{
    use StreamFilterTrait;

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config('Email')->fromEmail = 'fatura@pixmenu.test';
        $this->sent                = [];
        Events::on('email', function (array $archive): void {
            $this->sent[] = $archive;
        });

        // A: 1–30 Kasım 2026 aylık dönem. B: aboneliği yok sayılmasın diye uzun dönem.
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionA)->update([
            'started_at' => '2026-11-01', 'current_period_start' => '2026-11-01', 'current_period_end' => '2026-11-30',
        ]);
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionB)->update([
            'billing_cycle' => 'yearly', 'price' => '0.00',
            'started_at' => '2026-01-01', 'current_period_start' => '2026-01-01', 'current_period_end' => '2030-12-31',
        ]);
    }

    protected function tearDown(): void
    {
        Events::removeAllListeners('email');
        config('Email')->fromEmail = '';

        parent::tearDown();
    }

    /**
     * @return array<string, int>
     */
    private function runOn(string $date): array
    {
        return (new BillingRunner())->run($date);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoicesA(): array
    {
        return $this->db->table('invoices')->where('restaurant_id', $this->fx->restaurantA)->orderBy('period_start')->get()->getResultArray();
    }

    private function subjects(): string
    {
        return implode(' | ', array_map(static fn (array $m): string => (string) ($m['subject'] ?? ''), $this->sent));
    }

    public function testFullCycleIssueRemindOverdueSuspendAndPay(): void
    {
        // 22 Kasım: dönem bitimine 8 gün → yenileme faturası yok. Ama içinde bulunulan dönemin faturası da yok → kesilir.
        $this->runOn('2026-11-22');
        $this->assertCount(1, $this->invoicesA());
        $this->assertSame('2026-11-01', $this->invoicesA()[0]['period_start']);
        // Bu ilk faturayı ödenmiş sayalım (senaryonun odağı yenileme faturası).
        $this->db->table('invoices')->where('id', $this->invoicesA()[0]['id'])->update(['status' => 'paid']);

        // 23 Kasım: dönem bitimine 7 gün → Aralık faturası, vade = 30 Kasım, e-posta.
        $this->sent = [];
        $stats      = $this->runOn('2026-11-23');
        $this->assertSame(1, $stats['invoices_issued']);
        $december = $this->invoicesA()[1];
        $this->assertSame('2026-12-01', $december['period_start']);
        $this->assertSame('2026-12-31', $december['period_end']);
        $this->assertSame('2026-11-30', $december['due_date']);
        $this->assertNotNull($this->row('invoices', (int) $december['id'])['issued_notified_at']);
        $this->assertStringContainsString('Yeni faturanız', $this->subjects());
        $this->assertSame(['owner-a@test.local'], array_values((array) $this->sent[0]['recipients']));

        // Aynı gün tekrar: hiçbir şey olmaz (idempotent).
        $this->sent = [];
        $again      = $this->runOn('2026-11-23');
        $this->assertSame(0, $again['invoices_issued']);
        $this->assertCount(2, $this->invoicesA());
        $this->assertSame([], $this->sent);

        // 27 Kasım: vade hatırlatması, bir kez.
        $this->runOn('2026-11-27');
        $this->assertStringContainsString('hatırlatması', $this->subjects());
        $this->sent = [];
        $this->runOn('2026-11-28');
        $this->assertSame([], $this->sent);

        // 1 Aralık: dönem ilerler, fatura açık → past_due + gecikme e-postası.
        $stats = $this->runOn('2026-12-01');
        $this->assertSame(1, $stats['periods_advanced']);
        $sub = $this->row('subscriptions', $this->fx->subscriptionA);
        $this->assertSame('2026-12-01', $sub['current_period_start']);
        $this->assertSame('past_due', $sub['status']);
        $this->assertNull($this->row('restaurants', $this->fx->restaurantA)['suspended_at']);
        $this->assertStringContainsString('Vadesi geçmiş', $this->subjects());

        // 8 Aralık: vade + 7 = 7 Aralık < 8 Aralık → askı.
        $this->runOn('2026-12-07');
        $this->assertNull($this->row('restaurants', $this->fx->restaurantA)['suspended_at'], '7 Aralık henüz eşik içinde');
        $this->sent = [];
        $stats      = $this->runOn('2026-12-08');
        $this->assertSame(1, $stats['suspended']);
        $this->assertSame('overdue', $this->row('restaurants', $this->fx->restaurantA)['suspension_reason']);
        $this->assertSame('suspended', $this->row('subscriptions', $this->fx->subscriptionA)['status']);
        $this->assertStringContainsString('askıya alındı', $this->subjects());
        $this->get('m/aaaaaaaaa1')->assertStatus(404);

        // Askıdayken yenileme faturası kesilmez, dönem ilerlemez.
        $this->runOn('2027-01-05');
        $this->assertCount(2, $this->invoicesA());
        $this->assertSame('2026-12-01', $this->row('subscriptions', $this->fx->subscriptionA)['current_period_start']);

        // Ödeme → askı kalkar; sonraki çalışmada dönem bugünden başlar ve faturası kesilir.
        $this->db->table('invoices')->where('id', $december['id'])->update(['status' => 'paid']);
        (new \App\Services\SubscriptionService())->reconcile($this->fx->restaurantA, '2027-01-10');
        $this->assertNull($this->row('restaurants', $this->fx->restaurantA)['suspended_at']);

        $this->runOn('2027-01-10');
        $sub = $this->row('subscriptions', $this->fx->subscriptionA);
        $this->assertSame('2027-01-10', $sub['current_period_start']);
        $this->assertSame('2027-02-09', $sub['current_period_end']);
        $this->assertSame('2027-01-10', $this->invoicesA()[2]['period_start']);
        $this->get('m/aaaaaaaaa1')->assertOK();
    }

    public function testRestaurantPercentDiscountAppliesUntilEndDate(): void
    {
        $this->db->table('restaurants')->where('id', $this->fx->restaurantA)->update([
            'discount_percent' => '20.00', 'discount_until' => '2026-11-30',
        ]);

        // Kasım dönemi (başı 1 Kasım ≤ bitiş) → %20 indirim.
        $this->runOn('2026-11-22');
        $nov      = $this->invoicesA()[0];
        $subtotal = (float) $nov['subtotal'];
        $this->assertGreaterThan(0, $subtotal);
        $this->assertSame(number_format($subtotal * 0.2, 2, '.', ''), $nov['discount']);
        $this->assertSame(number_format($subtotal * 0.8 * 1.2, 2, '.', ''), $nov['total']);

        // Aralık dönemi (başı 1 Aralık > bitiş) → indirim yok.
        $this->runOn('2026-11-23');
        $dec = $this->invoicesA()[1];
        $this->assertSame('2026-12-01', $dec['period_start']);
        $this->assertSame('0.00', $dec['discount']);
    }

    public function testGraceDaysOverridePerRestaurant(): void
    {
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionA)->update(['grace_days' => 30]);
        $this->fx->insert('invoices', [
            'restaurant_id' => $this->fx->restaurantA, 'subscription_id' => $this->fx->subscriptionA, 'plan_id' => $this->fx->plan['pro'],
            'number' => 'PM-T-1', 'description' => 'x', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'subtotal' => '1000.00', 'total' => '1200.00', 'status' => 'pending', 'issued_at' => '2026-11-01', 'due_date' => '2026-11-08',
        ]);

        $this->runOn('2026-11-20');
        $this->assertNull($this->row('restaurants', $this->fx->restaurantA)['suspended_at']);
        $this->assertSame('past_due', $this->row('subscriptions', $this->fx->subscriptionA)['status']);
    }

    public function testTrialReminderThenExpirySuspends(): void
    {
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionB)->update([
            'plan_id' => $this->fx->plan['demo'], 'billing_cycle' => 'trial', 'status' => 'trialing', 'price' => '0.00',
            'started_at' => '2026-11-01', 'current_period_start' => '2026-11-01', 'current_period_end' => '2026-11-14',
        ]);

        $this->runOn('2026-11-11');
        $this->assertStringContainsString('Deneme sürenizin bitmesine 3 gün', $this->subjects());
        $this->assertNotNull($this->row('subscriptions', $this->fx->subscriptionB)['trial_reminder_sent_at']);

        $this->runOn('2026-11-14');
        $this->assertNull($this->row('restaurants', $this->fx->restaurantB)['suspended_at'], 'Son gün hâlâ açık');

        $stats = $this->runOn('2026-11-15');
        $this->assertSame('trial_expired', $this->row('restaurants', $this->fx->restaurantB)['suspension_reason']);
        $this->assertGreaterThanOrEqual(1, $stats['suspended']);
        $this->assertStringContainsString('Deneme süreniz sona erdi', $this->subjects());
        $this->assertSame(0, $this->db->table('invoices')->where('restaurant_id', $this->fx->restaurantB)->countAllResults(), 'Denemede fatura kesilmez');
    }

    public function testCancelAtPeriodEndEndsAndSuspends(): void
    {
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionA)->update(['cancel_at_period_end' => 1]);

        $stats = $this->runOn('2026-11-25');
        $this->assertSame(1, $stats['invoices_issued'], 'Yalnızca içinde bulunulan dönemin faturası; yenileme yok');

        $this->db->table('invoices')->where('restaurant_id', $this->fx->restaurantA)->update(['status' => 'paid']);
        $stats = $this->runOn('2026-12-01');
        $this->assertSame(1, $stats['ended']);

        $sub = $this->row('subscriptions', $this->fx->subscriptionA);
        $this->assertSame('ended', $sub['status']);
        $this->assertNull($sub['is_current']);
        $this->assertSame('no_subscription', $this->row('restaurants', $this->fx->restaurantA)['suspension_reason']);
    }

    public function testDryRunChangesNothingAndSendsNothing(): void
    {
        $stats = (new BillingRunner())->run('2026-11-25', true);

        $this->assertGreaterThan(0, $stats['invoices_issued']);
        $this->assertSame(0, $this->db->table('invoices')->countAllResults());
        $this->assertSame([], $this->sent);
    }

    public function testCommandRuns(): void
    {
        command('billing:run --dry-run --date=2026-11-25');
        $this->assertSame(0, $this->db->table('invoices')->countAllResults());
        $this->assertStringContainsString('Kesilen fatura', $this->getStreamFilterBuffer());
        $this->assertStringContainsString('2026-11-25', $this->getStreamFilterBuffer());
    }
}
