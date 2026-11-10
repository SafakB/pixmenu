<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AllergenTranslationModel;
use App\Models\ProductTranslationModel;
use App\Services\LanguageResolver;
use Tests\Support\DbTestCase;

/**
 * Çeviri fallback zinciri (spec §8.3) ve ziyaretçi dil seçimi (spec §8.4).
 *
 * @internal
 */
final class LanguageResolverTest extends DbTestCase
{
    private LanguageResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new LanguageResolver();
    }

    public function testChainDropsNullsAndDuplicates(): void
    {
        $this->assertSame([3, 1], $this->resolver->chain(3, null, 1, 3, 0));
        $this->assertSame([], $this->resolver->chain(null, null));
    }

    public function testPickFollowsRequestedThenBranchThenRestaurantThenFirstAvailable(): void
    {
        $rows = [
            10 => ['name' => 'on'],
            20 => ['name' => 'yirmi'],
            30 => ['name' => 'otuz'],
        ];

        // 1. istenen dil
        $this->assertSame('yirmi', $this->resolver->pick($rows, [20, 30, 10])['name']);
        // 2. istenen yok → şube varsayılanı
        $this->assertSame('otuz', $this->resolver->pick($rows, [99, 30, 10])['name']);
        // 3. istenen ve şube yok → restoran varsayılanı
        $this->assertSame('on', $this->resolver->pick($rows, [99, 98, 10])['name']);
        // 4. hiçbiri yok → mevcut ilk çeviri
        $this->assertSame('on', $this->resolver->pick($rows, [99, 98, 97])['name']);
        // 5. hiç çeviri yok
        $this->assertNull($this->resolver->pick([], [20]));
    }

    public function testResolveManyUsesRequestedLanguageWhenPresent(): void
    {
        $chain = $this->resolver->chain($this->fx->lang['en'], $this->fx->lang['tr']);

        $resolved = $this->resolver->resolveMany(ProductTranslationModel::class, [$this->fx->productA1], $chain);

        $this->assertSame('Lentil A1', $resolved[$this->fx->productA1]['name']);
    }

    public function testResolveManyFallsBackToBranchDefault(): void
    {
        // Yalnızca tr çevirisi olan ürün; istenen dil en.
        $productId = $this->fx->product($this->fx->restaurantA, $this->fx->branchA1, $this->fx->categoryA1, 'Sadece Türkçe', null, '10.00', 5);
        $chain     = $this->resolver->chain($this->fx->lang['en'], $this->fx->lang['tr']);

        $resolved = $this->resolver->resolveMany(ProductTranslationModel::class, [$productId, $this->fx->productA1], $chain);

        $this->assertSame('Sadece Türkçe', $resolved[$productId]['name']);
        $this->assertSame('Lentil A1', $resolved[$this->fx->productA1]['name']);
    }

    public function testResolveManyFallsBackToRestaurantDefaultThenFirstAvailable(): void
    {
        $productId = $this->fx->product($this->fx->restaurantA, $this->fx->branchA1, $this->fx->categoryA1, 'Türkçe Ad', null, '10.00', 5);

        // İstenen: de, şube varsayılanı: en (yok), restoran varsayılanı: tr (var)
        $chain = $this->resolver->chain($this->fx->lang['de'], $this->fx->lang['en'], $this->fx->lang['tr']);
        $this->assertSame('Türkçe Ad', $this->resolver->resolveOne(ProductTranslationModel::class, $productId, $chain)['name']);

        // Zincirdeki hiçbir dilde yok → mevcut ilk çeviri
        $chain = $this->resolver->chain($this->fx->lang['de'], $this->fx->lang['en']);
        $this->assertSame('Türkçe Ad', $this->resolver->resolveOne(ProductTranslationModel::class, $productId, $chain)['name']);
    }

    public function testResolveManyReturnsNothingForParentsWithoutTranslations(): void
    {
        $this->db->table('product_translations')->where('product_id', $this->fx->productA1)->delete();

        $resolved = $this->resolver->resolveMany(ProductTranslationModel::class, [$this->fx->productA1], [$this->fx->lang['tr']]);

        $this->assertArrayNotHasKey($this->fx->productA1, $resolved);
        $this->assertSame([], $this->resolver->resolveMany(ProductTranslationModel::class, [], [$this->fx->lang['tr']]));
    }

    public function testAllergenChainIsRequestedThenBranchDefaultThenTrThenEn(): void
    {
        $this->assertSame(
            [$this->fx->lang['de'], $this->fx->lang['en'], $this->fx->lang['tr']],
            $this->resolver->allergenChain($this->fx->lang['de'], $this->fx->lang['en']),
        );

        // de çevirisi yok → şube varsayılanı (tr)
        $chain = $this->resolver->allergenChain($this->fx->lang['de'], $this->fx->lang['tr']);
        $name  = $this->resolver->resolveOne(AllergenTranslationModel::class, $this->fx->allergen['milk'], $chain)['name'];
        $this->assertSame('Süt', $name);

        // tr silinirse → en
        $this->db->table('allergen_translations')
            ->where('allergen_id', $this->fx->allergen['milk'])->where('language_id', $this->fx->lang['tr'])->delete();
        $name = $this->resolver->resolveOne(AllergenTranslationModel::class, $this->fx->allergen['milk'], $chain)['name'];
        $this->assertSame('Milk', $name);
    }

    public function testVisitorLanguagePrefersValidQueryThenCookieThenBranchDefault(): void
    {
        $active = [
            ['id' => 1, 'code' => 'tr'],
            ['id' => 2, 'code' => 'en'],
        ];

        $fromQuery = $this->resolver->visitorLanguage($active, 1, 'en', 'tr');
        $this->assertSame('en', $fromQuery['language']['code']);
        $this->assertTrue($fromQuery['persist']);

        $fromCookie = $this->resolver->visitorLanguage($active, 1, null, 'en');
        $this->assertSame('en', $fromCookie['language']['code']);
        $this->assertFalse($fromCookie['persist']);

        // Geçersiz ?lang → çerez; geçersiz çerez → şube varsayılanı
        $this->assertSame('en', $this->resolver->visitorLanguage($active, 1, 'xx', 'en')['language']['code']);
        $this->assertSame('tr', $this->resolver->visitorLanguage($active, 1, 'xx', 'de')['language']['code']);
        $this->assertSame('en', $this->resolver->visitorLanguage($active, 2, null, null)['language']['code']);
    }
}
