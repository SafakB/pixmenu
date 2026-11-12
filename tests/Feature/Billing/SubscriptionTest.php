<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Exceptions\BusinessRuleException;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Tests\Support\DbTestCase;

/**
 * Paket, abonelik ve fatura kuralları (docs/ABONELIK_PLANI.md §5, §6).
 *
 * @internal
 */
final class SubscriptionTest extends DbTestCase
{
    private function moveToPlan(int $restaurantId, string $code, string $cycle = 'monthly'): void
    {
        $this->db->table('subscriptions')->where('restaurant_id', $restaurantId)->update(['is_current' => null, 'status' => 'ended']);
        $this->fx->subscription($restaurantId, $code, $cycle);
    }

    // ------------------------------------------------------------ Şube limiti

    public function testBranchLimitBlocksAdminAndPanelCreate(): void
    {
        $this->moveToPlan($this->fx->restaurantB, 'standard'); // 1 şube; B'nin zaten 1 aktif şubesi var

        $post = [
            'default_language_id' => (string) $this->fx->lang['tr'],
            'is_active'           => '1',
            'translations'        => ['tr' => ['name' => 'Yeni Şube']],
        ];

        $before = $this->db->table('branches')->where('restaurant_id', $this->fx->restaurantB)->countAllResults();

        $this->asAdmin()->post("admin/restaurants/{$this->fx->restaurantB}/branches", $post)->assertSessionHas('error');
        $this->asPanelUser($this->fx->ownerB)->post('panel/branches', $post)->assertSessionHas('error');

        $this->assertSame($before, $this->db->table('branches')->where('restaurant_id', $this->fx->restaurantB)->countAllResults());
    }

    public function testInactiveBranchIsAllowedButActivatingItOverLimitIsBlocked(): void
    {
        $this->moveToPlan($this->fx->restaurantB, 'standard');

        // Pasif şube limite sayılmaz → oluşturulabilir.
        $this->asPanelUser($this->fx->ownerB)->post('panel/branches', [
            'default_language_id' => (string) $this->fx->lang['tr'],
            'translations'        => ['tr' => ['name' => 'Pasif Şube']],
        ])->assertRedirect();

        $id = (int) $this->db->table('branches')->where('restaurant_id', $this->fx->restaurantB)->where('is_active', 0)->get()->getRow()->id;

        // Aktifleştirme limit nedeniyle reddedilir.
        $this->asPanelUser($this->fx->ownerB)->post("panel/branches/{$id}/toggle")->assertSessionHas('error');
        $this->assertSame('0', (string) $this->row('branches', $id)['is_active']);

        // Mevcut şube pasifleştirilince yer açılır.
        $this->asPanelUser($this->fx->ownerB)->post("panel/branches/{$this->fx->branchB1}/toggle");
        $this->asPanelUser($this->fx->ownerB)->post("panel/branches/{$id}/toggle");
        $this->assertSame('1', (string) $this->row('branches', $id)['is_active']);
    }

    public function testUnlimitedPlanAllowsBranches(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post('panel/branches', [
            'default_language_id' => (string) $this->fx->lang['tr'],
            'is_active'           => '1',
            'translations'        => ['tr' => ['name' => 'Üçüncü Şube']],
        ])->assertRedirect();

        $this->assertSame(3, $this->db->table('branches')->where('restaurant_id', $this->fx->restaurantA)->where('is_active', 1)->countAllResults());
    }

    public function testPanelBranchListDisablesNewButtonWhenLimitReached(): void
    {
        $this->moveToPlan($this->fx->restaurantB, 'standard');

        $html = (string) $this->asPanelUser($this->fx->ownerB)->get('panel/branches')->getBody();
        $this->assertStringNotContainsString('href="' . site_url('panel/branches/new') . '"', $html);
        $this->assertStringContainsString('Paketinizin', $html);
    }

    // ------------------------------------------------------------ Paket değişimi

    public function testDowngradeIsBlockedWhenBranchesExceedLimit(): void
    {
        $this->asAdmin()->post("admin/restaurants/{$this->fx->restaurantA}/subscription/change", [
            'plan_id' => (string) $this->fx->plan['standard'], 'billing_cycle' => 'monthly',
        ])->assertSessionHas('error');

        $current = $this->db->table('subscriptions')->where('restaurant_id', $this->fx->restaurantA)->where('is_current', 1)->get()->getRowArray();
        $this->assertSame($this->fx->subscriptionA, (int) $current['id'], 'Paket değişmemeli');
    }

    public function testDowngradeIsBlockedWhenDomainDefinedAndPlanHasNoDomain(): void
    {
        $this->fx->insert('restaurant_domains', ['restaurant_id' => $this->fx->restaurantB, 'domain' => 'menu.b-test.com', 'is_active' => 1]);

        $this->expectException(BusinessRuleException::class);
        (new SubscriptionService())->changePlan($this->fx->restaurantB, $this->fx->plan['demo'], 'trial');
    }

    public function testChangePlanEndsOldStartsNewAndIssuesFullInvoiceWithDiscountAndTax(): void
    {
        $response = $this->asAdmin()->post("admin/restaurants/{$this->fx->restaurantB}/subscription/change", [
            'plan_id' => (string) $this->fx->plan['enterprise'], 'billing_cycle' => 'yearly', 'discount' => '1.000,00',
        ]);
        $this->assertRedirectsTo($response, "admin/restaurants/{$this->fx->restaurantB}/subscription");

        $old = $this->row('subscriptions', $this->fx->subscriptionB);
        $this->assertSame('ended', $old['status']);
        $this->assertNull($old['is_current']);

        $new = $this->db->table('subscriptions')->where('restaurant_id', $this->fx->restaurantB)->where('is_current', 1)->get()->getRowArray();
        $this->assertSame('yearly', $new['billing_cycle']);
        $this->assertSame('active', $new['status']);
        $this->assertSame(date('Y-m-d'), $new['current_period_start']);
        $this->assertSame('20000.00', $new['price']);

        $invoice = $this->db->table('invoices')->where('subscription_id', $new['id'])->get()->getRowArray();
        $this->assertSame('20000.00', $invoice['subtotal']);
        $this->assertSame('1000.00', $invoice['discount']);
        $this->assertSame('20.00', $invoice['tax_rate']);
        $this->assertSame('3800.00', $invoice['tax_amount']);   // (20000 − 1000) × %20
        $this->assertSame('22800.00', $invoice['total']);
        $this->assertSame('pending', $invoice['status']);
        $this->assertSame(sprintf('PM-%s-%06d', date('Y'), $invoice['id']), $invoice['number']);
        $this->assertSame(date('Y-m-d', strtotime('+7 days')), $invoice['due_date']);
    }

    public function testPlanPriceChangeDoesNotAffectExistingSubscription(): void
    {
        $this->db->table('plans')->where('id', $this->fx->plan['pro'])->update(['price_monthly' => '5000.00']);
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionA)->update(['price' => '1000.00']);

        $sub = (new SubscriptionService())->current($this->fx->restaurantA);
        $id  = (new InvoiceService())->issue($sub, '2030-01-01', '2030-01-31', '2029-12-25', '2029-12-31');

        $this->assertSame('1000.00', $this->row('invoices', $id)['subtotal']);
    }

    // ------------------------------------------------------------ Ödeme / iptal

    public function testOverdueSuspensionIsLiftedWhenInvoicePaid(): void
    {
        $service = new SubscriptionService();
        $sub     = $service->current($this->fx->restaurantA);
        $id      = (new InvoiceService())->issue($sub, '2026-01-01', '2026-01-31', '2026-01-01', '2026-01-08');

        $service->reconcile($this->fx->restaurantA, '2026-01-20');   // vade + 7 < 20 → askı
        $this->assertSame('overdue', $this->row('restaurants', $this->fx->restaurantA)['suspension_reason']);
        $this->assertSame('suspended', $this->row('subscriptions', $this->fx->subscriptionA)['status']);

        $this->asAdmin()->post("admin/invoices/{$id}/pay", [
            'payment_method' => 'bank_transfer', 'payment_reference' => 'DEKONT-1', 'paid_at' => date('Y-m-d'),
        ])->assertSessionHas('success');

        $invoice = $this->row('invoices', $id);
        $this->assertSame('paid', $invoice['status']);
        $this->assertSame('DEKONT-1', $invoice['payment_reference']);
        $this->assertSame($this->fx->adminId, (int) $invoice['paid_marked_by']);

        $restaurant = $this->row('restaurants', $this->fx->restaurantA);
        $this->assertNull($restaurant['suspended_at']);
        $this->assertNull($restaurant['suspension_reason']);
        $this->assertSame('active', $this->row('subscriptions', $this->fx->subscriptionA)['status']);
    }

    public function testVoidKeepsInvoiceAndOnlyPendingCanBeVoidedOrPaid(): void
    {
        $sub = (new SubscriptionService())->current($this->fx->restaurantA);
        $id  = (new InvoiceService())->issue($sub, '2030-01-01', '2030-01-31', '2029-12-25', '2029-12-31');

        $this->asAdmin()->post("admin/invoices/{$id}/void")->assertSessionHas('success');
        $this->assertSame('void', $this->row('invoices', $id)['status']);

        $this->asAdmin()->post("admin/invoices/{$id}/pay", ['payment_method' => 'cash', 'paid_at' => date('Y-m-d')])->assertSessionHas('error');
        $this->asAdmin()->post("admin/invoices/{$id}/void")->assertSessionHas('error');
        $this->assertSame('void', $this->row('invoices', $id)['status']);
    }

    public function testManualSuspendAndResume(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin()->post("admin/restaurants/{$a}/subscription/suspend");
        $this->assertSame('manual', $this->row('restaurants', $a)['suspension_reason']);

        // Cron (reconcile) elle askıyı kaldırmaz.
        (new SubscriptionService())->reconcile($a);
        $this->assertSame('manual', $this->row('restaurants', $a)['suspension_reason']);

        $this->asAdmin()->post("admin/restaurants/{$a}/subscription/resume")->assertSessionHas('success');
        $this->assertNull($this->row('restaurants', $a)['suspended_at']);
    }

    // ------------------------------------------------------------ Yeni restoran

    public function testNewRestaurantGetsDefaultTrialWithoutInvoice(): void
    {
        $this->asAdmin()->post('admin/restaurants', [
            'currency_code' => 'TRY', 'language_ids' => [(string) $this->fx->lang['tr']],
            'default_language_id' => (string) $this->fx->lang['tr'], 'translations' => ['tr' => ['name' => 'Deneme Lokantası']],
        ]);

        $restaurant = $this->db->table('restaurants')->where('slug', 'deneme-lokantasi')->get()->getRowArray();
        $sub        = $this->db->table('subscriptions')->where('restaurant_id', $restaurant['id'])->get()->getRowArray();

        $this->assertSame((string) $this->fx->plan['demo'], (string) $sub['plan_id']);
        $this->assertSame('trial', $sub['billing_cycle']);
        $this->assertSame('trialing', $sub['status']);
        $this->assertSame(date('Y-m-d', strtotime('+13 days')), $sub['current_period_end']);
        $this->assertSame(0, $this->db->table('invoices')->where('restaurant_id', $restaurant['id'])->countAllResults());
    }

    public function testNewRestaurantWithPaidPlanIssuesFirstInvoice(): void
    {
        $this->asAdmin()->post('admin/restaurants', [
            'currency_code' => 'TRY', 'language_ids' => [(string) $this->fx->lang['tr']],
            'default_language_id' => (string) $this->fx->lang['tr'], 'translations' => ['tr' => ['name' => 'Ücretli Lokanta']],
            'plan_id' => (string) $this->fx->plan['standard'], 'billing_cycle' => 'yearly',
        ]);

        $restaurant = $this->db->table('restaurants')->where('slug', 'ucretli-lokanta')->get()->getRowArray();
        $invoice    = $this->db->table('invoices')->where('restaurant_id', $restaurant['id'])->get()->getRowArray();

        $this->assertSame('5000.00', $invoice['subtotal']);
        $this->assertSame('6000.00', $invoice['total']);
    }
}
