<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Exceptions\ValidationException;
use App\Services\CategoryService;
use App\Services\ProductService;
use App\Services\Scope;
use CodeIgniter\HTTP\Files\UploadedFile;
use Tests\Support\DbTestCase;

/**
 * Panel: kategori ve ürün iş kuralları (spec §4.4, §10.1, §12).
 *
 * @internal
 */
final class CategoryProductTest extends DbTestCase
{
    private function base(): string
    {
        return "panel/branches/{$this->fx->branchA1}";
    }

    private function pngUpload(int $width = 800, int $height = 600): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 120, 200));
        $path = tempnam(sys_get_temp_dir(), 'pix');
        imagepng($image, $path);

        return new UploadedFile($path, 'foto.png', 'image/png', (int) filesize($path), UPLOAD_ERR_OK);
    }

    private function actAsOwnerA(): void
    {
        service('tenant')->set($this->fx->restaurantA, (int) $this->fx->ownerA['id'], 'owner', null);
    }

    // ---------------------------------------------------------------- Kategori

    public function testCreateCategoryAppendsToEndAndRequiresBranchDefaultLanguageName(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/categories', [
            'is_active' => '1', 'translations' => ['tr' => ['name' => 'İçecekler', 'description' => 'Soğuk ve sıcak'], 'en' => ['name' => 'Beverages']],
        ]);

        $translation = $this->db->table('category_translations')->where('name', 'İçecekler')->get()->getRowArray();
        $category    = $this->row('categories', (int) $translation['category_id']);
        $this->assertSame(2, (int) $category['sort_order']); // mevcut: 0 ve 1
        $this->assertSame(2, $this->db->table('category_translations')->where('category_id', $category['id'])->countAllResults());

        // Şubenin varsayılan dili (tr) boş → hata
        $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/categories', ['translations' => ['en' => ['name' => 'Only EN']]]);
        $this->assertArrayHasKey('translations.tr.name', $_SESSION['errors'] ?? []);
        $this->dontSeeInDatabase('category_translations', ['name' => 'Only EN']);
    }

    public function testRequiredLanguageFollowsBranchDefaultNotRestaurantDefault(): void
    {
        // A2 şubesinin varsayılan dili en olsun (restoranınki tr).
        $this->db->table('branches')->where('id', $this->fx->branchA2)->update(['default_language_id' => $this->fx->lang['en']]);
        $url = "panel/branches/{$this->fx->branchA2}/categories";

        $this->asPanelUser($this->fx->ownerA)->post($url, ['translations' => ['tr' => ['name' => 'Sadece TR']]]);
        $this->assertArrayHasKey('translations.en.name', $_SESSION['errors'] ?? []);

        $this->asPanelUser($this->fx->ownerA)->post($url, ['translations' => ['en' => ['name' => 'English Only']]]);
        $this->seeInDatabase('category_translations', ['name' => 'English Only']);
    }

    public function testUpdateAndToggleCategory(): void
    {
        $url = $this->base() . '/categories/' . $this->fx->categoryA1;

        $this->asPanelUser($this->fx->ownerA)->post($url, [
            'sort_order' => '9', 'translations' => ['tr' => ['name' => 'Sıcak Çorbalar'], 'en' => ['name' => '']],
        ]);
        $row = $this->row('categories', $this->fx->categoryA1);
        $this->assertSame([9, 0], [(int) $row['sort_order'], (int) $row['is_active']]); // is_active gönderilmedi → pasif
        $this->seeInDatabase('category_translations', ['category_id' => $this->fx->categoryA1, 'name' => 'Sıcak Çorbalar']);
        $this->dontSeeInDatabase('category_translations', ['category_id' => $this->fx->categoryA1, 'language_id' => $this->fx->lang['en']]);

        $this->asPanelUser($this->fx->ownerA)->post($url . '/toggle');
        $this->assertSame(1, (int) $this->row('categories', $this->fx->categoryA1)['is_active']);
    }

    public function testCategoryEmojiIsLimitedToConfiguredListAndCanBeCleared(): void
    {
        $create = $this->base() . '/categories';

        // Listede olmayan değerler reddedilir, kayıt eklenmez.
        foreach (['abc', '💣'] as $invalid) {
            unset($_SESSION['errors']);
            $this->asPanelUser($this->fx->ownerA)->post($create, ['emoji' => $invalid, 'translations' => ['tr' => ['name' => 'Geçersiz ' . $invalid]]]);
            $this->assertArrayHasKey('emoji', $_SESSION['errors'] ?? []);
            $this->dontSeeInDatabase('category_translations', ['name' => 'Geçersiz ' . $invalid]);
        }

        $this->asPanelUser($this->fx->ownerA)->post($create, ['is_active' => '1', 'emoji' => '🍲', 'translations' => ['tr' => ['name' => 'Emojili']]]);
        $id = (int) $this->db->table('category_translations')->where('name', 'Emojili')->get()->getRowArray()['category_id'];
        $this->assertSame('🍲', $this->row('categories', $id)['emoji']);

        // "Emoji yok" → null
        $this->asPanelUser($this->fx->ownerA)->post($create . '/' . $id, ['emoji' => '', 'translations' => ['tr' => ['name' => 'Emojili']]]);
        $this->assertNull($this->row('categories', $id)['emoji']);
    }

    public function testStoredEmojiRemovedFromListIsKeptOnUpdate(): void
    {
        // Listeden sonradan çıkarılmış bir emoji: değişmeden kaydedilebilir, yeniden seçilemez.
        $this->db->table('categories')->where('id', $this->fx->categoryA1)->update(['emoji' => '💣']);
        $url  = $this->base() . '/categories/' . $this->fx->categoryA1;
        $post = ['is_active' => '1', 'emoji' => '💣', 'translations' => ['tr' => ['name' => 'Çorbalar A1']]];

        $this->asPanelUser($this->fx->ownerA)->post($url, $post);
        $this->assertSame('💣', $this->row('categories', $this->fx->categoryA1)['emoji']);

        $this->asPanelUser($this->fx->ownerA)->post($url, ['emoji' => '🍲'] + $post);
        $this->assertSame('🍲', $this->row('categories', $this->fx->categoryA1)['emoji']);

        unset($_SESSION['errors']);
        $this->asPanelUser($this->fx->ownerA)->post($url, ['emoji' => '💣'] + $post);
        $this->assertArrayHasKey('emoji', $_SESSION['errors'] ?? []);
        $this->assertSame('🍲', $this->row('categories', $this->fx->categoryA1)['emoji']);
    }

    /** §4.4: içinde ürün olan kategori silinemez, ürün sayısı bildirilir */
    public function testCategoryWithProductsCannotBeDeleted(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/categories/' . $this->fx->categoryA1 . '/delete');

        $this->assertNull($this->row('categories', $this->fx->categoryA1)['deleted_at']);
        $this->assertStringContainsString('2 ürün', (string) ($_SESSION['error'] ?? ''));
    }

    public function testEmptyCategoryAndCategoryWithOnlyDeletedProductsCanBeDeleted(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/categories/' . $this->fx->categoryA1b . '/delete');
        $this->assertNotNull($this->row('categories', $this->fx->categoryA1b)['deleted_at']);

        // A1 kategorisindeki ürünleri soft-delete et → artık silinebilir.
        $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/products/' . $this->fx->productA1 . '/delete');
        $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/products/' . $this->fx->productA1b . '/delete');
        $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/categories/' . $this->fx->categoryA1 . '/delete');
        $this->assertNotNull($this->row('categories', $this->fx->categoryA1)['deleted_at']);

        // Soft-delete'li kayıtlar panelde görünmez.
        $list = $this->asPanelUser($this->fx->ownerA)->get($this->base() . '/categories');
        $list->assertDontSee('Çorbalar A1');
        $this->asPanelUser($this->fx->ownerA)->get($this->base() . '/categories/' . $this->fx->categoryA1 . '/edit')->assertStatus(404);
    }

    // -------------------------------------------------------------------- Ürün

    public function testCreateProductWithTranslationsAndAllergensInOneGo(): void
    {
        $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/products', [
            'category_id'  => (string) $this->fx->categoryA1b,
            'price'        => '129,90',
            'is_active'    => '1',
            'translations' => ['tr' => ['name' => 'Sütlaç', 'description' => 'Fırında'], 'en' => ['name' => 'Rice Pudding']],
            'allergens'    => [
                (string) $this->fx->allergen['milk']   => 'contains',
                (string) $this->fx->allergen['gluten'] => 'may_contain',
            ],
        ]);

        $translation = $this->db->table('product_translations')->where('name', 'Sütlaç')->get()->getRowArray();
        $this->assertNotNull($translation);
        $product = $this->row('products', (int) $translation['product_id']);
        $this->assertSame('129.90', $product['price']);
        $this->assertSame($this->fx->categoryA1b, (int) $product['category_id']);
        $this->assertSame(0, (int) $product['sort_order']);

        $allergens = array_column(
            $this->db->table('product_allergens')->where('product_id', $product['id'])->get()->getResultArray(),
            'level',
            'allergen_id',
        );
        $this->assertSame('contains', $allergens[$this->fx->allergen['milk']]);
        $this->assertSame('may_contain', $allergens[$this->fx->allergen['gluten']]);
        $this->seeInDatabase('product_allergens', ['product_id' => $product['id'], 'restaurant_id' => $this->fx->restaurantA]);
    }

    public function testPriceNormalizationAndValidation(): void
    {
        $this->assertSame('129.90', ProductService::normalizePrice('129,90'));
        $this->assertSame('1299.90', ProductService::normalizePrice('1.299,90'));
        $this->assertSame('45.00', ProductService::normalizePrice(' 45 '));
        $this->assertSame('0.00', ProductService::normalizePrice('0'));
        $this->assertSame('12.50', ProductService::normalizePrice('12.5'));

        foreach (['', '-5', 'abc', '12,345', '1e5', '10.999', '999999999'] as $invalid) {
            $this->assertNull(ProductService::normalizePrice($invalid), "'{$invalid}' reddedilmeliydi");
        }

        foreach (['-1', 'bedava', ''] as $price) {
            $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/products', [
                'category_id' => (string) $this->fx->categoryA1, 'price' => $price, 'translations' => ['tr' => ['name' => 'Fiyatsız']],
            ]);
            $this->assertArrayHasKey('price', $_SESSION['errors'] ?? []);
        }

        $this->dontSeeInDatabase('product_translations', ['name' => 'Fiyatsız']);
    }

    public function testAllergenSyncRemovesUnsentAndRejectsInactiveOrUnknown(): void
    {
        $url = $this->base() . '/products/' . $this->fx->productA1;
        $this->fx->productAllergen($this->fx->restaurantA, $this->fx->productA1, $this->fx->allergen['gluten']);
        $post = ['category_id' => (string) $this->fx->categoryA1, 'price' => '100', 'is_active' => '1', 'translations' => ['tr' => ['name' => 'Mercimek A1']]];

        // Pasif alerjen (lupin) kabul edilmez; hiçbir şey değişmez.
        $this->asPanelUser($this->fx->ownerA)->post($url, $post + ['allergens' => [(string) $this->fx->allergen['lupin'] => 'contains']]);
        $this->assertArrayHasKey('allergens', $_SESSION['errors'] ?? []);

        // Var olmayan alerjen / geçersiz düzey
        $this->asPanelUser($this->fx->ownerA)->post($url, $post + ['allergens' => ['99999' => 'contains']]);
        $this->assertArrayHasKey('allergens', $_SESSION['errors'] ?? []);
        $this->asPanelUser($this->fx->ownerA)->post($url, $post + ['allergens' => [(string) $this->fx->allergen['milk'] => 'lots']]);
        $this->assertArrayHasKey('allergens', $_SESSION['errors'] ?? []);

        $this->assertSame(1, $this->db->table('product_allergens')->where('product_id', $this->fx->productA1)->countAllResults());

        // gluten gönderilmedi → silinir; milk eklenir; boş değer = seçilmemiş.
        $this->asPanelUser($this->fx->ownerA)->post($url, $post + ['allergens' => [
            (string) $this->fx->allergen['milk'] => 'may_contain', (string) $this->fx->allergen['gluten'] => '',
        ]]);
        $rows = $this->db->table('product_allergens')->where('product_id', $this->fx->productA1)->get()->getResultArray();
        $this->assertCount(1, $rows);
        $this->assertSame([$this->fx->allergen['milk'], 'may_contain'], [(int) $rows[0]['allergen_id'], $rows[0]['level']]);

        // Hiç alerjen gönderilmezse hepsi silinir.
        $this->asPanelUser($this->fx->ownerA)->post($url, $post);
        $this->assertSame(0, $this->db->table('product_allergens')->where('product_id', $this->fx->productA1)->countAllResults());
    }

    public function testFailedProductWriteLeavesNoPartialData(): void
    {
        // Çeviri hatası → ürün, çeviri ve alerjen yazılmamalı (tek transaction / ön doğrulama).
        $this->asPanelUser($this->fx->ownerA)->post($this->base() . '/products', [
            'category_id' => (string) $this->fx->categoryA1, 'price' => '10',
            'translations' => ['tr' => ['name' => str_repeat('x', 200)]],
            'allergens' => [(string) $this->fx->allergen['milk'] => 'contains'],
        ]);

        $this->assertSame(4, $this->db->table('products')->countAllResults());
        $this->assertSame(0, $this->db->table('product_allergens')->countAllResults());
    }

    public function testToggleAndSoftDeleteProduct(): void
    {
        $url = $this->base() . '/products/' . $this->fx->productA1;

        $this->asPanelUser($this->fx->ownerA)->post($url . '/toggle');
        $this->assertSame(0, (int) $this->row('products', $this->fx->productA1)['is_active']);

        $this->asPanelUser($this->fx->ownerA)->post($url . '/delete');
        $this->assertNotNull($this->row('products', $this->fx->productA1)['deleted_at']);

        $this->asPanelUser($this->fx->ownerA)->get($url . '/edit')->assertStatus(404);
        $this->asPanelUser($this->fx->ownerA)->get($this->base() . '/products')->assertDontSee('Mercimek A1');
    }

    public function testProductListFiltersByPriceRange(): void
    {
        // Fixture: Mercimek A1 = 100.00, Ezogelin A1 = 110.00
        $url = $this->base() . '/products';

        $min = $this->asPanelUser($this->fx->ownerA)->get($url . '?min_price=105');
        $min->assertOK();
        $min->assertSee('Ezogelin A1');
        $min->assertDontSee('Mercimek A1');

        $max = $this->asPanelUser($this->fx->ownerA)->get($url . '?max_price=100,50');
        $max->assertSee('Mercimek A1');
        $max->assertDontSee('Ezogelin A1');

        // min > max ise yer değiştirir
        $swapped = $this->asPanelUser($this->fx->ownerA)->get($url . '?min_price=110&max_price=100');
        $swapped->assertSee('Mercimek A1');
        $swapped->assertSee('Ezogelin A1');

        // Geçersiz değer yok sayılır
        $invalid = $this->asPanelUser($this->fx->ownerA)->get($url . '?min_price=abc&max_price=-5');
        $invalid->assertOK();
        $invalid->assertSee('Mercimek A1');
        $invalid->assertSee('Ezogelin A1');
    }

    // ------------------------------------------------------------- Görseller

    public function testProductImageIsStoredUnderTenantDirectoryReplacedAndDeleted(): void
    {
        $this->actAsOwnerA();
        $service = new ProductService(Scope::tenant());
        $branch  = $this->row('branches', $this->fx->branchA1);
        $images  = service('images');
        $data    = ['category_id' => $this->fx->categoryA1, 'price' => '10', 'is_active' => 1, 'sort_order' => null];

        $id      = $service->create($branch, $data, ['tr' => ['name' => 'Görselli']], null, $this->pngUpload());
        $product = $this->row('products', $id);

        $this->assertStringStartsWith("uploads/r/{$this->fx->restaurantA}/products/", $product['image_path']);
        $this->assertTrue($images->exists($product['image_path']));
        $this->assertTrue($images->exists($product['thumb_path']));

        // Görsel değişince eski dosyalar silinir.
        $service->update($branch, $product, $data, ['tr' => ['name' => 'Görselli']], null, $this->pngUpload(400, 400));
        $updated = $this->row('products', $id);
        $this->assertNotSame($product['image_path'], $updated['image_path']);
        $this->assertFalse($images->exists($product['image_path']));
        $this->assertFalse($images->exists($product['thumb_path']));
        $this->assertTrue($images->exists($updated['image_path']));

        // Soft delete'te dosyalar kalır.
        $service->delete($updated);
        $this->assertTrue($images->exists($updated['image_path']));
    }

    public function testDeleteImageEndpointUsesPathFromDatabaseOnly(): void
    {
        $this->actAsOwnerA();
        $branch = $this->row('branches', $this->fx->branchA1);
        $id     = (new ProductService(Scope::tenant()))->create(
            $branch,
            ['category_id' => $this->fx->categoryA1, 'price' => '10', 'is_active' => 1, 'sort_order' => null],
            ['tr' => ['name' => 'Görselli']],
            null,
            $this->pngUpload(),
        );
        $product = $this->row('products', $id);
        $images  = service('images');

        // Request'te gelen "path" yok sayılır; hedef dışı bir dosya asla silinmez.
        $decoy = $this->uploadRoot . 'uploads/r/' . $this->fx->restaurantB . '/products';
        mkdir($decoy, 0777, true);
        file_put_contents($decoy . '/victim.webp', 'x');

        $this->asPanelUser($this->fx->ownerA)->post($this->base() . "/products/{$id}/image/delete", [
            'path' => 'uploads/r/' . $this->fx->restaurantB . '/products/victim.webp', 'image_path' => '../../.env',
        ]);

        $after = $this->row('products', $id);
        $this->assertNull($after['image_path']);
        $this->assertNull($after['thumb_path']);
        $this->assertFalse($images->exists($product['image_path']));
        $this->assertFileExists($decoy . '/victim.webp');
    }

    public function testImageIsRemovedFromDiskWhenTransactionFails(): void
    {
        $this->actAsOwnerA();
        $branch = $this->row('branches', $this->fx->branchA1);

        try {
            // Uygulama doğrulaması geçer; DB hatası üretmek için kolon geçici olarak daraltılır.
            $service = new CategoryService(Scope::tenant());
            $this->db->query('ALTER TABLE category_translations MODIFY name VARCHAR(5) NOT NULL');
            $service->create($branch, ['is_active' => 1, 'sort_order' => null], ['tr' => ['name' => 'Çok uzun bir kategori adı']], $this->pngUpload());
            $this->fail('DB hatası bekleniyordu');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(ValidationException::class, $e);
        } finally {
            $this->db->query('ALTER TABLE category_translations MODIFY name VARCHAR(150) NOT NULL');
        }

        $dir   = $this->uploadRoot . 'uploads/r/' . $this->fx->restaurantA . '/categories';
        $files = is_dir($dir) ? array_diff(scandir($dir) ?: [], ['.', '..']) : [];
        $this->assertSame([], array_values($files), 'Commit olmayan transaction’ın dosyası diskte kalmamalı');
        $this->assertSame(3, $this->db->table('categories')->where('restaurant_id', $this->fx->restaurantA)->countAllResults());
    }
}
