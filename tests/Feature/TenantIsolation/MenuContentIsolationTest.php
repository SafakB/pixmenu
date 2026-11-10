<?php

declare(strict_types=1);

namespace Tests\Feature\TenantIsolation;

use Tests\Support\DbTestCase;

/**
 * Kategori ve ürün uçlarında tenant izolasyonu (spec §14.1 — Test 1–6, 8, 10).
 *
 * @internal
 */
final class MenuContentIsolationTest extends DbTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function productPost(int $categoryId, string $name = 'Güncel Ad', string $price = '55,50'): array
    {
        return [
            'category_id'  => (string) $categoryId,
            'price'        => $price,
            'is_active'    => '1',
            'translations' => ['tr' => ['name' => $name]],
        ];
    }

    /**
     * @return array<string, array<string, mixed>|null> B'nin izlenen kayıtlarının anlık görüntüsü
     */
    private function snapshotB(): array
    {
        return [
            'branch'       => $this->row('branches', $this->fx->branchB1),
            'category'     => $this->row('categories', $this->fx->categoryB1),
            'product'      => $this->row('products', $this->fx->productB1),
            'translations' => $this->db->table('product_translations')->where('product_id', $this->fx->productB1)->orderBy('id')->get()->getResultArray()
                + ['c' => $this->db->table('category_translations')->where('category_id', $this->fx->categoryB1)->orderBy('id')->get()->getResultArray()],
            'allergens' => $this->db->table('product_allergens')->where('product_id', $this->fx->productB1)->get()->getResultArray(),
        ];
    }

    // ------------------------------------------------------------------ Test 1

    /** Test 1 */
    public function testOwnerCannotOpenAnotherTenantsEditPages(): void
    {
        $b1 = $this->fx->branchB1;

        foreach ([
            "panel/branches/{$b1}/categories",
            "panel/branches/{$b1}/categories/new",
            "panel/branches/{$b1}/categories/{$this->fx->categoryB1}/edit",
            "panel/branches/{$b1}/products",
            "panel/branches/{$b1}/products/new",
            "panel/branches/{$b1}/products/{$this->fx->productB1}/edit",
        ] as $path) {
            $response = $this->asPanelUser($this->fx->ownerA)->get($path);
            $response->assertStatus(404);
            $response->assertDontSee('Gizli');
        }

        // Kendi kayıtları açılır.
        $a1 = $this->fx->branchA1;
        $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/categories/{$this->fx->categoryA1}/edit")->assertOK();
        $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/products/{$this->fx->productA1}/edit")->assertOK();
    }

    public function testForeignRecordAndMissingRecordAreIndistinguishable(): void
    {
        $foreign = $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$this->fx->branchB1}/products/{$this->fx->productB1}/edit");
        $missing = $this->asPanelUser($this->fx->ownerA)->get('panel/branches/999999/products/999999/edit');

        $this->assertSame(404, $foreign->response()->getStatusCode());
        $this->assertSame(404, $missing->response()->getStatusCode());
        // CI4 debug modunun eklediği sayaçlı "DEBUG-VIEW" yorumları karşılaştırma dışı.
        $strip = static fn (?string $body): string => (string) preg_replace('/<!-- DEBUG-VIEW.*?-->\s*/', '', (string) $body);

        $this->assertSame($strip($missing->response()->getBody()), $strip($foreign->response()->getBody()));
        $this->assertSame($missing->response()->getHeaderLine('Content-Type'), $foreign->response()->getHeaderLine('Content-Type'));
    }

    // ------------------------------------------------------------------ Test 2

    /** Test 2 */
    public function testOwnerCannotUpdateDeleteOrToggleAnotherTenantsProduct(): void
    {
        $before = $this->snapshotB();
        $base   = "panel/branches/{$this->fx->branchB1}/products/{$this->fx->productB1}";

        $this->asPanelUser($this->fx->ownerA)->post($base, $this->productPost($this->fx->categoryB1, 'Hacked', '1'))->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($base . '/toggle')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($base . '/delete')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($base . '/image/delete')->assertStatus(404);

        $this->assertSame($before, $this->snapshotB());
    }

    /** Test 2 (kategori) */
    public function testOwnerCannotUpdateDeleteOrToggleAnotherTenantsCategory(): void
    {
        $before = $this->snapshotB();
        $base   = "panel/branches/{$this->fx->branchB1}/categories/{$this->fx->categoryB1}";

        $this->asPanelUser($this->fx->ownerA)->post($base, ['is_active' => '1', 'translations' => ['tr' => ['name' => 'Hacked']]])->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($base . '/toggle')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($base . '/delete')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchB1}/categories", [
            'translations' => ['tr' => ['name' => 'B içine sızan kategori']],
        ])->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchB1}/products", $this->productPost($this->fx->categoryB1, 'B içine sızan ürün'))
            ->assertStatus(404);

        $this->assertSame($before, $this->snapshotB());
        $this->assertSame(1, $this->db->table('categories')->where('restaurant_id', $this->fx->restaurantB)->countAllResults());
        $this->assertSame(1, $this->db->table('products')->where('restaurant_id', $this->fx->restaurantB)->countAllResults());
    }

    // ------------------------------------------------------------------ Test 3

    /** Test 3 */
    public function testForeignProductIdUnderOwnBranchUrlIs404(): void
    {
        $before = $this->snapshotB();
        $base   = "panel/branches/{$this->fx->branchA1}/products/{$this->fx->productB1}";

        $this->asPanelUser($this->fx->ownerA)->get($base . '/edit')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($base, $this->productPost($this->fx->categoryA1, 'Hacked'))->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($base . '/toggle')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($base . '/delete')->assertStatus(404);

        // Kategori için de aynısı.
        $catBase = "panel/branches/{$this->fx->branchA1}/categories/{$this->fx->categoryB1}";
        $this->asPanelUser($this->fx->ownerA)->get($catBase . '/edit')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($catBase . '/delete')->assertStatus(404);

        $this->assertSame($before, $this->snapshotB());
    }

    /** Test 3 (aynı tenant, yanlış şube): A2'nin ürünü A1'in URL'si altında kullanılamaz */
    public function testProductOfAnotherBranchInSameTenantIs404UnderWrongBranchUrl(): void
    {
        $base = "panel/branches/{$this->fx->branchA1}/products/{$this->fx->productA2}";

        $this->asPanelUser($this->fx->ownerA)->get($base . '/edit')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post($base . '/delete')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchA1}/categories/{$this->fx->categoryA2}/toggle")->assertStatus(404);

        $this->assertNull($this->row('products', $this->fx->productA2)['deleted_at']);
        $this->assertSame(1, (int) $this->row('categories', $this->fx->categoryA2)['is_active']);
    }

    // ------------------------------------------------------------------ Test 4

    /** Test 4 */
    public function testOwnerCannotMoveOwnProductIntoAnotherTenantsCategory(): void
    {
        $before = $this->row('products', $this->fx->productA1);

        $response = $this->asPanelUser($this->fx->ownerA)->post(
            "panel/branches/{$this->fx->branchA1}/products/{$this->fx->productA1}",
            $this->productPost($this->fx->categoryB1, 'Taşındı'),
        );

        $response->assertRedirect();
        $this->assertArrayHasKey('category_id', $_SESSION['errors'] ?? []);
        $this->assertSame($before, $this->row('products', $this->fx->productA1));
        $this->dontSeeInDatabase('product_translations', ['name' => 'Taşındı']);
    }

    /** Test 4 (JSON → 422) */
    public function testForeignCategoryIdReturns422ForJsonClients(): void
    {
        $response = $this->asPanelUser($this->fx->ownerA)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("panel/branches/{$this->fx->branchA1}/products", $this->productPost($this->fx->categoryB1, 'Yeni'));

        $response->assertStatus(422);
        $body = json_decode((string) $response->getJSON(), true);
        $this->assertSame('error', $body['status']);
        $this->assertArrayHasKey('category_id', $body['errors']);
        $this->dontSeeInDatabase('product_translations', ['name' => 'Yeni']);
    }

    /** Test 4 (aynı tenant, başka şubenin kategorisi) */
    public function testProductCannotUseCategoryOfAnotherBranchInSameTenant(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post(
            "panel/branches/{$this->fx->branchA1}/products",
            $this->productPost($this->fx->categoryA2, 'Yanlış Şube'),
        );

        $this->assertArrayHasKey('category_id', $_SESSION['errors'] ?? []);
        $this->dontSeeInDatabase('product_translations', ['name' => 'Yanlış Şube']);
    }

    public function testSoftDeletedCategoryCannotBeUsed(): void
    {
        $this->db->table('categories')->where('id', $this->fx->categoryA1b)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $this->asPanelUser($this->fx->ownerA)->post(
            "panel/branches/{$this->fx->branchA1}/products",
            $this->productPost($this->fx->categoryA1b, 'Silinmiş Kategoride'),
        );

        $this->assertArrayHasKey('category_id', $_SESSION['errors'] ?? []);
    }

    // ------------------------------------------------------------------ Test 5

    /** Test 5 */
    public function testRestaurantIdInBodyIsIgnoredOnCreate(): void
    {
        $post = $this->productPost($this->fx->categoryA1, 'Body ile Sızma') + [
            'restaurant_id' => (string) $this->fx->restaurantB,
            'branch_id'     => (string) $this->fx->branchB1,
        ];

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchA1}/products?restaurant_id={$this->fx->restaurantB}", $post);

        $translation = $this->db->table('product_translations')->where('name', 'Body ile Sızma')->get()->getRowArray();
        $this->assertNotNull($translation);
        $product = $this->row('products', (int) $translation['product_id']);
        $this->assertSame($this->fx->restaurantA, (int) $product['restaurant_id']);
        $this->assertSame($this->fx->branchA1, (int) $product['branch_id']);
        $this->assertSame('55.50', $product['price']);

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchA1}/categories", [
            'restaurant_id' => (string) $this->fx->restaurantB, 'branch_id' => (string) $this->fx->branchB1,
            'is_active' => '1', 'translations' => ['tr' => ['name' => 'Body ile Sızan Kategori']],
        ]);
        $translation = $this->db->table('category_translations')->where('name', 'Body ile Sızan Kategori')->get()->getRowArray();
        $category    = $this->row('categories', (int) $translation['category_id']);
        $this->assertSame([$this->fx->restaurantA, $this->fx->branchA1], [(int) $category['restaurant_id'], (int) $category['branch_id']]);
    }

    /** Test 5 (güncelleme) */
    public function testRestaurantIdInBodyIsIgnoredOnUpdate(): void
    {
        $post = $this->productPost($this->fx->categoryA1, 'Güncellendi') + [
            'restaurant_id' => (string) $this->fx->restaurantB, 'branch_id' => (string) $this->fx->branchB1,
        ];

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchA1}/products/{$this->fx->productA1}", $post);

        $product = $this->row('products', $this->fx->productA1);
        $this->assertSame([$this->fx->restaurantA, $this->fx->branchA1], [(int) $product['restaurant_id'], (int) $product['branch_id']]);
        $this->seeInDatabase('product_translations', ['product_id' => $this->fx->productA1, 'name' => 'Güncellendi']);
    }

    // ------------------------------------------------------------------ Test 6

    /** Test 6 */
    public function testReorderWithForeignProductIdsIsRejectedEntirely(): void
    {
        $response = $this->asPanelUser($this->fx->ownerA)->postJson("panel/branches/{$this->fx->branchA1}/products/reorder", [
            'ids' => [$this->fx->productA1b, $this->fx->productB1, $this->fx->productA1],
        ]);

        $response->assertStatus(422);
        $this->assertSame('error', json_decode((string) $response->getJSON(), true)['status']);

        $this->assertSame(0, (int) $this->row('products', $this->fx->productA1)['sort_order']);
        $this->assertSame(1, (int) $this->row('products', $this->fx->productA1b)['sort_order']);
        $this->assertSame(0, (int) $this->row('products', $this->fx->productB1)['sort_order']);
    }

    /** Test 6 (kategori + aynı tenant başka şube) */
    public function testReorderWithForeignCategoryIdsOrOtherBranchIdsIsRejectedEntirely(): void
    {
        $url = "panel/branches/{$this->fx->branchA1}/categories/reorder";

        $this->asPanelUser($this->fx->ownerA)->postJson($url, ['ids' => [$this->fx->categoryA1b, $this->fx->categoryB1, $this->fx->categoryA1]])->assertStatus(422);
        $this->asPanelUser($this->fx->ownerA)->postJson($url, ['ids' => [$this->fx->categoryA1b, $this->fx->categoryA2, $this->fx->categoryA1]])->assertStatus(422);
        $this->asPanelUser($this->fx->ownerA)->postJson("panel/branches/{$this->fx->branchB1}/categories/reorder", ['ids' => [$this->fx->categoryB1]])->assertStatus(404);

        $this->assertSame(0, (int) $this->row('categories', $this->fx->categoryA1)['sort_order']);
        $this->assertSame(1, (int) $this->row('categories', $this->fx->categoryA1b)['sort_order']);
        $this->assertSame(0, (int) $this->row('categories', $this->fx->categoryB1)['sort_order']);
    }

    public function testReorderOwnRecordsWorks(): void
    {
        $this->asPanelUser($this->fx->ownerA)->postJson("panel/branches/{$this->fx->branchA1}/products/reorder", [
            'ids' => [$this->fx->productA1b, $this->fx->productA1],
        ])->assertOK();
        $this->assertSame(0, (int) $this->row('products', $this->fx->productA1b)['sort_order']);
        $this->assertSame(1, (int) $this->row('products', $this->fx->productA1)['sort_order']);

        $this->asPanelUser($this->fx->ownerA)->postJson("panel/branches/{$this->fx->branchA1}/categories/reorder", [
            'ids' => [$this->fx->categoryA1b, $this->fx->categoryA1],
        ])->assertOK();
        $this->assertSame(0, (int) $this->row('categories', $this->fx->categoryA1b)['sort_order']);
    }

    // ------------------------------------------------------------------ Test 8

    /** Test 8 */
    public function testManagerCannotAccessUnassignedBranchOfOwnRestaurant(): void
    {
        $a2      = $this->fx->branchA2;
        $manager = $this->fx->managerA1;

        foreach ([
            "panel/branches/{$a2}/categories", "panel/branches/{$a2}/categories/new",
            "panel/branches/{$a2}/categories/{$this->fx->categoryA2}/edit",
            "panel/branches/{$a2}/products", "panel/branches/{$a2}/products/new",
            "panel/branches/{$a2}/products/{$this->fx->productA2}/edit",
        ] as $path) {
            $this->asPanelUser($manager)->get($path)->assertStatus(404);
        }

        $before = [$this->row('products', $this->fx->productA2), $this->row('categories', $this->fx->categoryA2)];

        $this->asPanelUser($manager)->post("panel/branches/{$a2}/products/{$this->fx->productA2}", $this->productPost($this->fx->categoryA2, 'Hacked'))->assertStatus(404);
        $this->asPanelUser($manager)->post("panel/branches/{$a2}/products/{$this->fx->productA2}/toggle")->assertStatus(404);
        $this->asPanelUser($manager)->post("panel/branches/{$a2}/products/{$this->fx->productA2}/delete")->assertStatus(404);
        $this->asPanelUser($manager)->post("panel/branches/{$a2}/products", $this->productPost($this->fx->categoryA2, 'Yeni'))->assertStatus(404);
        $this->asPanelUser($manager)->post("panel/branches/{$a2}/categories/{$this->fx->categoryA2}/delete")->assertStatus(404);
        $this->asPanelUser($manager)->post("panel/branches/{$a2}/categories", ['translations' => ['tr' => ['name' => 'Yeni']]])->assertStatus(404);
        $this->asPanelUser($manager)->postJson("panel/branches/{$a2}/products/reorder", ['ids' => [$this->fx->productA2]])->assertStatus(404);

        $this->assertSame($before, [$this->row('products', $this->fx->productA2), $this->row('categories', $this->fx->categoryA2)]);
        $this->assertSame(1, $this->db->table('products')->where('branch_id', $a2)->countAllResults());
    }

    /** Test 8 (olumlu taraf): manager atanmış şubesinde çalışabilir */
    public function testManagerCanManageAssignedBranch(): void
    {
        $a1      = $this->fx->branchA1;
        $manager = $this->fx->managerA1;

        $this->asPanelUser($manager)->get("panel/branches/{$a1}/categories")->assertOK();
        $this->asPanelUser($manager)->get("panel/branches/{$a1}/products")->assertOK();
        $this->asPanelUser($manager)->get("panel/branches/{$a1}/products/{$this->fx->productA1}/edit")->assertOK();

        $this->asPanelUser($manager)->post("panel/branches/{$a1}/products/{$this->fx->productA1}", $this->productPost($this->fx->categoryA1b, 'Manager Güncelledi', '75'));
        $row = $this->row('products', $this->fx->productA1);
        $this->assertSame(['75.00', $this->fx->categoryA1b], [$row['price'], (int) $row['category_id']]);

        // Atanmamış şubenin kategorisini kendi şubesindeki ürüne veremez.
        $this->asPanelUser($manager)->post("panel/branches/{$a1}/products/{$this->fx->productA1}", $this->productPost($this->fx->categoryA2, 'Olmaz'));
        $this->assertArrayHasKey('category_id', $_SESSION['errors'] ?? []);
        $this->assertSame($this->fx->categoryA1b, (int) $this->row('products', $this->fx->productA1)['category_id']);
    }

    // ----------------------------------------------------------------- Test 10

    /** Test 10 */
    public function testListsNeverContainAnotherTenantsRecords(): void
    {
        $a1 = $this->fx->branchA1;

        $products = $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/products");
        $products->assertOK();
        $products->assertSee('Mercimek A1');
        $products->assertDontSee('Gizli');
        $products->assertDontSee('Mercimek A2'); // başka şube

        $categories = $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/categories");
        $categories->assertSee('Çorbalar A1');
        $categories->assertDontSee('Gizli');
        $categories->assertDontSee('Çorbalar A2');

        // Ürün formundaki kategori seçenekleri de yalnızca bu şubeye ait.
        $form = $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/products/new");
        $form->assertSee('Çorbalar A1');
        $form->assertDontSee('Gizli');
        $form->assertDontSee('Çorbalar A2');
    }

    /** Test 10 (arama ve filtre) */
    public function testSearchAndCategoryFilterCannotReachOtherTenants(): void
    {
        $a1 = $this->fx->branchA1;

        $search = $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/products?q=Gizli");
        $search->assertOK();
        $search->assertDontSee('Gizli Ürün B');

        $own = $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/products?q=Ezogelin");
        $own->assertSee('Ezogelin A1');
        $own->assertDontSee('Mercimek A1');

        // Başka tenant'ın / başka şubenin kategorisiyle filtre → 404
        $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/products?category={$this->fx->categoryB1}")->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/products?category={$this->fx->categoryA2}")->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$a1}/products?category={$this->fx->categoryA1}")->assertOK();
    }

    public function testSearchTreatsLikeWildcardsLiterally(): void
    {
        $response = $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$this->fx->branchA1}/products?q=%25");

        $response->assertOK();
        $response->assertDontSee('Mercimek A1');
    }

    // ------------------------------------------------------- Çeviri / alerjen

    /** §2.1.7: çeviriler yalnızca doğrulanmış ebeveyn üzerinden yazılır */
    public function testTranslationsAndAllergensOfForeignProductAreNeverWritten(): void
    {
        $before = $this->snapshotB();
        $post   = $this->productPost($this->fx->categoryA1, 'Çeviri Sızması') + [
            'allergens' => [(string) $this->fx->allergen['gluten'] => 'contains'],
        ];

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchA1}/products/{$this->fx->productB1}", $post)->assertStatus(404);

        $this->assertSame($before, $this->snapshotB());
    }

    public function testDisallowedLanguageCodesAreIgnoredForContent(): void
    {
        $post                       = $this->productPost($this->fx->categoryA1, 'Türkçe Ad');
        $post['translations']['de'] = ['name' => 'Restoranda olmayan dil'];

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchA1}/products/{$this->fx->productA1}", $post);

        $this->seeInDatabase('product_translations', ['product_id' => $this->fx->productA1, 'name' => 'Türkçe Ad']);
        $this->dontSeeInDatabase('product_translations', ['product_id' => $this->fx->productA1, 'language_id' => $this->fx->lang['de']]);
    }
}
