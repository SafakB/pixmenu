<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DbTestCase;
use Tests\Support\Fixture;

/**
 * İşlem kayıtları (App\Services\ActivityLogger + ActivityLogFilter):
 * başarılı POST'lar ve giriş/çıkış kaydedilir; GET, red ve hatalar kaydedilmez.
 *
 * @internal
 */
final class ActivityLogTest extends DbTestCase
{
    public function testSuccessfulAdminWriteIsLogged(): void
    {
        $this->asAdmin()->post('admin/languages', [
            'code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => '1',
        ]);

        $logs = $this->logs();
        $this->assertCount(1, $logs);
        $this->assertSame('admin', $logs[0]['area']);
        $this->assertSame($this->fx->adminId, (int) $logs[0]['actor_id']);
        $this->assertSame('admin@test.local', $logs[0]['actor_label']);
        $this->assertSame('language.create', $logs[0]['action']);
        $this->assertSame('admin/languages', $logs[0]['path']);
        $this->assertNull($logs[0]['restaurant_id']);
        $this->assertNull($logs[0]['detail']);
        $this->assertNotSame('', $logs[0]['ip']);
    }

    public function testAdminRestaurantScopedWriteCarriesRestaurantFromUrl(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$this->fx->managerA1['id']}/delete", []);

        $logs = $this->logs();
        $this->assertCount(1, $logs);
        $this->assertSame('restaurantuser.delete', $logs[0]['action']);
        $this->assertSame($a, (int) $logs[0]['restaurant_id']);
        $this->assertSame(['email' => 'manager-a1@test.local', 'role' => 'manager'], json_decode((string) $logs[0]['detail'], true));
    }

    public function testFailuresAndReadsAreNotLogged(): void
    {
        // GET
        $this->asAdmin()->get('admin/languages')->assertOK();

        // Doğrulama hatası
        $this->asAdmin()->post('admin/languages', ['code' => 'TR', 'name' => 'X', 'native_name' => 'X', 'direction' => 'ltr']);
        $this->assertArrayHasKey('code', $_SESSION['errors'] ?? []);

        // İş kuralı reddi: varsayılan dil pasifleştirilemez
        $this->asAdmin()->post('admin/languages/' . $this->fx->lang['tr'] . '/toggle', []);

        // 403: yönetici rolü paket değiştiremez
        $manager = $this->fx->createAdmin('manager');
        $this->asAdmin($manager)->post('admin/plans/' . $this->fx->plan['pro'] . '/toggle', [])->assertStatus(403);

        // Hatalı parola
        $this->post('admin/login', ['email' => 'admin@test.local', 'password' => 'yanlis-sifre-1']);

        $this->assertSame([], $this->logs());
    }

    public function testPanelWriteIsLoggedWithTenant(): void
    {
        $this->asPanelUser($this->fx->managerA1)->post("panel/branches/{$this->fx->branchA1}/products", [
            'category_id'  => (string) $this->fx->categoryA1,
            'price'        => '50',
            'is_active'    => '1',
            'translations' => ['tr' => ['name' => 'Yayla']],
        ]);

        $this->seeInDatabase('product_translations', ['name' => 'Yayla']);

        $logs = $this->logs();
        $this->assertCount(1, $logs);
        $this->assertSame('panel', $logs[0]['area']);
        $this->assertSame((int) $this->fx->managerA1['id'], (int) $logs[0]['actor_id']);
        $this->assertSame('manager-a1@test.local', $logs[0]['actor_label']);
        $this->assertSame($this->fx->restaurantA, (int) $logs[0]['restaurant_id']);
        $this->assertSame('product.create', $logs[0]['action']);
    }

    public function testJsonReorderIsLogged(): void
    {
        $this->asPanelUser($this->fx->ownerA)->postJson(
            "panel/branches/{$this->fx->branchA1}/categories/reorder",
            ['ids' => [$this->fx->categoryA1b, $this->fx->categoryA1]],
        )->assertOK();

        $logs = $this->logs();
        $this->assertCount(1, $logs);
        $this->assertSame('category.reorder', $logs[0]['action']);

        // Reddedilen sıralama kaydedilmez.
        $this->asPanelUser($this->fx->ownerA)->postJson(
            "panel/branches/{$this->fx->branchA1}/categories/reorder",
            ['ids' => [$this->fx->categoryA1, $this->fx->categoryB1]],
        )->assertStatus(422);

        $this->assertCount(1, $this->logs());
    }

    public function testLoginAndLogoutAreLogged(): void
    {
        $this->post('panel/login', ['email' => 'owner-a@test.local', 'password' => Fixture::PASSWORD]);
        $this->asPanelUser($this->fx->ownerA)->post('panel/logout', []);
        $this->post('admin/login', ['email' => 'admin@test.local', 'password' => Fixture::PASSWORD]);
        $this->asAdmin()->post('admin/logout', []);

        $logs = $this->logs();
        $this->assertSame(
            [['panel', 'auth.login'], ['panel', 'auth.logout'], ['admin', 'auth.login'], ['admin', 'auth.logout']],
            array_map(static fn (array $l): array => [$l['area'], $l['action']], $logs),
        );
        $this->assertSame('owner-a@test.local', $logs[0]['actor_label']);
        $this->assertSame($this->fx->restaurantA, (int) $logs[1]['restaurant_id']);
        $this->assertSame('admin@test.local', $logs[3]['actor_label']);
        $this->assertNull($logs[2]['restaurant_id']);
    }

    public function testInvoicePaymentCarriesDetailAndRestaurant(): void
    {
        $invoice = $this->invoice();

        $this->asAdmin()->post("admin/invoices/{$invoice}/pay", ['payment_method' => 'bank_transfer', 'paid_at' => '2026-11-05']);
        $this->seeInDatabase('invoices', ['id' => $invoice, 'status' => 'paid']);

        $logs = $this->logs();
        $this->assertCount(1, $logs);
        $this->assertSame('invoice.pay', $logs[0]['action']);
        $this->assertSame($this->fx->restaurantA, (int) $logs[0]['restaurant_id']);
        $this->assertSame(
            ['number' => 'PM-2026-000001', 'total' => '1200.00 TRY', 'method' => 'bank_transfer', 'paid_at' => '2026-11-05'],
            json_decode((string) $logs[0]['detail'], true),
        );
    }

    public function testPasswordIsNeverWrittenToDetail(): void
    {
        $this->asAdmin()->post("admin/restaurants/{$this->fx->restaurantA}/users", [
            'name' => 'Yeni', 'email' => 'yeni@test.local', 'password' => 'CokGizli12345', 'role' => 'manager', 'is_active' => '1',
            'branch_ids' => [(string) $this->fx->branchA2],
        ]);
        $this->seeInDatabase('restaurant_users', ['email' => 'yeni@test.local']);

        $logs = $this->logs();
        $this->assertCount(1, $logs);
        $this->assertSame('restaurantuser.create', $logs[0]['action']);
        $this->assertStringContainsString('yeni@test.local', (string) $logs[0]['detail']);
        $this->assertStringNotContainsString('CokGizli12345', (string) $logs[0]['detail'] . $logs[0]['path']);
    }

    public function testLogScreenIsSuperAdminOnlyAndFilters(): void
    {
        $invoice = $this->invoice();
        $this->asAdmin()->post("admin/invoices/{$invoice}/pay", ['payment_method' => 'cash', 'paid_at' => '2026-11-05']);
        $this->asPanelUser($this->fx->ownerB)->post("panel/branches/{$this->fx->branchB1}/categories/{$this->fx->categoryB1}/toggle", []);

        $all = $this->asAdmin()->get('admin/logs');
        $all->assertOK();
        $all->assertSee('PM-2026-000001');
        $all->assertSee('owner-b@test.local');
        $all->assertSee('Fatura: Ödendi işaretleme');

        $panelOnly = $this->asAdmin()->get('admin/logs?area=panel');
        $panelOnly->assertSee('owner-b@test.local');
        $panelOnly->assertDontSee('PM-2026-000001');

        $byRestaurant = $this->asAdmin()->get('admin/logs?restaurant=' . $this->fx->restaurantA . '&action=invoice');
        $byRestaurant->assertSee('PM-2026-000001');
        $byRestaurant->assertDontSee('owner-b@test.local');

        $this->asAdmin()->get('admin/logs?from=2020-01-01&to=2020-01-02&actor=yok&page=3')->assertOK();

        $manager = $this->fx->createAdmin('manager');
        $this->asAdmin($manager)->get('admin/logs')->assertStatus(403);
        $this->asAdmin($manager)->get('admin')->assertDontSee('admin/logs');
    }

    public function testPruneDeletesOnlyExpiredRows(): void
    {
        $base = ['area' => 'admin', 'actor_id' => 1, 'actor_label' => 'a@test.local', 'action' => 'x.y', 'path' => 'admin/x', 'ip' => '127.0.0.1'];
        $old  = $this->fx->insert('activity_logs', $base + ['created_at' => date('Y-m-d H:i:s', strtotime('-181 days'))]);
        $new  = $this->fx->insert('activity_logs', $base + ['created_at' => date('Y-m-d H:i:s', strtotime('-179 days'))]);

        command('activity:prune');

        $this->assertNull($this->row('activity_logs', $old));
        $this->assertNotNull($this->row('activity_logs', $new));

        command('activity:prune --days 30');

        $this->assertNull($this->row('activity_logs', $new));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function logs(): array
    {
        return $this->db->table('activity_logs')->orderBy('id', 'ASC')->get()->getResultArray();
    }

    private function invoice(): int
    {
        return $this->fx->insert('invoices', [
            'restaurant_id' => $this->fx->restaurantA, 'subscription_id' => $this->fx->subscriptionA, 'plan_id' => $this->fx->plan['pro'],
            'number' => 'PM-2026-000001', 'description' => 'Pro', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'subtotal' => '1000.00', 'total' => '1200.00', 'status' => 'pending', 'issued_at' => '2026-11-01', 'due_date' => '2026-11-08',
        ]);
    }
}
