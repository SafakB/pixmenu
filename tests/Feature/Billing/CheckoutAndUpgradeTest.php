<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Tests\Support\DbTestCase;

/**
 * Panel self-servis: deneme → ücretli paket geçişi ve kart ödeme ekranı (PayTR henüz yok: stub).
 *
 * @internal
 */
final class CheckoutAndUpgradeTest extends DbTestCase
{
    /** Restoran A'yı Demo denemesine çevirir. */
    private function onTrial(?string $end = null): void
    {
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionA)->update([
            'plan_id' => $this->fx->plan['demo'], 'billing_cycle' => 'trial', 'status' => 'trialing', 'price' => '0.00',
            'current_period_end' => $end ?? date('Y-m-d', strtotime('+10 days')),
        ]);
    }

    private function invoice(int $restaurantId, int $subscriptionId, string $number, string $status = 'pending'): int
    {
        return $this->fx->insert('invoices', [
            'restaurant_id' => $restaurantId, 'subscription_id' => $subscriptionId, 'plan_id' => $this->fx->plan['pro'],
            'number' => $number, 'description' => 'Pro — Aylık', 'period_start' => date('Y-m-d'), 'period_end' => date('Y-m-d', strtotime('+1 month -1 day')),
            'subtotal' => '1000.00', 'discount' => '0.00', 'tax_rate' => '20.00', 'tax_amount' => '200.00', 'total' => '1200.00',
            'currency_code' => 'TRY', 'status' => $status, 'issued_at' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+7 days')),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function logs(): array
    {
        return $this->db->table('activity_logs')->get()->getResultArray();
    }

    // ------------------------------------------------------------------
    // Paket geçişi
    // ------------------------------------------------------------------

    public function testTrialOwnerUpgradesToPaidPlanAndLandsOnCheckout(): void
    {
        $this->onTrial();

        $plans = $this->asPanelUser($this->fx->ownerA)->get('panel/subscription/plans');
        $plans->assertSee('Bu pakete geç');
        $plans->assertSee('Paketiniz');

        $response = $this->asPanelUser($this->fx->ownerA)->post('panel/subscription/upgrade', [
            'plan_id' => (string) $this->fx->plan['pro'], 'billing_cycle' => 'yearly',
        ]);

        $old = $this->row('subscriptions', $this->fx->subscriptionA);
        $this->assertSame('ended', $old['status']);
        $this->assertNull($old['is_current']);

        $new = $this->db->table('subscriptions')->where('restaurant_id', $this->fx->restaurantA)->where('is_current', 1)->get()->getRowArray();
        $this->assertSame((int) $this->fx->plan['pro'], (int) $new['plan_id']);
        $this->assertSame('yearly', $new['billing_cycle']);
        $this->assertSame('active', $new['status']);

        $invoice = $this->db->table('invoices')->where('subscription_id', $new['id'])->get()->getRowArray();
        $this->assertSame('pending', $invoice['status']);
        $this->assertSame('12000.00', $invoice['total']);
        $this->assertRedirectsTo($response, 'panel/subscription/invoices/' . $invoice['id'] . '/pay');

        $log = $this->logs()[0];
        $this->assertSame('subscription.upgrade', $log['action']);
        $this->assertSame(['plan' => 'pro', 'cycle' => 'yearly', 'invoice_id' => (int) $invoice['id']], json_decode((string) $log['detail'], true));
    }

    public function testPaidOwnerSeesContactTextAndCannotSelfUpgrade(): void
    {
        $plans = $this->asPanelUser($this->fx->ownerA)->get('panel/subscription/plans');
        $plans->assertSee('Geçiş için bizimle iletişime geçin');
        $plans->assertDontSee('Bu pakete geç');

        $this->asPanelUser($this->fx->ownerA)->post('panel/subscription/upgrade', [
            'plan_id' => (string) $this->fx->plan['enterprise'], 'billing_cycle' => 'monthly',
        ]);

        $this->assertStringContainsString('bizimle iletişime geçin', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame(1, (int) $this->row('subscriptions', $this->fx->subscriptionA)['is_current']);
        $this->assertSame([], $this->logs());
    }

    public function testTrialPlanCannotBeTargetAndCycleIsRequired(): void
    {
        $this->onTrial();

        $this->asPanelUser($this->fx->ownerA)->post('panel/subscription/upgrade', ['plan_id' => (string) $this->fx->plan['demo'], 'billing_cycle' => 'monthly']);
        $this->assertArrayHasKey('plan_id', $_SESSION['errors'] ?? []);

        $this->asPanelUser($this->fx->ownerA)->post('panel/subscription/upgrade', ['plan_id' => (string) $this->fx->plan['pro'], 'billing_cycle' => '']);
        $this->assertArrayHasKey('billing_cycle', $_SESSION['errors'] ?? []);

        $this->assertSame(1, (int) $this->row('subscriptions', $this->fx->subscriptionA)['is_current']);
    }

    public function testExpiredTrialRestaurantCanUpgradeAndIsResumed(): void
    {
        $this->onTrial(date('Y-m-d', strtotime('-2 days')));
        (new \App\Services\SubscriptionService())->reconcile($this->fx->restaurantA);
        $this->assertSame('trial_expired', $this->row('restaurants', $this->fx->restaurantA)['suspension_reason']);

        // Askıdayken içerik sayfaları kapalı, paketler açık; bant "Paket seç" önerir.
        $this->asPanelUser($this->fx->ownerA)->get('panel/subscription/plans')->assertSee('Paket seç');

        $this->asPanelUser($this->fx->ownerA)->post('panel/subscription/upgrade', ['plan_id' => (string) $this->fx->plan['pro'], 'billing_cycle' => 'monthly']);

        $restaurant = $this->row('restaurants', $this->fx->restaurantA);
        $this->assertNull($restaurant['suspended_at']);
        $this->assertNull($restaurant['suspension_reason']);
    }

    public function testManualSuspensionBlocksUpgrade(): void
    {
        $this->onTrial();
        $this->db->table('restaurants')->where('id', $this->fx->restaurantA)->update(['suspended_at' => date('Y-m-d H:i:s'), 'suspension_reason' => 'manual']);

        $this->asPanelUser($this->fx->ownerA)->get('panel/subscription/plans')->assertDontSee('Bu pakete geç');
        $this->asPanelUser($this->fx->ownerA)->post('panel/subscription/upgrade', ['plan_id' => (string) $this->fx->plan['pro'], 'billing_cycle' => 'monthly']);

        $this->assertStringContainsString('yönetici tarafından askıya alındığı', (string) ($_SESSION['error'] ?? ''));
        $this->assertSame(1, (int) $this->row('subscriptions', $this->fx->subscriptionA)['is_current']);
    }

    // ------------------------------------------------------------------
    // Ödeme ekranı
    // ------------------------------------------------------------------

    public function testCheckoutShowsOwnPendingInvoiceWithoutNamedCardFields(): void
    {
        $id = $this->invoice($this->fx->restaurantA, $this->fx->subscriptionA, 'PM-2026-000101');

        $page = $this->asPanelUser($this->fx->ownerA)->get("panel/subscription/invoices/{$id}/pay");

        $page->assertOK();
        $page->assertSee('PM-2026-000101');
        $page->assertSee('1.200,00 TRY');
        $page->assertSee('Kart ile ödeme');

        $html = (string) $page->getBody();
        $this->assertStringContainsString('id="card-number"', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="card-[a-z]+"[^>]*name=/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="(card|cc|number|cvc|cvv|expiry)/', $html);
    }

    public function testPayStubDoesNotChangeInvoiceOrLog(): void
    {
        $id = $this->invoice($this->fx->restaurantA, $this->fx->subscriptionA, 'PM-2026-000102');

        $response = $this->asPanelUser($this->fx->ownerA)->post("panel/subscription/invoices/{$id}/pay", []);

        $this->assertRedirectsTo($response, 'panel/subscription');
        $this->assertStringContainsString('yakında aktif olacak', (string) ($_SESSION['info'] ?? ''));
        $this->assertSame('pending', $this->row('invoices', $id)['status']);
        $this->assertNull($this->row('invoices', $id)['paid_at']);
        $this->assertSame([], $this->logs());
    }

    public function testCheckoutIsolationAndNonPendingInvoices(): void
    {
        $foreign = $this->invoice($this->fx->restaurantB, $this->fx->subscriptionB, 'PM-2026-000201');
        $this->asPanelUser($this->fx->ownerA)->get("panel/subscription/invoices/{$foreign}/pay")->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post("panel/subscription/invoices/{$foreign}/pay", [])->assertStatus(404);

        $own = $this->invoice($this->fx->restaurantA, $this->fx->subscriptionA, 'PM-2026-000103');
        $this->asPanelUser($this->fx->managerA1)->get("panel/subscription/invoices/{$own}/pay")->assertStatus(404);
        $this->asPanelUser($this->fx->managerA1)->post('panel/subscription/upgrade', ['plan_id' => (string) $this->fx->plan['pro']])->assertStatus(404);

        $paid = $this->invoice($this->fx->restaurantA, $this->fx->subscriptionA, 'PM-2026-000104', 'paid');
        $this->assertRedirectsTo($this->asPanelUser($this->fx->ownerA)->get("panel/subscription/invoices/{$paid}/pay"), "panel/subscription/invoices/{$paid}");
    }

    public function testPayButtonsAppearOnlyForPendingInvoices(): void
    {
        $pending = $this->invoice($this->fx->restaurantA, $this->fx->subscriptionA, 'PM-2026-000105');
        $paid    = $this->invoice($this->fx->restaurantA, $this->fx->subscriptionA, 'PM-2026-000106', 'paid');

        $index = (string) $this->asPanelUser($this->fx->ownerA)->get('panel/subscription')->getBody();
        $this->assertStringContainsString("invoices/{$pending}/pay", $index);
        $this->assertStringNotContainsString("invoices/{$paid}/pay", $index);

        $this->asPanelUser($this->fx->ownerA)->get("panel/subscription/invoices/{$pending}")->assertSee('Kartla öde');
        $this->asPanelUser($this->fx->ownerA)->get("panel/subscription/invoices/{$paid}")->assertDontSee('Kartla öde');
        $this->asAdmin()->get("admin/invoices/{$pending}")->assertDontSee('Kartla öde');
    }
}
