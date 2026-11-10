<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MenuService;
use CodeIgniter\Events\Events;
use Tests\Support\DbTestCase;

/**
 * Herkese açık menü (spec §8.3, §8.4, §9.3, §11.3, §14.2).
 *
 * @internal
 */
final class PublicMenuTest extends DbTestCase
{
    private const CODE_A1 = 'aaaaaaaaa1';
    private const CODE_B1 = 'bbbbbbbbb1';

    /**
     * @return array<string, mixed>
     */
    private function api(string $code, string $query = ''): array
    {
        $response = $this->get("api/v1/menu/{$code}{$query}");
        $response->assertOK();
        $body = json_decode((string) $response->getJSON(), true);
        $this->assertSame('success', $body['status']);

        return $body['data'];
    }

    /**
     * @param array<string, mixed> $menu
     *
     * @return list<string>
     */
    private function productNames(array $menu): array
    {
        $names = [];

        foreach ($menu['categories'] as $category) {
            foreach ($category['products'] as $product) {
                $names[] = $product['name'];
            }
        }

        return $names;
    }

    public function testJsonStructureMatchesSpec(): void
    {
        $this->fx->productAllergen($this->fx->restaurantA, $this->fx->productA1, $this->fx->allergen['gluten'], 'contains');
        $this->fx->productAllergen($this->fx->restaurantA, $this->fx->productA1, $this->fx->allergen['milk'], 'may_contain');

        $menu = $this->api(self::CODE_A1);

        $this->assertSame(['restaurant', 'branch', 'language', 'languages', 'allergens', 'categories'], array_keys($menu));
        $this->assertSame(['name', 'description', 'logo_url', 'currency'], array_keys($menu['restaurant']));
        $this->assertSame('Restoran A', $menu['restaurant']['name']);
        $this->assertSame('TRY', $menu['restaurant']['currency']);
        $this->assertSame(['code' => self::CODE_A1, 'name' => 'Şube A1', 'address' => 'Şube A1 adresi', 'phone' => null], $menu['branch']);
        $this->assertSame(['code' => 'tr', 'direction' => 'ltr'], $menu['language']);
        $this->assertSame([['code' => 'tr', 'native_name' => 'Türkçe'], ['code' => 'en', 'native_name' => 'English']], $menu['languages']);

        $this->assertCount(1, $menu['categories']); // "Tatlılar A1" ürünsüz → listelenmez
        $category = $menu['categories'][0];
        $this->assertSame(['id', 'name', 'description', 'image_url', 'products'], array_keys($category));
        $this->assertSame('Çorbalar A1', $category['name']);

        $product = $category['products'][0];
        $this->assertSame(['id', 'name', 'description', 'price', 'image_url', 'thumb_url', 'allergens'], array_keys($product));
        $this->assertSame('Mercimek A1', $product['name']);
        $this->assertSame('100.00', $product['price']); // string
        $this->assertSame([
            ['id' => $this->fx->allergen['gluten'], 'level' => 'contains'],
            ['id' => $this->fx->allergen['milk'], 'level' => 'may_contain'],
        ], $product['allergens']);

        $this->assertSame(['id', 'code', 'name', 'icon_url'], array_keys($menu['allergens'][0]));
        $this->assertSame(['Gluten', 'Süt'], array_column($menu['allergens'], 'name'));
    }

    public function testApiSendsPublicCacheHeaderAndIsNotCsrfProtected(): void
    {
        config('Filters')->globals['before']['csrf'] = ['except' => ['api/*']];

        $response = $this->get('api/v1/menu/' . self::CODE_A1);

        $response->assertOK();
        $this->assertStringContainsString('max-age=60', $response->response()->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('public', $response->response()->getHeaderLine('Cache-Control'));
    }

    public function testItemsAreSortedBySortOrderThenId(): void
    {
        $this->db->table('products')->where('id', $this->fx->productA1)->update(['sort_order' => 5]);

        $this->assertSame(['Ezogelin A1', 'Mercimek A1'], $this->productNames($this->api(self::CODE_A1)));
    }

    /** §14.2 */
    public function testInactiveAndDeletedProductsAndCategoriesAreHidden(): void
    {
        $a = $this->fx->restaurantA;
        $this->fx->product($a, $this->fx->branchA1, $this->fx->categoryA1, 'Pasif Ürün', null, '1.00', 9, 0);
        $deleted = $this->fx->product($a, $this->fx->branchA1, $this->fx->categoryA1, 'Silinmiş Ürün', null, '1.00', 9);
        $this->db->table('products')->where('id', $deleted)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $inactiveCategory = $this->fx->category($a, $this->fx->branchA1, 'Pasif Kategori', null, 5, 0);
        $this->fx->product($a, $this->fx->branchA1, $inactiveCategory, 'Pasif Kategorideki Ürün', null, '1.00', 0);

        $deletedCategory = $this->fx->category($a, $this->fx->branchA1, 'Silinmiş Kategori', null, 6);
        $this->fx->product($a, $this->fx->branchA1, $deletedCategory, 'Silinmiş Kategorideki Ürün', null, '1.00', 0);
        $this->db->table('categories')->where('id', $deletedCategory)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $menu = $this->api(self::CODE_A1);

        $this->assertSame(['Mercimek A1', 'Ezogelin A1'], $this->productNames($menu));
        $this->assertSame(['Çorbalar A1'], array_column($menu['categories'], 'name'));
    }

    public function testOnlyAllergensUsedByVisibleProductsAreListedAndInactiveOnesAreHidden(): void
    {
        $a      = $this->fx->restaurantA;
        $hidden = $this->fx->product($a, $this->fx->branchA1, $this->fx->categoryA1, 'Pasif', null, '1.00', 9, 0);
        $this->fx->productAllergen($a, $hidden, $this->fx->allergen['milk']);            // yalnızca pasif üründe
        $this->fx->productAllergen($a, $this->fx->productA1, $this->fx->allergen['gluten']);
        $this->fx->productAllergen($a, $this->fx->productA1, $this->fx->allergen['lupin']); // pasif alerjen

        $menu = $this->api(self::CODE_A1);

        $this->assertSame(['gluten'], array_column($menu['allergens'], 'code'));
        $this->assertSame([['id' => $this->fx->allergen['gluten'], 'level' => 'contains']], $menu['categories'][0]['products'][0]['allergens']);
    }

    // ------------------------------------------------------------------ Diller

    public function testValidLangParameterIsUsed(): void
    {
        $menu = $this->api(self::CODE_A1, '?lang=en');

        $this->assertSame('en', $menu['language']['code']);
        $this->assertSame('Restaurant A', $menu['restaurant']['name']);
        $this->assertSame(['Lentil A1', 'Ezogelin A1'], $this->productNames($menu));
        // Şubenin en çevirisi yok → şubenin varsayılan diline (tr) düşer.
        $this->assertSame('Şube A1', $menu['branch']['name']);
    }

    /** §14.2 */
    public function testInvalidLangFallsBackToBranchDefault(): void
    {
        foreach (['?lang=xx', '?lang=de', '?lang=', "?lang=en'%20OR%201=1"] as $query) {
            $menu = $this->api(self::CODE_A1, $query);
            $this->assertSame('tr', $menu['language']['code'], $query);
            $this->assertSame('Mercimek A1', $this->productNames($menu)[0]);
        }
    }

    public function testBranchDefaultLanguageIsUsedWhenNoLangGiven(): void
    {
        $this->db->table('branches')->where('id', $this->fx->branchA1)->update(['default_language_id' => $this->fx->lang['en']]);

        $this->assertSame('en', $this->api(self::CODE_A1)['language']['code']);
    }

    public function testLanguageRemovedFromRestaurantOrGloballyInactiveIsNotOffered(): void
    {
        $this->db->table('restaurant_languages')
            ->where('restaurant_id', $this->fx->restaurantA)->where('language_id', $this->fx->lang['en'])->update(['is_active' => 0]);

        $menu = $this->api(self::CODE_A1, '?lang=en');
        $this->assertSame('tr', $menu['language']['code']);
        $this->assertSame(['tr'], array_column($menu['languages'], 'code'));

        // B'de en hâlâ restoran düzeyinde aktif, ama global olarak pasifleşirse orada da gösterilmez.
        $this->db->table('languages')->where('id', $this->fx->lang['en'])->update(['is_active' => 0]);
        $this->assertSame(['tr'], array_column($this->api(self::CODE_B1, '?lang=en')['languages'], 'code'));
    }

    public function testTranslationFallbackChainInMenu(): void
    {
        $a = $this->fx->restaurantA;
        $this->fx->product($a, $this->fx->branchA1, $this->fx->categoryA1, 'Sadece Türkçe', null, '5.00', 7);

        // İstenen dil en; ürünün en çevirisi yok → şube varsayılanı (tr).
        $this->assertContains('Sadece Türkçe', $this->productNames($this->api(self::CODE_A1, '?lang=en')));

        // Zincirdeki hiçbir dilde çevirisi olmayan ürün → mevcut ilk çeviri (de).
        $onlyGerman = $this->fx->insert('products', [
            'restaurant_id' => $a, 'branch_id' => $this->fx->branchA1, 'category_id' => $this->fx->categoryA1, 'price' => '6.00', 'sort_order' => 8,
        ]);
        $this->fx->insert('product_translations', ['product_id' => $onlyGerman, 'language_id' => $this->fx->lang['de'], 'name' => 'Nur Deutsch']);
        (new MenuService())->invalidate($this->fx->branchA1);

        $this->assertContains('Nur Deutsch', $this->productNames($this->api(self::CODE_A1, '?lang=en')));
    }

    public function testHtmlMenuRendersAndSetsLanguageCookieOnlyForValidLang(): void
    {
        $response = $this->get('m/' . self::CODE_A1 . '?lang=en');
        $response->assertOK();
        $response->assertSee('Lentil A1');
        $this->assertStringContainsString('<html lang="en" dir="ltr">', (string) $response->response()->getBody());
        $this->assertTrue($response->response()->hasCookie('pixmenu_lang_' . self::CODE_A1, 'en'));

        $invalid = $this->get('m/' . self::CODE_A1 . '?lang=xx');
        $invalid->assertOK();
        $invalid->assertSee('Mercimek A1');
        $this->assertFalse($invalid->response()->hasCookie('pixmenu_lang_' . self::CODE_A1));
    }

    public function testCookieLanguageIsUsedWhenNoValidLangParameter(): void
    {
        $service = new MenuService();

        $this->assertSame('en', $service->build(self::CODE_A1, null, 'en')['language']['code']);
        $this->assertSame('en', $service->build(self::CODE_A1, 'xx', 'en')['language']['code']);
        $this->assertSame('tr', $service->build(self::CODE_A1, 'tr', 'en')['language']['code']);
        $this->assertSame('tr', $service->build(self::CODE_A1, null, 'de')['language']['code']);
    }

    public function testAcceptLanguageHeaderIsIgnored(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('api/v1/menu/' . self::CODE_A1);

        $this->assertSame('tr', json_decode((string) $response->getJSON(), true)['data']['language']['code']);
    }

    // ------------------------------------------------------------ 404 durumları

    /** Test 12 (menü kısmı) */
    public function testDeactivatedRestaurantMenuReturns404(): void
    {
        $this->get('m/' . self::CODE_B1)->assertOK();
        $this->get('api/v1/menu/' . self::CODE_B1)->assertOK();

        // Admin pasifleştirir (cache de temizlenir).
        $this->asAdmin()->post("admin/restaurants/{$this->fx->restaurantB}/toggle");

        $this->get('m/' . self::CODE_B1)->assertStatus(404);
        $api = $this->get('api/v1/menu/' . self::CODE_B1);
        $api->assertStatus(404);
        $this->assertSame('error', json_decode((string) $api->getJSON(), true)['status']);

        // A etkilenmez.
        $this->get('m/' . self::CODE_A1)->assertOK();
    }

    public function testInactiveOrDeletedBranchAndDeletedRestaurantReturn404(): void
    {
        $this->db->table('branches')->where('id', $this->fx->branchA1)->update(['is_active' => 0]);
        $this->get('api/v1/menu/' . self::CODE_A1)->assertStatus(404);

        $this->db->table('branches')->where('id', $this->fx->branchA2)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->get('api/v1/menu/aaaaaaaaa2')->assertStatus(404);

        $this->db->table('restaurants')->where('id', $this->fx->restaurantB)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->get('m/' . self::CODE_B1)->assertStatus(404);
    }

    public function testUnknownOrMalformedCodesReturn404(): void
    {
        foreach (['zzzzzzzzzz', 'abc', '1', (string) $this->fx->branchA1, 'aaaaaaaaa1x'] as $code) {
            $this->get("api/v1/menu/{$code}")->assertStatus(404);
            $this->get("m/{$code}")->assertStatus(404);
        }
    }

    // --------------------------------------------------------------- İzolasyon

    public function testMenuNeverContainsAnotherRestaurantsContent(): void
    {
        $this->fx->productAllergen($this->fx->restaurantB, $this->fx->productB1, $this->fx->allergen['milk']);

        $a = json_encode($this->api(self::CODE_A1), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Gizli', $a);
        $this->assertStringNotContainsString('Restoran B', $a);
        $this->assertStringNotContainsString('A2', $a); // aynı restoranın diğer şubesi de yok
        $this->assertStringNotContainsString('milk', $a);

        $b = $this->api(self::CODE_B1);
        $this->assertSame(['Gizli Ürün B'], $this->productNames($b));
        $this->assertSame('Restoran B', $b['restaurant']['name']);
    }

    // ---------------------------------------------------------- Performans/cache

    /** §11.3 / §14.2: sorgu sayısı dil ve ürün sayısından bağımsız, ≤ 10 */
    public function testQueryCountIsConstantAndAtMostTen(): void
    {
        $count = function (): int {
            cache()->clean();
            $queries = 0;
            $counter = static function () use (&$queries): void {
                $queries++;
            };
            Events::on('DBQuery', $counter);
            (new MenuService())->build(self::CODE_A1, 'en');
            Events::removeListener('DBQuery', $counter);

            return $queries;
        };

        $a = $this->fx->restaurantA;

        // Küçük menü: tüm sorgu türlerini tetikleyecek şekilde bir alerjen ataması ve
        // zincirdeki hiçbir dilde çevirisi olmayan bir ürün ("mevcut ilk çeviri" sorgusu).
        $this->fx->productAllergen($a, $this->fx->productA1, $this->fx->allergen['gluten']);
        $orphan = $this->fx->insert('products', [
            'restaurant_id' => $a, 'branch_id' => $this->fx->branchA1, 'category_id' => $this->fx->categoryA1, 'price' => '6.00',
        ]);
        $this->fx->insert('product_translations', ['product_id' => $orphan, 'language_id' => $this->fx->lang['de'], 'name' => 'Nur Deutsch']);

        $small = $count();

        // Büyük menü: çok daha fazla kategori, ürün, alerjen ataması ve dil.
        $this->db->table('restaurant_languages')->insert(['restaurant_id' => $a, 'language_id' => $this->fx->lang['de'], 'sort_order' => 2, 'is_active' => 1]);

        for ($c = 0; $c < 6; $c++) {
            $categoryId = $this->fx->category($a, $this->fx->branchA1, "Kategori {$c}", "Category {$c}", 10 + $c);

            for ($p = 0; $p < 12; $p++) {
                $productId = $this->fx->product($a, $this->fx->branchA1, $categoryId, "Ürün {$c}-{$p}", $p % 2 === 0 ? "Product {$c}-{$p}" : null, '10.00', $p);
                $this->fx->productAllergen($a, $productId, $this->fx->allergen[$p % 2 === 0 ? 'gluten' : 'milk']);
            }
        }

        $large = $count();

        $this->assertLessThanOrEqual(10, $small, "Küçük menü {$small} sorgu yaptı");
        $this->assertLessThanOrEqual(10, $large, "Büyük menü {$large} sorgu yaptı");
        $this->assertSame($small, $large, 'Sorgu sayısı dil ve ürün sayısından bağımsız sabit olmalı');
    }

    public function testResultIsCachedAndInvalidatedByPanelChanges(): void
    {
        $this->assertSame('Mercimek A1', $this->productNames($this->api(self::CODE_A1))[0]);

        // Doğrudan DB değişikliği cache'i atlatmaz → eski veri gelir (cache çalışıyor).
        $this->db->table('product_translations')
            ->where('product_id', $this->fx->productA1)->where('language_id', $this->fx->lang['tr'])->update(['name' => 'DB ile Değişti']);
        $this->assertSame('Mercimek A1', $this->productNames($this->api(self::CODE_A1))[0]);

        // Panel üzerinden güncelleme → ilgili şubenin tüm dil anahtarları silinir.
        $this->api(self::CODE_A1, '?lang=en');
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchA1}/products/{$this->fx->productA1}", [
            'category_id' => (string) $this->fx->categoryA1, 'price' => '250', 'is_active' => '1',
            'translations' => ['tr' => ['name' => 'Panelden Güncel'], 'en' => ['name' => 'Updated From Panel']],
        ]);

        $tr = $this->api(self::CODE_A1);
        $this->assertSame('Panelden Güncel', $this->productNames($tr)[0]);
        $this->assertSame('250.00', $tr['categories'][0]['products'][0]['price']);
        $this->assertSame('Updated From Panel', $this->productNames($this->api(self::CODE_A1, '?lang=en'))[0]);
    }

    public function testCacheIsInvalidatedByCategoryBranchRestaurantAndAllergenChanges(): void
    {
        $a1 = $this->fx->branchA1;
        $this->fx->productAllergen($this->fx->restaurantA, $this->fx->productA1, $this->fx->allergen['gluten']);
        $this->api(self::CODE_A1);

        // Kategori pasifleştirme
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$a1}/categories/{$this->fx->categoryA1}/toggle");
        $this->assertSame([], $this->api(self::CODE_A1)['categories']);
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$a1}/categories/{$this->fx->categoryA1}/toggle");
        $this->assertCount(1, $this->api(self::CODE_A1)['categories']);

        // Restoran adı (owner)
        $this->asPanelUser($this->fx->ownerA)->post('panel/restaurant', ['translations' => ['tr' => ['name' => 'A Yeni Ad']]]);
        $this->assertSame('A Yeni Ad', $this->api(self::CODE_A1)['restaurant']['name']);

        // Şube bilgisi (owner)
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$a1}", [
            'default_language_id' => (string) $this->fx->lang['tr'], 'is_active' => '1', 'phone' => '0555',
            'translations' => ['tr' => ['name' => 'A1 Yeni']],
        ]);
        $branch = $this->api(self::CODE_A1)['branch'];
        $this->assertSame(['A1 Yeni', '0555'], [$branch['name'], $branch['phone']]);

        // Alerjen (admin) → tüm menü cache'i
        $this->asAdmin()->post('admin/allergens/' . $this->fx->allergen['gluten'], [
            'code' => 'gluten', 'is_active' => '1', 'translations' => ['tr' => ['name' => 'Glüten (yeni)']],
        ]);
        $this->assertSame(['Glüten (yeni)'], array_column($this->api(self::CODE_A1)['allergens'], 'name'));

        // Ürün silme
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$a1}/products/{$this->fx->productA1}/delete");
        $this->assertSame(['Ezogelin A1'], $this->productNames($this->api(self::CODE_A1)));
    }

    public function testRegeneratedCodeInvalidatesOldMenuUrl(): void
    {
        $this->get('m/' . self::CODE_A1)->assertOK();

        $this->asAdmin()->post("admin/restaurants/{$this->fx->restaurantA}/branches/{$this->fx->branchA1}/regenerate-code");
        $newCode = $this->row('branches', $this->fx->branchA1)['public_code'];

        $this->get('m/' . self::CODE_A1)->assertStatus(404);
        $this->get('m/' . $newCode)->assertOK();
    }
}
