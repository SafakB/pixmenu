<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Models\ProductTranslationModel;
use App\Services\TranslationService;
use Tests\Support\DbTestCase;

/**
 * TranslationService::sync (spec §8.2).
 *
 * @internal
 */
final class TranslationServiceTest extends DbTestCase
{
    private TranslationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TranslationService();
    }

    /**
     * @return array<string, array<string, mixed>> code => satır
     */
    private function translations(int $productId): array
    {
        $rows = $this->db->table('product_translations pt')
            ->select('pt.*, l.code')
            ->join('languages l', 'l.id = pt.language_id')
            ->where('pt.product_id', $productId)
            ->get()->getResultArray();

        return array_column($rows, null, 'code');
    }

    private function sync(array $input, array $allowed = ['tr', 'en'], string $required = 'tr'): void
    {
        $this->service->sync(ProductTranslationModel::class, 'product_id', $this->fx->productA1, $input, $allowed, $required);
    }

    public function testUpdatesExistingAndInsertsNewRows(): void
    {
        $this->db->table('product_translations')
            ->where('product_id', $this->fx->productA1)->where('language_id', $this->fx->lang['en'])->delete();

        $this->sync([
            'tr' => ['name' => '  Yeni Ad  ', 'description' => 'Açıklama'],
            'en' => ['name' => 'New Name', 'description' => ''],
        ]);

        $rows = $this->translations($this->fx->productA1);
        $this->assertSame('Yeni Ad', $rows['tr']['name']);
        $this->assertSame('Açıklama', $rows['tr']['description']);
        $this->assertSame('New Name', $rows['en']['name']);
        $this->assertNull($rows['en']['description']);
        $this->assertCount(2, $rows);
    }

    public function testDisallowedLanguageCodesAreIgnored(): void
    {
        $this->sync([
            'tr' => ['name' => 'Ad'],
            'de' => ['name' => 'Sollte ignoriert werden'],
            'xx' => ['name' => 'yok'],
        ]);

        $this->assertArrayNotHasKey('de', $this->translations($this->fx->productA1));
    }

    public function testRequiredLanguageNameIsMandatory(): void
    {
        try {
            $this->sync(['tr' => ['name' => '   '], 'en' => ['name' => 'Only English']]);
            $this->fail('Zorunlu dil boşken hata bekleniyordu');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('translations.tr.name', $e->errors());
        }

        // Hiçbir şey değişmemiş olmalı.
        $this->assertSame('Mercimek A1', $this->translations($this->fx->productA1)['tr']['name']);
        $this->assertSame('Lentil A1', $this->translations($this->fx->productA1)['en']['name']);
    }

    public function testEmptyNameInOtherLanguageDeletesThatRow(): void
    {
        $this->sync(['tr' => ['name' => 'Ad'], 'en' => ['name' => '', 'description' => 'kalmamalı']]);

        $rows = $this->translations($this->fx->productA1);
        $this->assertArrayNotHasKey('en', $rows);
        $this->assertArrayHasKey('tr', $rows);
    }

    public function testLanguagesMissingFromInputAreLeftUntouched(): void
    {
        $this->sync(['tr' => ['name' => 'Sadece TR gönderildi']]);

        $this->assertSame('Lentil A1', $this->translations($this->fx->productA1)['en']['name']);
    }

    public function testTooLongNameIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->sync(['tr' => ['name' => str_repeat('a', 151)]]);
    }

    public function testLoadReturnsTranslationsKeyedByCode(): void
    {
        $loaded = $this->service->load(ProductTranslationModel::class, $this->fx->productA1);

        $this->assertSame(['name' => 'Mercimek A1', 'description' => null], $loaded['tr']);
        $this->assertSame('Lentil A1', $loaded['en']['name']);
    }
}
