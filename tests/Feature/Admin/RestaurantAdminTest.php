<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\DbTestCase;
use Tests\Support\Fixture;

/**
 * Admin: restoran, restoran dilleri, kullanıcılar, şubeler (spec §8.5, §9.1, §10.1).
 *
 * @internal
 */
final class RestaurantAdminTest extends DbTestCase
{
    // -------------------------------------------------------------- Restoran

    public function testPagesRender(): void
    {
        $a = $this->fx->restaurantA;

        foreach ([
            'admin', 'admin/restaurants', 'admin/restaurants/new', "admin/restaurants/{$a}", "admin/restaurants/{$a}/edit",
            "admin/restaurants/{$a}/languages", "admin/restaurants/{$a}/users", "admin/restaurants/{$a}/users/new",
            "admin/restaurants/{$a}/users/{$this->fx->ownerA['id']}/edit", "admin/restaurants/{$a}/branches",
            "admin/restaurants/{$a}/branches/new", "admin/restaurants/{$a}/branches/{$this->fx->branchA1}/edit",
        ] as $path) {
            $this->asAdmin()->get($path)->assertOK();
        }

        $this->asAdmin()->get('admin/restaurants/99999')->assertStatus(404);
    }

    public function testCreateRestaurantWithLanguagesTranslationsAndOwnerInOneTransaction(): void
    {
        $response = $this->asAdmin()->post('admin/restaurants', [
            'slug'                => '',
            'currency_code'       => 'eur',
            'is_active'           => '1',
            'language_ids'        => [(string) $this->fx->lang['tr'], (string) $this->fx->lang['en']],
            'default_language_id' => (string) $this->fx->lang['tr'],
            'translations'        => ['tr' => ['name' => 'Çöp Şiş Köşesi'], 'en' => ['name' => 'Skewer Corner'], 'de' => ['name' => 'ignored']],
            'owner_name'          => 'Yeni Sahip',
            'owner_email'         => 'Yeni@Test.Local',
            'owner_password'      => 'GucluSifre123',
        ]);

        $restaurant = $this->db->table('restaurants')->where('slug', 'cop-sis-kosesi')->get()->getRowArray();
        $this->assertNotNull($restaurant, 'Slug Türkçe karakterler dönüştürülerek üretilmeli');
        $this->assertRedirectsTo($response, 'admin/restaurants/' . $restaurant['id']);
        $this->assertSame('EUR', $restaurant['currency_code']);
        $this->assertSame(2, $this->db->table('restaurant_languages')->where('restaurant_id', $restaurant['id'])->countAllResults());
        $this->assertSame(2, $this->db->table('restaurant_translations')->where('restaurant_id', $restaurant['id'])->countAllResults());

        $owner = $this->db->table('restaurant_users')->where('email', 'yeni@test.local')->get()->getRowArray();
        $this->assertSame('owner', $owner['role']);
        $this->assertSame((int) $restaurant['id'], (int) $owner['restaurant_id']);
        $this->assertTrue(password_verify('GucluSifre123', $owner['password_hash']));
    }

    public function testCreateRestaurantSlugCollisionGetsSuffix(): void
    {
        $post = [
            'currency_code' => 'TRY', 'language_ids' => [(string) $this->fx->lang['tr']],
            'default_language_id' => (string) $this->fx->lang['tr'], 'translations' => ['tr' => ['name' => 'Restoran A']],
        ];

        $this->asAdmin()->post('admin/restaurants', $post);
        $this->asAdmin()->post('admin/restaurants', $post);

        $this->seeInDatabase('restaurants', ['slug' => 'restoran-a-2']);
        $this->seeInDatabase('restaurants', ['slug' => 'restoran-a-3']);
    }

    public function testCreateRestaurantRollsBackEverythingWhenOwnerIsInvalid(): void
    {
        $before = $this->db->table('restaurants')->countAllResults();

        $this->asAdmin()->post('admin/restaurants', [
            'currency_code' => 'TRY', 'language_ids' => [(string) $this->fx->lang['tr']],
            'default_language_id' => (string) $this->fx->lang['tr'], 'translations' => ['tr' => ['name' => 'Yarım Kalan']],
            'owner_name' => 'X', 'owner_email' => 'owner-a@test.local', 'owner_password' => 'GucluSifre123', // e-posta kullanımda
        ]);

        $this->assertArrayHasKey('email', $_SESSION['errors'] ?? []);
        $this->assertSame($before, $this->db->table('restaurants')->countAllResults());
        $this->assertSame(0, $this->db->table('restaurant_translations')->where('name', 'Yarım Kalan')->countAllResults());
    }

    public function testCreateRestaurantValidatesLanguages(): void
    {
        $base = ['currency_code' => 'TRY', 'translations' => ['tr' => ['name' => 'X Restoran']]];

        // Dil yok
        $this->asAdmin()->post('admin/restaurants', $base + ['default_language_id' => (string) $this->fx->lang['tr']]);
        $this->assertArrayHasKey('language_ids', $_SESSION['errors'] ?? []);

        // Varsayılan dil seçilenler arasında değil
        $this->asAdmin()->post('admin/restaurants', $base + [
            'language_ids' => [(string) $this->fx->lang['tr']], 'default_language_id' => (string) $this->fx->lang['en'],
        ]);
        $this->assertArrayHasKey('default_language_id', $_SESSION['errors'] ?? []);

        // Varsayılan dilde ad yok
        $this->asAdmin()->post('admin/restaurants', [
            'currency_code' => 'TRY', 'language_ids' => [(string) $this->fx->lang['tr'], (string) $this->fx->lang['en']],
            'default_language_id' => (string) $this->fx->lang['tr'], 'translations' => ['en' => ['name' => 'Only EN']],
        ]);
        $this->assertArrayHasKey('translations.tr.name', $_SESSION['errors'] ?? []);

        $this->assertSame(2, $this->db->table('restaurants')->countAllResults());
    }

    public function testUpdateToggleAndSoftDeleteRestaurant(): void
    {
        $b = $this->fx->restaurantB;

        $this->asAdmin()->post("admin/restaurants/{$b}", [
            'slug' => 'yeni-b', 'currency_code' => 'USD', 'is_active' => '1',
            'default_language_id' => (string) $this->fx->lang['en'],
            'translations' => ['tr' => ['name' => 'B TR'], 'en' => ['name' => 'B EN']],
        ]);
        $row = $this->row('restaurants', $b);
        $this->assertSame(['yeni-b', 'USD', $this->fx->lang['en']], [$row['slug'], $row['currency_code'], (int) $row['default_language_id']]);

        $this->asAdmin()->post("admin/restaurants/{$b}/toggle");
        $this->assertSame(0, (int) $this->row('restaurants', $b)['is_active']);

        $this->asAdmin()->post("admin/restaurants/{$b}/delete");
        $row = $this->row('restaurants', $b);
        $this->assertNotNull($row['deleted_at']);
        $this->assertSame("yeni-b--deleted-{$b}", $row['slug']);
        $this->asAdmin()->get("admin/restaurants/{$b}")->assertStatus(404);
    }

    // ------------------------------------------------------- Restoran dilleri

    /** §8.5 / §14.2: varsayılan dil olarak kullanılan dil restorandan çıkarılamaz */
    public function testDefaultLanguageCannotBeRemovedFromRestaurant(): void
    {
        $a = $this->fx->restaurantA;

        // tr restoranın (ve şubelerin) varsayılan dili → çıkarılamaz
        $this->asAdmin()->post("admin/restaurants/{$a}/languages", ['language_ids' => [(string) $this->fx->lang['en']]]);
        $this->assertNotEmpty($_SESSION['error'] ?? null);
        $this->assertSame(2, $this->db->table('restaurant_languages')->where('restaurant_id', $a)->countAllResults());
    }

    public function testBranchDefaultLanguageCannotBeRemovedFromRestaurant(): void
    {
        $a = $this->fx->restaurantA;
        $this->db->table('branches')->where('id', $this->fx->branchA2)->update(['default_language_id' => $this->fx->lang['en']]);

        // en restoranın varsayılanı değil ama A2 şubesinin varsayılanı → çıkarılamaz
        $this->asAdmin()->post("admin/restaurants/{$a}/languages", ['language_ids' => [(string) $this->fx->lang['tr']]]);
        $this->assertNotEmpty($_SESSION['error'] ?? null);
        $this->assertSame(2, $this->db->table('restaurant_languages')->where('restaurant_id', $a)->countAllResults());
    }

    public function testRemovingLanguageKeepsTranslationsAndAddingWorks(): void
    {
        $a = $this->fx->restaurantA;

        // en çıkar, de ekle
        $this->asAdmin()->post("admin/restaurants/{$a}/languages", [
            'language_ids' => [(string) $this->fx->lang['tr'], (string) $this->fx->lang['de']],
        ]);

        $ids = array_map('intval', array_column(
            $this->db->table('restaurant_languages')->where('restaurant_id', $a)->orderBy('sort_order')->get()->getResultArray(),
            'language_id',
        ));
        $this->assertSame([$this->fx->lang['tr'], $this->fx->lang['de']], $ids);

        // Çıkarılan dilin çevirileri silinmez.
        $this->seeInDatabase('product_translations', ['product_id' => $this->fx->productA1, 'language_id' => $this->fx->lang['en']]);
        $this->seeInDatabase('restaurant_translations', ['restaurant_id' => $a, 'language_id' => $this->fx->lang['en']]);

        // B'nin dilleri etkilenmedi.
        $this->assertSame(2, $this->db->table('restaurant_languages')->where('restaurant_id', $this->fx->restaurantB)->countAllResults());
    }

    public function testSyncLanguagesRejectsUnknownOrEmptySelection(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin()->post("admin/restaurants/{$a}/languages", ['language_ids' => [(string) $this->fx->lang['tr'], '99999']]);
        $this->assertArrayHasKey('language_ids', $_SESSION['errors'] ?? []);

        $this->asAdmin()->post("admin/restaurants/{$a}/languages", []);
        $this->assertArrayHasKey('language_ids', $_SESSION['errors'] ?? []);

        $this->assertSame(2, $this->db->table('restaurant_languages')->where('restaurant_id', $a)->countAllResults());
    }

    // ------------------------------------------------------------ Kullanıcılar

    public function testAdminCreatesManagerWithBranches(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin()->post("admin/restaurants/{$a}/users", [
            'name' => 'Yeni Manager', 'email' => 'm2@test.local', 'password' => 'GucluSifre123', 'role' => 'manager',
            'is_active' => '1', 'branch_ids' => [(string) $this->fx->branchA2],
        ]);

        $user = $this->db->table('restaurant_users')->where('email', 'm2@test.local')->get()->getRowArray();
        $this->assertSame($a, (int) $user['restaurant_id']);
        $this->seeInDatabase('restaurant_user_branches', ['user_id' => $user['id'], 'branch_id' => $this->fx->branchA2, 'restaurant_id' => $a]);
    }

    public function testAdminCannotAssignManagerToAnotherRestaurantsBranch(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin()->post("admin/restaurants/{$a}/users", [
            'name' => 'Sızma', 'email' => 'x@test.local', 'password' => 'GucluSifre123', 'role' => 'manager',
            'is_active' => '1', 'branch_ids' => [(string) $this->fx->branchA1, (string) $this->fx->branchB1],
        ]);

        $this->assertArrayHasKey('branch_ids', $_SESSION['errors'] ?? []);
        $this->dontSeeInDatabase('restaurant_users', ['email' => 'x@test.local']);
    }

    public function testUserValidation(): void
    {
        $a    = $this->fx->restaurantA;
        $base = ['name' => 'X', 'role' => 'owner', 'is_active' => '1'];

        $this->asAdmin()->post("admin/restaurants/{$a}/users", $base + ['email' => 'zayif@test.local', 'password' => 'kisa1']);
        $this->assertArrayHasKey('password', $_SESSION['errors'] ?? []);

        $this->asAdmin()->post("admin/restaurants/{$a}/users", $base + ['email' => 'zayif@test.local', 'password' => 'sadeceharflerden']);
        $this->assertArrayHasKey('password', $_SESSION['errors'] ?? []);

        // E-posta global unique: B'nin kullanıcısının e-postası A'da kullanılamaz.
        $this->asAdmin()->post("admin/restaurants/{$a}/users", $base + ['email' => 'owner-b@test.local', 'password' => 'GucluSifre123']);
        $this->assertArrayHasKey('email', $_SESSION['errors'] ?? []);

        $this->asAdmin()->post("admin/restaurants/{$a}/users", ['name' => 'X', 'role' => 'manager', 'email' => 'm3@test.local', 'password' => 'GucluSifre123']);
        $this->assertArrayHasKey('branch_ids', $_SESSION['errors'] ?? []);

        $this->assertSame(3, $this->db->table('restaurant_users')->countAllResults());
    }

    /** §14.2: son owner silinemez / pasifleştirilemez / manager'a düşürülemez */
    public function testLastActiveOwnerIsProtected(): void
    {
        $a     = $this->fx->restaurantA;
        $owner = (int) $this->fx->ownerA['id'];

        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$owner}/delete");
        $this->assertNull($this->row('restaurant_users', $owner)['deleted_at']);
        $this->assertNotEmpty($_SESSION['error'] ?? null);

        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$owner}/toggle");
        $this->assertSame(1, (int) $this->row('restaurant_users', $owner)['is_active']);

        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$owner}", [
            'name' => 'Owner A', 'email' => 'owner-a@test.local', 'password' => '', 'role' => 'manager',
            'is_active' => '1', 'branch_ids' => [(string) $this->fx->branchA1],
        ]);
        $this->assertSame('owner', $this->row('restaurant_users', $owner)['role']);

        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$owner}", [
            'name' => 'Owner A', 'email' => 'owner-a@test.local', 'password' => '', 'role' => 'owner',
        ]); // is_active gönderilmedi → pasifleştirme denemesi
        $this->assertSame(1, (int) $this->row('restaurant_users', $owner)['is_active']);
    }

    public function testOwnerCanBeDeletedWhenAnotherActiveOwnerExists(): void
    {
        $a      = $this->fx->restaurantA;
        $owner  = (int) $this->fx->ownerA['id'];
        $second = $this->fx->user($a, 'Owner A2', 'owner-a2@test.local', 'owner');

        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$owner}/delete");

        $row = $this->row('restaurant_users', $owner);
        $this->assertNotNull($row['deleted_at']);
        $this->assertSame("owner-a@test.local--deleted-{$owner}", $row['email']);

        // Artık A2 son owner → korunur.
        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$second['id']}/delete");
        $this->assertNull($this->row('restaurant_users', (int) $second['id'])['deleted_at']);

        // Silinen e-posta yeniden kullanılabilir.
        $this->asAdmin()->post("admin/restaurants/{$a}/users", [
            'name' => 'Geri Geldi', 'email' => 'owner-a@test.local', 'password' => 'GucluSifre123', 'role' => 'owner', 'is_active' => '1',
        ]);
        $this->seeInDatabase('restaurant_users', ['email' => 'owner-a@test.local', 'name' => 'Geri Geldi']);
    }

    public function testInactiveSecondOwnerDoesNotCountAsBackup(): void
    {
        $a = $this->fx->restaurantA;
        $this->fx->user($a, 'Pasif Owner', 'pasif@test.local', 'owner', 0);

        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$this->fx->ownerA['id']}/toggle");

        $this->assertSame(1, (int) $this->row('restaurant_users', (int) $this->fx->ownerA['id'])['is_active']);
    }

    public function testPasswordChangeByAdminSetsPasswordChangedAt(): void
    {
        $a = $this->fx->restaurantA;
        $m = (int) $this->fx->managerA1['id'];

        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$m}", [
            'name' => 'Manager A1', 'email' => 'manager-a1@test.local', 'password' => 'YeniSifre12345', 'role' => 'manager',
            'is_active' => '1', 'branch_ids' => [(string) $this->fx->branchA1],
        ]);

        $row = $this->row('restaurant_users', $m);
        $this->assertNotNull($row['password_changed_at']);
        $this->assertTrue(password_verify('YeniSifre12345', $row['password_hash']));
        $this->assertFalse(password_verify(Fixture::PASSWORD, $row['password_hash']));
    }

    // ---------------------------------------------------------------- Şubeler

    public function testAdminCreatesBranchWithGeneratedSlugAndPublicCode(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin()->post("admin/restaurants/{$a}/branches", [
            'slug' => '', 'default_language_id' => (string) $this->fx->lang['en'], 'phone' => '0212 000 00 00',
            'is_active' => '1', 'sort_order' => '5',
            'translations' => ['tr' => ['name' => 'Üsküdar Şubesi', 'address' => 'Adres'], 'en' => ['name' => 'Uskudar']],
        ]);

        $branch = $this->db->table('branches')->where('slug', 'uskudar-subesi')->get()->getRowArray();
        $this->assertNotNull($branch);
        $this->assertSame($a, (int) $branch['restaurant_id']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $branch['public_code']);
        $this->assertSame($this->fx->lang['en'], (int) $branch['default_language_id']);
        $this->assertSame(2, $this->db->table('branch_translations')->where('branch_id', $branch['id'])->countAllResults());
    }

    public function testBranchDefaultLanguageMustBeAnActiveRestaurantLanguage(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin()->post("admin/restaurants/{$a}/branches", [
            'default_language_id' => (string) $this->fx->lang['de'], // restoranda yok
            'translations' => ['tr' => ['name' => 'Olmaz']],
        ]);

        $this->assertArrayHasKey('default_language_id', $_SESSION['errors'] ?? []);
        $this->assertSame(3, $this->db->table('branches')->countAllResults());
    }

    public function testBranchRequiresNameInRestaurantDefaultLanguage(): void
    {
        $a = $this->fx->restaurantA;

        $this->asAdmin()->post("admin/restaurants/{$a}/branches", [
            'default_language_id' => (string) $this->fx->lang['en'], 'translations' => ['en' => ['name' => 'Only EN']],
        ]);

        $this->assertArrayHasKey('translations.tr.name', $_SESSION['errors'] ?? []);
    }

    /** §9.1: iç içe ID doğrulaması */
    public function testNestedIdsAreValidatedAgainstRestaurantInUrl(): void
    {
        $a = $this->fx->restaurantA;
        $b = $this->fx->restaurantB;

        // B'nin şubesi/kullanıcısı A'nın URL'si altında → 404
        $this->asAdmin()->get("admin/restaurants/{$a}/branches/{$this->fx->branchB1}/edit")->assertStatus(404);
        $this->asAdmin()->post("admin/restaurants/{$a}/branches/{$this->fx->branchB1}/delete")->assertStatus(404);
        $this->asAdmin()->post("admin/restaurants/{$a}/branches/{$this->fx->branchB1}/regenerate-code")->assertStatus(404);
        $this->asAdmin()->get("admin/restaurants/{$a}/users/{$this->fx->ownerB['id']}/edit")->assertStatus(404);
        $this->asAdmin()->post("admin/restaurants/{$a}/users/{$this->fx->ownerB['id']}/delete")->assertStatus(404);
        $this->asAdmin()->post("admin/restaurants/{$b}/users/{$this->fx->managerA1['id']}/toggle")->assertStatus(404);

        $this->assertNull($this->row('branches', $this->fx->branchB1)['deleted_at']);
        $this->assertSame('bbbbbbbbb1', $this->row('branches', $this->fx->branchB1)['public_code']);
        $this->assertNull($this->row('restaurant_users', (int) $this->fx->ownerB['id'])['deleted_at']);
        $this->assertSame(1, (int) $this->row('restaurant_users', (int) $this->fx->managerA1['id'])['is_active']);
    }

    public function testRegenerateCodeToggleAndSoftDeleteBranch(): void
    {
        $a  = $this->fx->restaurantA;
        $a2 = $this->fx->branchA2;

        $this->asAdmin()->post("admin/restaurants/{$a}/branches/{$a2}/regenerate-code");
        $code = $this->row('branches', $a2)['public_code'];
        $this->assertNotSame('aaaaaaaaa2', $code);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $code);

        $this->asAdmin()->post("admin/restaurants/{$a}/branches/{$a2}/toggle");
        $this->assertSame(0, (int) $this->row('branches', $a2)['is_active']);

        $this->asAdmin()->post("admin/restaurants/{$a}/branches/{$a2}/delete");
        $row = $this->row('branches', $a2);
        $this->assertNotNull($row['deleted_at']);
        $this->assertSame("a2--deleted-{$a2}", $row['slug']);

        // Silinen slug yeniden kullanılabilir.
        $this->asAdmin()->post("admin/restaurants/{$a}/branches", [
            'slug' => 'a2', 'default_language_id' => (string) $this->fx->lang['tr'], 'translations' => ['tr' => ['name' => 'Yeni A2']],
        ]);
        $this->seeInDatabase('branches', ['slug' => 'a2', 'restaurant_id' => $a, 'deleted_at' => null]);
    }

    public function testSameBranchSlugAllowedInDifferentRestaurantsButNotWithinOne(): void
    {
        $post = ['slug' => 'a1', 'default_language_id' => (string) $this->fx->lang['tr'], 'translations' => ['tr' => ['name' => 'Çakışma']]];

        $this->asAdmin()->post("admin/restaurants/{$this->fx->restaurantA}/branches", $post);
        $this->assertArrayHasKey('slug', $_SESSION['errors'] ?? []);

        $this->asAdmin()->post("admin/restaurants/{$this->fx->restaurantB}/branches", $post);
        $this->seeInDatabase('branches', ['slug' => 'a1', 'restaurant_id' => $this->fx->restaurantB]);
    }
}
