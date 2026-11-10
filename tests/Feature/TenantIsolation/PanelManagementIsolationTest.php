<?php

declare(strict_types=1);

namespace Tests\Feature\TenantIsolation;

use Tests\Support\DbTestCase;
use Tests\Support\Fixture;

/**
 * Panel: restoran, şube, kullanıcı, profil uçlarında tenant izolasyonu
 * (spec §14.1 — Test 7, 9 ve şube/kullanıcı için 1, 2, 5, 10).
 *
 * @internal
 */
final class PanelManagementIsolationTest extends DbTestCase
{
    // ------------------------------------------------------------------ Test 9

    /** Test 9 */
    public function testManagerGets404OnOwnerRoutes(): void
    {
        $manager = $this->fx->managerA1;

        foreach ([
            'panel/users', 'panel/users/new', 'panel/restaurant', 'panel/branches/new',
            "panel/branches/{$this->fx->branchA1}/edit", "panel/users/{$manager['id']}/edit",
        ] as $path) {
            $this->asPanelUser($manager)->get($path)->assertStatus(404);
        }
    }

    /** Test 9 */
    public function testManagerCannotWriteThroughOwnerRoutes(): void
    {
        $manager = $this->fx->managerA1;
        $a1      = $this->fx->branchA1;

        $this->asPanelUser($manager)->post('panel/branches', [
            'default_language_id' => (string) $this->fx->lang['tr'], 'translations' => ['tr' => ['name' => 'Manager Şubesi']],
        ])->assertStatus(404);
        $this->asPanelUser($manager)->post("panel/branches/{$a1}", ['translations' => ['tr' => ['name' => 'Değişti']]])->assertStatus(404);
        $this->asPanelUser($manager)->post("panel/branches/{$a1}/toggle")->assertStatus(404);
        $this->asPanelUser($manager)->post("panel/branches/{$a1}/delete")->assertStatus(404);
        $this->asPanelUser($manager)->post('panel/restaurant', ['translations' => ['tr' => ['name' => 'Ele Geçirildi']]])->assertStatus(404);
        $this->asPanelUser($manager)->post('panel/restaurant/logo/delete')->assertStatus(404);
        $this->asPanelUser($manager)->post('panel/users', [
            'name' => 'X', 'email' => 'x@test.local', 'password' => 'GucluSifre123', 'branch_ids' => [(string) $a1],
        ])->assertStatus(404);
        $this->asPanelUser($manager)->post("panel/users/{$manager['id']}/toggle")->assertStatus(404);

        $this->assertSame(3, $this->db->table('branches')->where('deleted_at', null)->countAllResults());
        $this->assertSame(1, (int) $this->row('branches', $a1)['is_active']);
        $this->assertSame(3, $this->db->table('restaurant_users')->countAllResults());
        $this->dontSeeInDatabase('restaurant_translations', ['name' => 'Ele Geçirildi']);
        $this->dontSeeInDatabase('branch_translations', ['name' => 'Değişti']);
    }

    public function testManagerSeesOnlyAssignedBranchesInLists(): void
    {
        $list = $this->asPanelUser($this->fx->managerA1)->get('panel/branches');
        $list->assertOK();
        $list->assertSee('Şube A1');
        $list->assertDontSee('Şube A2');
        $list->assertDontSee('Şube B1');

        $dashboard = $this->asPanelUser($this->fx->managerA1)->get('panel');
        $dashboard->assertSee('Şube A1');
        $dashboard->assertDontSee('Şube A2');
    }

    public function testManagerLosesAccessWhenAssignedBranchIsSoftDeleted(): void
    {
        $this->db->table('branches')->where('id', $this->fx->branchA1)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $list = $this->asPanelUser($this->fx->managerA1)->get('panel/branches');
        $list->assertOK();
        $list->assertDontSee('Şube A1');
    }

    // ------------------------------------------------------------------ Test 7

    /** Test 7 */
    public function testOwnerCannotAssignManagerToAnotherTenantsBranch(): void
    {
        $response = $this->asPanelUser($this->fx->ownerA)->post('panel/users', [
            'name' => 'Sızma', 'email' => 'sizma@test.local', 'password' => 'GucluSifre123', 'is_active' => '1',
            'branch_ids' => [(string) $this->fx->branchA1, (string) $this->fx->branchB1],
        ]);

        $response->assertRedirect();
        $this->assertArrayHasKey('branch_ids', $_SESSION['errors'] ?? []);
        $this->dontSeeInDatabase('restaurant_users', ['email' => 'sizma@test.local']);
        $this->assertSame(0, $this->db->table('restaurant_user_branches')->where('branch_id', $this->fx->branchB1)->countAllResults());
    }

    /** Test 7 (güncelleme yolu) */
    public function testOwnerCannotReassignManagerToAnotherTenantsBranch(): void
    {
        $m = (int) $this->fx->managerA1['id'];

        $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$m}", [
            'name' => 'Manager A1', 'email' => 'manager-a1@test.local', 'password' => '', 'is_active' => '1',
            'branch_ids' => [(string) $this->fx->branchB1],
        ]);

        $this->assertArrayHasKey('branch_ids', $_SESSION['errors'] ?? []);
        $assigned = array_map('intval', array_column(
            $this->db->table('restaurant_user_branches')->where('user_id', $m)->get()->getResultArray(),
            'branch_id',
        ));
        $this->assertSame([$this->fx->branchA1], $assigned);
    }

    // ------------------------------------------------- Kullanıcılar (owner)

    public function testOwnerCreatesManagerForOwnTenantIgnoringRoleAndRestaurantIdInBody(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post('panel/users', [
            'name' => 'Yeni Manager', 'email' => 'yeni-m@test.local', 'password' => 'GucluSifre123', 'is_active' => '1',
            'branch_ids'    => [(string) $this->fx->branchA2],
            'role'          => 'owner',                               // yok sayılmalı
            'restaurant_id' => (string) $this->fx->restaurantB,       // yok sayılmalı (Test 5)
        ]);

        $user = $this->db->table('restaurant_users')->where('email', 'yeni-m@test.local')->get()->getRowArray();
        $this->assertSame('manager', $user['role']);
        $this->assertSame($this->fx->restaurantA, (int) $user['restaurant_id']);
        $this->seeInDatabase('restaurant_user_branches', [
            'user_id' => $user['id'], 'branch_id' => $this->fx->branchA2, 'restaurant_id' => $this->fx->restaurantA,
        ]);
    }

    public function testOwnerCannotSeeOrTouchAnotherTenantsUsers(): void
    {
        $b = (int) $this->fx->ownerB['id'];

        $list = $this->asPanelUser($this->fx->ownerA)->get('panel/users');
        $list->assertOK();
        $list->assertDontSee('owner-b@test.local');
        $list->assertSee('manager-a1@test.local');

        $this->asPanelUser($this->fx->ownerA)->get("panel/users/{$b}/edit")->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$b}", ['name' => 'Hacked', 'email' => 'h@test.local'])->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$b}/toggle")->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$b}/delete")->assertStatus(404);

        $row = $this->row('restaurant_users', $b);
        $this->assertSame(['Owner B', 'owner-b@test.local', '1', null], [$row['name'], $row['email'], (string) $row['is_active'], $row['deleted_at']]);
    }

    public function testOwnerCannotEditDeleteOrDeactivateOwnersIncludingSelf(): void
    {
        $self  = (int) $this->fx->ownerA['id'];
        $other = (int) $this->fx->user($this->fx->restaurantA, 'Owner A2', 'owner-a2@test.local', 'owner')['id'];

        foreach ([$self, $other] as $id) {
            $this->asPanelUser($this->fx->ownerA)->get("panel/users/{$id}/edit")->assertStatus(404);
            $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$id}/toggle")->assertStatus(404);
            $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$id}/delete")->assertStatus(404);

            $row = $this->row('restaurant_users', $id);
            $this->assertSame(1, (int) $row['is_active']);
            $this->assertNull($row['deleted_at']);
        }
    }

    public function testOwnerManagesOwnManagers(): void
    {
        $m = (int) $this->fx->managerA1['id'];

        $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$m}", [
            'name' => 'Yeni Ad', 'email' => 'manager-a1@test.local', 'password' => '', 'is_active' => '1',
            'branch_ids' => [(string) $this->fx->branchA1, (string) $this->fx->branchA2],
        ]);
        $this->assertSame('Yeni Ad', $this->row('restaurant_users', $m)['name']);
        $this->assertSame(2, $this->db->table('restaurant_user_branches')->where('user_id', $m)->countAllResults());

        $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$m}/toggle");
        $this->assertSame(0, (int) $this->row('restaurant_users', $m)['is_active']);

        $this->asPanelUser($this->fx->ownerA)->post("panel/users/{$m}/delete");
        $this->assertNotNull($this->row('restaurant_users', $m)['deleted_at']);
    }

    // ----------------------------------------------------------- Şubeler (owner)

    /** Test 1 (şube) */
    public function testOwnerCannotOpenAnotherTenantsBranchEditPage(): void
    {
        $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$this->fx->branchB1}/edit")->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->get('panel/branches/99999/edit')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$this->fx->branchA1}/edit")->assertOK();
    }

    /** Test 2 (şube) */
    public function testOwnerCannotUpdateToggleOrDeleteAnotherTenantsBranch(): void
    {
        $b1     = $this->fx->branchB1;
        $before = $this->row('branches', $b1);

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$b1}", [
            'slug' => 'hacked', 'default_language_id' => (string) $this->fx->lang['tr'], 'translations' => ['tr' => ['name' => 'Hacked']],
        ])->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$b1}/toggle")->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$b1}/delete")->assertStatus(404);

        $this->assertSame($before, $this->row('branches', $b1));
        $this->dontSeeInDatabase('branch_translations', ['name' => 'Hacked']);
    }

    /** Test 5 (şube) */
    public function testBranchCreatedByOwnerAlwaysBelongsToOwnTenant(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post('panel/branches', [
            'restaurant_id'       => (string) $this->fx->restaurantB,   // yok sayılmalı
            'public_code'         => 'bbbbbbbbb1',                      // yok sayılmalı
            'default_language_id' => (string) $this->fx->lang['tr'],
            'is_active'           => '1',
            'translations'        => ['tr' => ['name' => 'Yeni Şube']],
        ]);

        $branch = $this->db->table('branches')->where('slug', 'yeni-sube')->get()->getRowArray();
        $this->assertNotNull($branch);
        $this->assertSame($this->fx->restaurantA, (int) $branch['restaurant_id']);
        $this->assertNotSame('bbbbbbbbb1', $branch['public_code']);
    }

    /** Test 10 (şube listesi) */
    public function testBranchListNeverContainsAnotherTenantsBranches(): void
    {
        $list = $this->asPanelUser($this->fx->ownerA)->get('panel/branches');
        $list->assertOK();
        $list->assertSee('Şube A1');
        $list->assertSee('Şube A2');
        $list->assertDontSee('Şube B1');
        $list->assertDontSee('bbbbbbbbb1');

        $listB = $this->asPanelUser($this->fx->ownerB)->get('panel/branches');
        $listB->assertSee('Şube B1');
        $listB->assertDontSee('Şube A1');
    }

    public function testOwnerUpdatesTogglesAndDeletesOwnBranch(): void
    {
        $a2 = $this->fx->branchA2;

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$a2}", [
            'slug' => 'a2-yeni', 'default_language_id' => (string) $this->fx->lang['en'], 'phone' => '123', 'is_active' => '1',
            'translations' => ['tr' => ['name' => 'A2 Yeni'], 'en' => ['name' => 'A2 New']],
        ]);
        $row = $this->row('branches', $a2);
        $this->assertSame(['a2-yeni', $this->fx->lang['en'], '123'], [$row['slug'], (int) $row['default_language_id'], $row['phone']]);

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$a2}/toggle");
        $this->assertSame(0, (int) $this->row('branches', $a2)['is_active']);

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$a2}/delete");
        $this->assertNotNull($this->row('branches', $a2)['deleted_at']);
    }

    public function testOwnerCannotUseLanguageOutsideRestaurantAsBranchDefault(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchA1}", [
            'default_language_id' => (string) $this->fx->lang['de'], 'is_active' => '1', 'translations' => ['tr' => ['name' => 'Şube A1']],
        ]);

        $this->assertArrayHasKey('default_language_id', $_SESSION['errors'] ?? []);
        $this->assertSame($this->fx->lang['tr'], (int) $this->row('branches', $this->fx->branchA1)['default_language_id']);
    }

    // ------------------------------------------------------- Restoran (owner)

    public function testOwnerEditsOnlyNameDescriptionOfOwnRestaurant(): void
    {
        $before = $this->row('restaurants', $this->fx->restaurantA);

        $this->asPanelUser($this->fx->ownerA)->get('panel/restaurant')->assertOK();
        $this->asPanelUser($this->fx->ownerA)->post('panel/restaurant', [
            'translations'        => ['tr' => ['name' => 'A Yeni Ad', 'description' => 'Açıklama'], 'de' => ['name' => 'yok sayılır']],
            // Aşağıdakilerin hiçbiri owner tarafından değiştirilemez:
            'restaurant_id'       => (string) $this->fx->restaurantB,
            'slug'                => 'hacked',
            'currency_code'       => 'USD',
            'default_language_id' => (string) $this->fx->lang['en'],
            'is_active'           => '0',
        ]);

        $after = $this->row('restaurants', $this->fx->restaurantA);
        unset($before['updated_at'], $after['updated_at']);
        $this->assertSame($before, $after);

        $this->seeInDatabase('restaurant_translations', ['restaurant_id' => $this->fx->restaurantA, 'name' => 'A Yeni Ad']);
        // B'nin restoranı etkilenmedi.
        $this->seeInDatabase('restaurant_translations', ['restaurant_id' => $this->fx->restaurantB, 'name' => 'Restoran B']);
        $this->dontSeeInDatabase('restaurant_translations', ['restaurant_id' => $this->fx->restaurantA, 'language_id' => $this->fx->lang['de']]);
    }

    // ------------------------------------------------------------------ Profil

    public function testProfileUpdateAffectsOnlyCurrentUser(): void
    {
        $this->asPanelUser($this->fx->managerA1)->get('panel/profile')->assertOK();
        $this->asPanelUser($this->fx->managerA1)->post('panel/profile', [
            'id' => (string) $this->fx->ownerB['id'], 'name' => 'Ben Değiştim', 'email' => 'manager-yeni@test.local',
            'role' => 'owner', 'restaurant_id' => (string) $this->fx->restaurantB,
        ]);

        $me = $this->row('restaurant_users', (int) $this->fx->managerA1['id']);
        $this->assertSame(['Ben Değiştim', 'manager-yeni@test.local', 'manager', $this->fx->restaurantA], [
            $me['name'], $me['email'], $me['role'], (int) $me['restaurant_id'],
        ]);
        $this->assertSame('Owner B', $this->row('restaurant_users', (int) $this->fx->ownerB['id'])['name']);
    }

    public function testProfileEmailMustBeGloballyUnique(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post('panel/profile', ['name' => 'Owner A', 'email' => 'owner-b@test.local']);

        $this->assertArrayHasKey('email', $_SESSION['errors'] ?? []);
        $this->assertSame('owner-a@test.local', $this->row('restaurant_users', (int) $this->fx->ownerA['id'])['email']);
    }

    public function testPasswordChangeRequiresCurrentPasswordAndPolicy(): void
    {
        $id = (int) $this->fx->ownerA['id'];

        $this->asPanelUser($this->fx->ownerA)->post('panel/profile/password', [
            'current_password' => 'yanlis', 'password' => 'YeniSifre12345', 'password_confirm' => 'YeniSifre12345',
        ]);
        $this->assertArrayHasKey('current_password', $_SESSION['errors'] ?? []);

        $this->asPanelUser($this->fx->ownerA)->post('panel/profile/password', [
            'current_password' => Fixture::PASSWORD, 'password' => 'zayif', 'password_confirm' => 'zayif',
        ]);
        $this->assertArrayHasKey('password', $_SESSION['errors'] ?? []);

        $this->asPanelUser($this->fx->ownerA)->post('panel/profile/password', [
            'current_password' => Fixture::PASSWORD, 'password' => 'YeniSifre12345', 'password_confirm' => 'Farkli12345678',
        ]);
        $this->assertArrayHasKey('password_confirm', $_SESSION['errors'] ?? []);

        $this->assertTrue(password_verify(Fixture::PASSWORD, $this->row('restaurant_users', $id)['password_hash']));
    }

    public function testPasswordChangeKeepsCurrentSessionAndDropsOlderOnes(): void
    {
        $id = (int) $this->fx->ownerA['id'];

        $response = $this->asPanelUser($this->fx->ownerA)->post('panel/profile/password', [
            'current_password' => Fixture::PASSWORD, 'password' => 'YeniSifre12345', 'password_confirm' => 'YeniSifre12345',
        ]);
        $this->assertRedirectsTo($response, 'panel/profile');

        $row = $this->row('restaurant_users', $id);
        $this->assertTrue(password_verify('YeniSifre12345', $row['password_hash']));
        $this->assertNotNull($row['password_changed_at']);

        // Parolayı değiştiren oturum geçerli kalır.
        $current = $_SESSION['panel_auth'];
        $this->withSession(['panel_auth' => $current])->get('panel')->assertOK();

        // Parola değişiminden ÖNCE açılmış başka bir oturum düşer.
        $old = ['id' => $id, 'restaurant_id' => $this->fx->restaurantA, 'login_at' => strtotime($row['password_changed_at']) - 3600];
        $this->assertRedirectsTo($this->withSession(['panel_auth' => $old])->get('panel'), 'panel/login');
    }
}
