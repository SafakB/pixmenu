<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\RestaurantUserBranchModel;
use Tests\Support\DbTestCase;

/**
 * Şube özetleri: kategori/ürün sayıları ve yetkili manager'lar
 * (panel gösterge paneli kartları + admin şube listesi).
 *
 * @internal
 */
final class BranchSummaryTest extends DbTestCase
{
    public function testInitialsHelper(): void
    {
        $this->assertSame('ŞB', pix_initials('Şafak Bahçe'));
        $this->assertSame('AK', pix_initials('  ali   veli  kaya '));
        $this->assertSame('İI', pix_initials('irem ılgın'));
        $this->assertSame('M', pix_initials('mehmet'));
        $this->assertSame('?', pix_initials('   '));
    }

    public function testCountsByBranchAreScopedAndSkipSoftDeleted(): void
    {
        $a   = $this->fx->restaurantA;
        $ids = [$this->fx->branchA1, $this->fx->branchA2, $this->fx->branchB1];

        $this->assertSame(
            [$this->fx->branchA1 => 2, $this->fx->branchA2 => 1],
            $this->sorted((new CategoryModel())->forRestaurant($a)->countsByBranch($ids)),
        );

        $this->db->table('products')->where('id', $this->fx->productA1b)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $this->assertSame(
            [$this->fx->branchA1 => 1, $this->fx->branchA2 => 1],
            $this->sorted((new ProductModel())->forRestaurant($a)->countsByBranch($ids)),
        );

        $this->assertSame([], (new ProductModel())->forRestaurant($a)->countsByBranch([]));
    }

    public function testActiveManagersByBranchExcludesInactiveDeletedOwnersAndOtherTenants(): void
    {
        $a = $this->fx->restaurantA;

        $inactive = $this->fx->user($a, 'Pasif Yönetici', 'pasif@test.local', 'manager', 0);
        $this->fx->assign($a, (int) $inactive['id'], $this->fx->branchA1);

        $deleted = $this->fx->user($a, 'Silinmiş Yönetici', 'silinmis@test.local', 'manager');
        $this->fx->assign($a, (int) $deleted['id'], $this->fx->branchA1);
        $this->db->table('restaurant_users')->where('id', $deleted['id'])->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $map = (new RestaurantUserBranchModel())->forRestaurant($a)
            ->activeManagersByBranch([$this->fx->branchA1, $this->fx->branchA2, $this->fx->branchB1]);

        $this->assertSame(
            [$this->fx->branchA1 => [['id' => (int) $this->fx->managerA1['id'], 'name' => 'Manager A1']]],
            $map,
        );
    }

    public function testPanelDashboardShowsCountsAndManagerInitials(): void
    {
        $response = $this->asPanelUser($this->fx->ownerA)->get('panel');

        $response->assertOK();
        $response->assertSee('Atanmış yönetici yok');      // şube A2
        $html = (string) $response->getBody();
        $this->assertStringContainsString('title="Manager A1"', $html);
        $this->assertStringContainsString('>MA</span>', $html);

        // Diğer tenant'ın manager'ı görünmez.
        $this->asPanelUser($this->fx->ownerB)->get('panel')->assertDontSee('Manager A1');
    }

    public function testAdminBranchListShowsCountsManagersAndMenuLink(): void
    {
        $response = $this->asAdmin()->get("admin/restaurants/{$this->fx->restaurantA}/branches");

        $response->assertOK();
        $response->assertSee('Menüyü Göster');
        $html = (string) $response->getBody();
        // Menü kodu artık link metni olarak basılmaz.
        $this->assertStringNotContainsString('<span class="font-monospace">aaaaaaaaa1</span>', $html);
        $this->assertStringContainsString('title="Manager A1"', $html);
        $this->assertStringContainsString('>MA</span>', $html);
    }

    public function testPanelBranchListShowsCountsManagersAndMenuLink(): void
    {
        $owner = $this->asPanelUser($this->fx->ownerA)->get('panel/branches');
        $owner->assertOK();
        $owner->assertSee('Menüyü Göster');
        $owner->assertSee('İşlemler');

        $html = (string) $owner->getBody();
        $this->assertStringNotContainsString('<span class="font-monospace">aaaaaaaaa1</span>', $html);
        $this->assertStringContainsString('title="Manager A1"', $html);
        $this->assertStringContainsString('>MA</span>', $html);
        $this->assertStringContainsString('href="' . site_url("panel/branches/{$this->fx->branchA1}/products") . '"', $html);

        // Manager'da işlem butonu olmadığı için sütun da yok; kategori/ürün linkleri duruyor.
        $manager = $this->asPanelUser($this->fx->managerA1)->get('panel/branches');
        $manager->assertOK();
        $manager->assertDontSee('İşlemler');
        $this->assertStringContainsString(
            'href="' . site_url("panel/branches/{$this->fx->branchA1}/categories") . '"',
            (string) $manager->getBody(),
        );
    }

    /**
     * @param array<int, int> $map
     *
     * @return array<int, int>
     */
    private function sorted(array $map): array
    {
        ksort($map);

        return $map;
    }
}
