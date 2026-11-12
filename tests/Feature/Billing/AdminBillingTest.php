<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Tests\Support\DbTestCase;

/**
 * Admin: paketler, abonelik ekranı, faturalar, alan adı (docs/ABONELIK_PLANI.md §8).
 *
 * @internal
 */
final class AdminBillingTest extends DbTestCase
{
    public function testPagesRender(): void
    {
        $a       = $this->fx->restaurantA;
        $invoice = $this->fx->insert('invoices', [
            'restaurant_id' => $a, 'subscription_id' => $this->fx->subscriptionA, 'plan_id' => $this->fx->plan['pro'],
            'number' => 'PM-2026-000001', 'description' => 'Pro', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'subtotal' => '1000.00', 'total' => '1200.00', 'status' => 'pending', 'issued_at' => '2026-11-01', 'due_date' => '2026-11-08',
        ]);

        foreach ([
            'admin', 'admin/plans', 'admin/plans/new', "admin/plans/{$this->fx->plan['pro']}/edit",
            'admin/invoices', 'admin/invoices?overdue=1', 'admin/invoices?status=paid&restaurant=' . $a, "admin/invoices/{$invoice}",
            "admin/restaurants/{$a}/subscription", "admin/restaurants/{$a}/domain", 'admin/restaurants', 'admin/restaurants/new', "admin/restaurants/{$a}",
        ] as $path) {
            $this->asAdmin()->get($path)->assertOK();
        }

        $this->asAdmin()->get('admin/invoices/99999')->assertStatus(404);
        $this->asAdmin()->get('admin/restaurants/99999/subscription')->assertStatus(404);
    }

    public function testPanelUserCannotReachAdminBilling(): void
    {
        $this->asPanelUser($this->fx->ownerA)->get('admin/invoices')->assertRedirect();
    }

    public function testCreateAndUpdatePlan(): void
    {
        $this->asAdmin()->post('admin/plans', [
            'code' => 'gold', 'price_monthly' => '1.499,90', 'price_yearly' => '14999', 'currency_code' => 'try',
            'max_branches' => '3', 'allow_custom_domain' => '1', 'is_active' => '1',
            'translations' => ['tr' => ['name' => 'Altın', 'description' => 'Üç şube']],
        ])->assertRedirect();

        $plan = $this->db->table('plans')->where('code', 'gold')->get()->getRowArray();
        $this->assertSame('1499.90', $plan['price_monthly']);
        $this->assertSame('14999.00', $plan['price_yearly']);
        $this->assertSame('TRY', $plan['currency_code']);
        $this->assertSame('3', (string) $plan['max_branches']);
        $this->assertNull($plan['trial_days']);
        $this->seeInDatabase('plan_translations', ['plan_id' => $plan['id'], 'name' => 'Altın']);

        $this->asAdmin()->post("admin/plans/{$plan['id']}", [
            'code' => 'gold', 'price_monthly' => '10', 'price_yearly' => '100', 'currency_code' => 'TRY', 'max_branches' => '',
            'translations' => ['tr' => ['name' => 'Altın+']],
        ])->assertRedirect();

        $plan = $this->row('plans', (int) $plan['id']);
        $this->assertNull($plan['max_branches'], 'Boş = sınırsız');
        $this->assertSame('0', (string) $plan['is_active']);
    }

    public function testPlanValidation(): void
    {
        $this->asAdmin()->post('admin/plans', [
            'code' => 'Pro!', 'price_monthly' => 'abc', 'price_yearly' => '-5', 'currency_code' => 'TRY',
            'translations' => ['tr' => ['name' => '']],
        ])->assertSessionHas('errors');

        $this->asAdmin()->post('admin/plans', [
            'code' => 'pro', 'price_monthly' => '1', 'price_yearly' => '1', 'currency_code' => 'TRY',
            'translations' => ['tr' => ['name' => 'Kopya']],
        ])->assertSessionHas('errors');

        $this->assertSame(4, $this->db->table('plans')->countAllResults());
    }

    public function testPlanInUseCannotBeDeletedButUnusedCan(): void
    {
        $this->asAdmin()->post("admin/plans/{$this->fx->plan['pro']}/delete")->assertSessionHas('error');
        $this->seeInDatabase('plans', ['id' => $this->fx->plan['pro']]);

        $this->asAdmin()->post("admin/plans/{$this->fx->plan['enterprise']}/delete")->assertSessionHas('success');
        $this->dontSeeInDatabase('plans', ['id' => $this->fx->plan['enterprise']]);
    }

    public function testInactivePlanCannotBeAssigned(): void
    {
        $this->asAdmin()->post("admin/plans/{$this->fx->plan['enterprise']}/toggle");

        $this->asAdmin()->post("admin/restaurants/{$this->fx->restaurantA}/subscription/change", [
            'plan_id' => (string) $this->fx->plan['enterprise'], 'billing_cycle' => 'monthly',
        ])->assertSessionHas('errors');
    }

    public function testCancelAtPeriodEndAndGraceDays(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin()->post("admin/restaurants/{$a}/subscription/cancel", ['cancel' => '1']);
        $this->assertSame('1', (string) $this->row('subscriptions', $this->fx->subscriptionA)['cancel_at_period_end']);
        $this->asAdmin()->post("admin/restaurants/{$a}/subscription/cancel", ['cancel' => '0']);
        $this->assertSame('0', (string) $this->row('subscriptions', $this->fx->subscriptionA)['cancel_at_period_end']);

        $this->asAdmin()->post("admin/restaurants/{$a}/subscription/grace", ['grace_days' => '15']);
        $this->assertSame('15', (string) $this->row('subscriptions', $this->fx->subscriptionA)['grace_days']);
        $this->asAdmin()->post("admin/restaurants/{$a}/subscription/grace", ['grace_days' => '']);
        $this->assertNull($this->row('subscriptions', $this->fx->subscriptionA)['grace_days']);
        $this->asAdmin()->post("admin/restaurants/{$a}/subscription/grace", ['grace_days' => 'abc'])->assertSessionHas('errors');
    }

    public function testInvoiceFilters(): void
    {
        $base = [
            'subscription_id' => $this->fx->subscriptionA, 'plan_id' => $this->fx->plan['pro'], 'description' => 'x',
            'period_end' => '2026-11-30', 'subtotal' => '1.00', 'total' => '1.20', 'issued_at' => '2026-11-01',
        ];
        $this->fx->insert('invoices', $base + ['restaurant_id' => $this->fx->restaurantA, 'number' => 'PM-GECIKMIS', 'period_start' => '2026-01-01', 'status' => 'pending', 'due_date' => '2026-01-08']);
        $this->fx->insert('invoices', $base + ['restaurant_id' => $this->fx->restaurantA, 'number' => 'PM-ODENDI', 'period_start' => '2026-02-01', 'status' => 'paid', 'due_date' => '2026-02-08']);
        $this->fx->insert('invoices', ['subscription_id' => $this->fx->subscriptionB] + $base + ['restaurant_id' => $this->fx->restaurantB, 'number' => 'PM-B', 'period_start' => '2026-01-01', 'status' => 'pending', 'due_date' => '2099-01-08']);

        $overdue = (string) $this->asAdmin()->get('admin/invoices?overdue=1')->getBody();
        $this->assertStringContainsString('PM-GECIKMIS', $overdue);
        $this->assertStringNotContainsString('PM-ODENDI', $overdue);
        $this->assertStringNotContainsString('PM-B<', $overdue);

        $paid = (string) $this->asAdmin()->get('admin/invoices?status=paid')->getBody();
        $this->assertStringContainsString('PM-ODENDI', $paid);
        $this->assertStringNotContainsString('PM-GECIKMIS', $paid);

        $onlyB = (string) $this->asAdmin()->get('admin/invoices?restaurant=' . $this->fx->restaurantB)->getBody();
        $this->assertStringContainsString('PM-B<', $onlyB);
        $this->assertStringNotContainsString('PM-GECIKMIS', $onlyB);
    }

    // ------------------------------------------------------------ Alan adı

    public function testDomainSaveNormalizesAndEnforcesPlanAndUniqueness(): void
    {
        $a = $this->fx->restaurantA;
        $b = $this->fx->restaurantB;

        $this->asAdmin()->post("admin/restaurants/{$a}/domain", ['domain' => 'HTTPS://www.Menu.Lezzet.com/path?x=1', 'is_active' => '1'])->assertSessionHas('success');
        $this->seeInDatabase('restaurant_domains', ['restaurant_id' => $a, 'domain' => 'menu.lezzet.com', 'is_active' => 1]);

        // Aynı alan adı başka restoranda kullanılamaz.
        $this->asAdmin()->post("admin/restaurants/{$b}/domain", ['domain' => 'menu.lezzet.com'])->assertSessionHas('errors');

        // Geçersiz ve ayrılmış adlar
        $this->asAdmin()->post("admin/restaurants/{$b}/domain", ['domain' => 'not a domain'])->assertSessionHas('errors');
        config('App')->allowedHostnames = ['panel.pixmenu-host.test'];
        $this->asAdmin()->post("admin/restaurants/{$b}/domain", ['domain' => 'panel.pixmenu-host.test'])->assertSessionHas('errors');   // uygulamanın kendi host'u
        config('App')->allowedHostnames = [];
        $this->asAdmin()->post("admin/restaurants/{$b}/domain", ['domain' => 'x.pixmenu.com'])->assertSessionHas('errors');

        // Paket izin vermiyorsa reddedilir.
        $this->db->table('subscriptions')->where('id', $this->fx->subscriptionB)->update(['plan_id' => $this->fx->plan['demo']]);
        $this->asAdmin()->post("admin/restaurants/{$b}/domain", ['domain' => 'menu.b.com'])->assertSessionHas('error');
        $this->dontSeeInDatabase('restaurant_domains', ['restaurant_id' => $b]);

        // Değiştirme doğrulamayı sıfırlar; kaldırma siler.
        $this->asAdmin()->post("admin/restaurants/{$a}/domain/verify", ['verified' => '1']);
        $this->assertNotNull($this->db->table('restaurant_domains')->where('restaurant_id', $a)->get()->getRow()->verified_at);
        $this->asAdmin()->post("admin/restaurants/{$a}/domain", ['domain' => 'yeni.lezzet.com', 'is_active' => '1']);
        $this->assertNull($this->db->table('restaurant_domains')->where('restaurant_id', $a)->get()->getRow()->verified_at);

        $this->asAdmin()->post("admin/restaurants/{$a}/domain/delete")->assertSessionHas('success');
        $this->dontSeeInDatabase('restaurant_domains', ['restaurant_id' => $a]);
    }
}
