<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Services\SubscriptionService;
use Tests\Support\DbTestCase;

/**
 * Askıya almanın menü/panel etkisi, panel abonelik sayfaları ve tenant izolasyonu
 * (docs/ABONELIK_PLANI.md §6, §8, §11).
 *
 * @internal
 */
final class SuspensionAndPanelTest extends DbTestCase
{
    private function suspendA(): void
    {
        (new SubscriptionService())->suspendManually($this->fx->restaurantA);
    }

    private function invoice(int $restaurantId, int $subscriptionId, string $number, string $due = '2030-01-08'): int
    {
        return $this->fx->insert('invoices', [
            'restaurant_id' => $restaurantId, 'subscription_id' => $subscriptionId, 'plan_id' => $this->fx->plan['pro'],
            'number' => $number, 'description' => 'Fatura ' . $number, 'period_start' => '2030-01-01', 'period_end' => '2030-01-31',
            'subtotal' => '1000.00', 'tax_rate' => '20.00', 'tax_amount' => '200.00', 'total' => '1200.00',
            'status' => 'pending', 'issued_at' => '2030-01-01', 'due_date' => $due,
        ]);
    }

    // ------------------------------------------------------------ Menü

    public function testSuspendedRestaurantMenuIs404AndOthersUnaffected(): void
    {
        $this->get('m/aaaaaaaaa1')->assertOK();   // cache'e girsin
        $this->suspendA();

        $this->get('m/aaaaaaaaa1')->assertStatus(404);
        $this->get('api/v1/menu/aaaaaaaaa1')->assertStatus(404);
        $this->get('m/bbbbbbbbb1')->assertOK();
    }

    // ------------------------------------------------------------ Panel

    public function testSuspendedOwnerCanLoginButContentRoutesRedirectToSubscription(): void
    {
        $this->suspendA();
        $owner = $this->fx->ownerA;

        $this->asPanelUser($owner)->get('panel')->assertOK();
        $this->asPanelUser($owner)->get('panel/profile')->assertOK();
        $this->asPanelUser($owner)->get('panel/subscription')->assertOK();
        $this->asPanelUser($owner)->get('panel/subscription/plans')->assertOK();

        foreach (['panel/branches', 'panel/restaurant', 'panel/users', "panel/branches/{$this->fx->branchA1}/categories", "panel/branches/{$this->fx->branchA1}/products"] as $path) {
            $this->assertRedirectsTo($this->asPanelUser($owner)->get($path), 'panel/subscription');
        }

        // Yazma da kapalı
        $this->assertRedirectsTo(
            $this->asPanelUser($owner)->post("panel/branches/{$this->fx->branchA1}/categories", ['translations' => ['tr' => ['name' => 'X']]]),
            'panel/subscription',
        );
        $this->dontSeeInDatabase('category_translations', ['name' => 'X']);
    }

    public function testSuspendedJsonRequestGets403(): void
    {
        $this->suspendA();

        $response = $this->asPanelUser($this->fx->ownerA)
            ->postJson("panel/branches/{$this->fx->branchA1}/categories/reorder", ['ids' => [$this->fx->categoryA1b, $this->fx->categoryA1]]);
        $response->assertStatus(403);
    }

    public function testSuspendedManagerIsRedirectedToDashboardWithNotice(): void
    {
        $this->suspendA();

        $this->assertRedirectsTo($this->asPanelUser($this->fx->managerA1)->get("panel/branches/{$this->fx->branchA1}/products"), 'panel');

        $dashboard = $this->asPanelUser($this->fx->managerA1)->get('panel');
        $dashboard->assertSee('Aboneliğiniz askıda');
        $dashboard->assertSee('restoran sahibinizle');
    }

    public function testNotSuspendedRestaurantUnaffected(): void
    {
        $this->suspendA();

        $this->asPanelUser($this->fx->ownerB)->get('panel/branches')->assertOK();
        $this->asPanelUser($this->fx->ownerB)->get("panel/branches/{$this->fx->branchB1}/categories")->assertOK();
    }

    public function testManagerCannotSeeSubscriptionPages(): void
    {
        $invoice = $this->invoice($this->fx->restaurantA, $this->fx->subscriptionA, 'PM-A-1');

        foreach (['panel/subscription', 'panel/subscription/plans', "panel/subscription/invoices/{$invoice}"] as $path) {
            $this->asPanelUser($this->fx->managerA1)->get($path)->assertStatus(404);
        }
    }

    public function testOwnerSeesOnlyOwnInvoices(): void
    {
        $mine   = $this->invoice($this->fx->restaurantA, $this->fx->subscriptionA, 'PM-A-1');
        $theirs = $this->invoice($this->fx->restaurantB, $this->fx->subscriptionB, 'PM-B-GIZLI');

        $html = (string) $this->asPanelUser($this->fx->ownerA)->get('panel/subscription')->getBody();
        $this->assertStringContainsString('PM-A-1', $html);
        $this->assertStringNotContainsString('PM-B-GIZLI', $html);

        $this->asPanelUser($this->fx->ownerA)->get("panel/subscription/invoices/{$mine}")->assertSee('PM-A-1');
        $this->asPanelUser($this->fx->ownerA)->get("panel/subscription/invoices/{$theirs}")->assertStatus(404);
    }

    public function testPricingPageMarksCurrentPlan(): void
    {
        $page = $this->asPanelUser($this->fx->ownerA)->get('panel/subscription/plans');

        $page->assertSee('Paketiniz');
        $page->assertSee('Kurumsal');
        $page->assertSee('Popüler');
    }

    public function testOverdueBannerShownToOwner(): void
    {
        $this->invoice($this->fx->restaurantA, $this->fx->subscriptionA, 'PM-A-1', date('Y-m-d', strtotime('-2 days')));

        $this->asPanelUser($this->fx->ownerA)->get('panel')->assertSee('Vadesi geçmiş 1 faturanız var');
    }

    public function testTrialBannerShowsDaysLeft(): void
    {
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionA)->update([
            'billing_cycle' => 'trial', 'status' => 'trialing', 'current_period_end' => date('Y-m-d', strtotime('+5 days')),
        ]);

        $this->asPanelUser($this->fx->ownerA)->get('panel')->assertSee('Deneme sürenizin bitmesine 5 gün kaldı');
    }
}
