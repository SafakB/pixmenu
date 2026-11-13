<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use CodeIgniter\Test\TestResponse;
use Tests\Support\DbTestCase;

/**
 * Admin rolleri (Config\AdminRoles): rota erişimi, 403 yanıtı, menü/düğme gizleme.
 *
 * @internal
 */
final class AdminRoleTest extends DbTestCase
{
    private int $manager;
    private int $accountant;
    private int $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager    = $this->fx->createAdmin('manager');
        $this->accountant = $this->fx->createAdmin('accountant');
        $this->invoice    = $this->fx->insert('invoices', [
            'restaurant_id' => $this->fx->restaurantA, 'subscription_id' => $this->fx->subscriptionA, 'plan_id' => $this->fx->plan['pro'],
            'number' => 'PM-2026-000001', 'description' => 'Pro', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'subtotal' => '1000.00', 'total' => '1200.00', 'status' => 'pending', 'issued_at' => '2026-11-01', 'due_date' => '2026-11-08',
        ]);
    }

    // ---------------------------------------------------------------
    // Erişim matrisi
    // ---------------------------------------------------------------

    public function testManagerAccess(): void
    {
        $a = $this->fx->restaurantA;

        foreach ([
            'admin', 'admin/restaurants', "admin/restaurants/{$a}", 'admin/restaurants/new', "admin/restaurants/{$a}/edit",
            "admin/restaurants/{$a}/branches", "admin/restaurants/{$a}/users", "admin/restaurants/{$a}/languages", "admin/restaurants/{$a}/domain",
            'admin/languages', 'admin/allergens', 'admin/templates',
            'admin/plans', 'admin/invoices', "admin/invoices/{$this->invoice}", "admin/restaurants/{$a}/subscription",
        ] as $path) {
            $this->asAdmin($this->manager)->get($path)->assertOK();
        }

        foreach (['admin/plans/new', "admin/plans/{$this->fx->plan['pro']}/edit"] as $path) {
            $this->assertForbidden($this->asAdmin($this->manager)->get($path));
        }

        foreach ([
            "admin/restaurants/{$a}/delete",
            "admin/invoices/{$this->invoice}/pay",
            "admin/invoices/{$this->invoice}/discount",
            "admin/invoices/{$this->invoice}/void",
            "admin/restaurants/{$a}/subscription/change",
            "admin/restaurants/{$a}/subscription/discount",
            "admin/restaurants/{$a}/subscription/suspend",
            "admin/plans/{$this->fx->plan['pro']}/toggle",
        ] as $path) {
            $this->assertForbidden($this->asAdmin($this->manager)->post($path, []));
        }

        $this->seeInDatabase('restaurants', ['id' => $a, 'deleted_at' => null, 'suspended_at' => null]);
        $this->seeInDatabase('invoices', ['id' => $this->invoice, 'status' => 'pending']);
    }

    public function testAccountantAccess(): void
    {
        $a = $this->fx->restaurantA;

        foreach ([
            'admin', 'admin/restaurants', "admin/restaurants/{$a}",
            'admin/plans', 'admin/invoices', "admin/invoices/{$this->invoice}", "admin/restaurants/{$a}/subscription",
        ] as $path) {
            $this->asAdmin($this->accountant)->get($path)->assertOK();
        }

        foreach ([
            'admin/restaurants/new', "admin/restaurants/{$a}/edit", "admin/restaurants/{$a}/branches", "admin/restaurants/{$a}/users",
            "admin/restaurants/{$a}/languages", "admin/restaurants/{$a}/domain",
            'admin/languages', 'admin/allergens', 'admin/templates', 'admin/plans/new',
        ] as $path) {
            $this->assertForbidden($this->asAdmin($this->accountant)->get($path));
        }

        foreach ([
            "admin/restaurants/{$a}/toggle",
            "admin/restaurants/{$a}/delete",
            "admin/invoices/{$this->invoice}/void",
            "admin/restaurants/{$a}/subscription/suspend",
            "admin/restaurants/{$a}/subscription/cancel",
            "admin/restaurants/{$a}/branches/{$this->fx->branchA1}/delete",
        ] as $path) {
            $this->assertForbidden($this->asAdmin($this->accountant)->post($path, []));
        }

        $this->seeInDatabase('restaurants', ['id' => $a, 'is_active' => 1, 'deleted_at' => null, 'suspended_at' => null]);
        $this->seeInDatabase('invoices', ['id' => $this->invoice, 'status' => 'pending']);
        $this->seeInDatabase('branches', ['id' => $this->fx->branchA1, 'deleted_at' => null]);
    }

    public function testAccountantCanPayInvoiceAndManageSubscriptionSettings(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin($this->accountant)->post("admin/invoices/{$this->invoice}/pay", [
            'payment_method' => 'cash', 'paid_at' => date('Y-m-d'),
        ])->assertSessionHas('success');
        $this->seeInDatabase('invoices', ['id' => $this->invoice, 'status' => 'paid']);

        $this->asAdmin($this->accountant)->post("admin/restaurants/{$a}/subscription/discount", [
            'discount_percent' => '15', 'discount_until' => '',
        ])->assertSessionHas('success');
        $this->seeInDatabase('restaurants', ['id' => $a, 'discount_percent' => '15.00']);
    }

    public function testSuperAdminCanDoCriticalActions(): void
    {
        $this->asAdmin()->post("admin/invoices/{$this->invoice}/void")->assertSessionHas('success');
        $this->seeInDatabase('invoices', ['id' => $this->invoice, 'status' => 'void']);

        $this->asAdmin()->get('admin/plans/new')->assertOK();
    }

    public function testForbiddenJsonResponse(): void
    {
        $response = $this->asAdmin($this->accountant)
            ->withBodyFormat('json')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('admin/templates/reorder', ['ids' => ['eski', 'modern', 'standart']]);

        $response->assertStatus(403);
        $response->assertJSONFragment(['status' => 'error', 'message' => lang('App.auth.forbidden')]);
        $this->seeInDatabase('menu_templates', ['code' => 'standart', 'sort_order' => 0]);
    }

    public function testForbiddenPageUsesAdminLayout(): void
    {
        $response = $this->asAdmin($this->accountant)->get('admin/languages');

        $response->assertStatus(403);
        $response->assertSee('Bu işlem için yetkiniz yok');
        $response->assertSee('Muhasebe');
    }

    // ---------------------------------------------------------------
    // Menü ve düğmeler
    // ---------------------------------------------------------------

    public function testMenuShowsOnlyPermittedItems(): void
    {
        $superResponse   = $this->asAdmin()->get('admin');
        $managerResponse = $this->asAdmin($this->manager)->get('admin');
        $super           = $superResponse->getBody();
        $manager         = $managerResponse->getBody();
        $accountant      = $this->asAdmin($this->accountant)->get('admin')->getBody();

        foreach (['admin/restaurants', 'admin/invoices', 'admin/plans', 'admin/languages', 'admin/templates'] as $path) {
            $this->assertStringContainsString($this->href($path), $super);
            $this->assertStringContainsString($this->href($path), $manager);
        }

        foreach (['admin/restaurants', 'admin/invoices', 'admin/plans'] as $path) {
            $this->assertStringContainsString($this->href($path), $accountant);
        }

        foreach (['admin/languages', 'admin/allergens', 'admin/templates'] as $path) {
            $this->assertStringNotContainsString($this->href($path), $accountant);
        }

        $this->assertStringNotContainsString('>Tanımlar<', $accountant);
        $superResponse->assertSee('Süper Admin');
        $managerResponse->assertSee('Yönetici');
    }

    public function testButtonsHiddenByPermission(): void
    {
        $a      = $this->fx->restaurantA;
        $delete = 'action="' . site_url("admin/restaurants/{$a}/delete") . '"';
        $toggle = 'action="' . site_url("admin/restaurants/{$a}/toggle") . '"';

        $super = $this->asAdmin()->get("admin/restaurants/{$a}")->getBody();
        $this->assertStringContainsString($delete, $super);
        $this->assertStringContainsString($toggle, $super);

        $manager = $this->asAdmin($this->manager)->get("admin/restaurants/{$a}")->getBody();
        $this->assertStringNotContainsString($delete, $manager);
        $this->assertStringContainsString($toggle, $manager);

        $accountant = $this->asAdmin($this->accountant)->get("admin/restaurants/{$a}")->getBody();
        $this->assertStringNotContainsString($delete, $accountant);
        $this->assertStringNotContainsString($toggle, $accountant);
        $this->assertStringNotContainsString($this->href("admin/restaurants/{$a}/branches/new"), $accountant);

        $pay  = 'action="' . site_url("admin/invoices/{$this->invoice}/pay") . '"';
        $void = 'action="' . site_url("admin/invoices/{$this->invoice}/void") . '"';

        $body = $this->asAdmin($this->accountant)->get("admin/invoices/{$this->invoice}")->getBody();
        $this->assertStringContainsString($pay, $body);
        $this->assertStringNotContainsString($void, $body);

        $body = $this->asAdmin($this->manager)->get("admin/invoices/{$this->invoice}")->getBody();
        $this->assertStringNotContainsString($pay, $body);
        $this->assertStringNotContainsString($void, $body);

        $sub     = "admin/restaurants/{$a}/subscription";
        $body    = $this->asAdmin($this->accountant)->get($sub)->getBody();
        $this->assertStringContainsString('action="' . site_url($sub . '/change') . '"', $body);
        $this->assertStringNotContainsString('action="' . site_url($sub . '/suspend') . '"', $body);

        $response = $this->asAdmin($this->manager)->get($sub);
        $this->assertStringNotContainsString('action="' . site_url($sub . '/change') . '"', $response->getBody());
        $response->assertSee('Abonelik ayarları');

        $body = $this->asAdmin($this->manager)->get('admin/plans')->getBody();
        $this->assertStringNotContainsString($this->href('admin/plans/new'), $body);
    }

    // ---------------------------------------------------------------
    // Rol oturumda her istekte doğrulanır
    // ---------------------------------------------------------------

    public function testRoleChangeTakesEffectImmediately(): void
    {
        $this->assertForbidden($this->asAdmin($this->accountant)->get('admin/restaurants/new'));

        $this->db->table('admins')->where('id', $this->accountant)->update(['role' => 'manager']);

        $this->asAdmin($this->accountant)->get('admin/restaurants/new')->assertOK();
    }

    public function testUnknownRoleIsLoggedOut(): void
    {
        $this->db->table('admins')->where('id', $this->manager)->update(['role' => 'ghost']);

        $this->assertRedirectsTo($this->asAdmin($this->manager)->get('admin'), 'admin/login');
    }

    public function testUnknownRoleCannotLogin(): void
    {
        $this->db->table('admins')->where('id', $this->manager)->update(['role' => 'ghost']);

        $this->post('admin/login', ['email' => 'manager@test.local', 'password' => \Tests\Support\Fixture::PASSWORD]);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
    }

    private function assertForbidden(TestResponse $response): void
    {
        $response->assertStatus(403);
    }

    private function href(string $path): string
    {
        return 'href="' . site_url($path) . '"';
    }
}
