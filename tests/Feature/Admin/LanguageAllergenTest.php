<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\DbTestCase;

/**
 * Admin: dil ve alerjen yönetimi (spec §4.4, §8.5, §10.1).
 *
 * @internal
 */
final class LanguageAllergenTest extends DbTestCase
{
    // ---------------------------------------------------------------- Diller

    public function testLanguageListAndForms(): void
    {
        $this->asAdmin()->get('admin/languages')->assertOK();
        $this->asAdmin()->get('admin/languages/new')->assertOK();
        $this->asAdmin()->get('admin/languages/' . $this->fx->lang['tr'] . '/edit')->assertOK();
        $this->asAdmin()->get('admin/languages/99999/edit')->assertStatus(404);
    }

    public function testCreateLanguage(): void
    {
        $response = $this->asAdmin()->post('admin/languages', [
            'code' => 'pt-BR', 'name' => 'Portuguese (Brazil)', 'native_name' => 'Português', 'direction' => 'ltr', 'is_active' => '1',
        ]);

        $this->assertRedirectsTo($response, 'admin/languages');
        $this->seeInDatabase('languages', ['code' => 'pt-BR', 'native_name' => 'Português', 'is_active' => 1]);
    }

    public function testCreateLanguageRejectsInvalidAndDuplicateCodes(): void
    {
        foreach (['TR', 'turkish', 'tr_TR', 'tr'] as $code) {
            $this->asAdmin()->post('admin/languages', [
                'code' => $code, 'name' => 'X', 'native_name' => 'X', 'direction' => 'ltr',
            ]);
            $this->assertArrayHasKey('code', $_SESSION['errors'] ?? [], "{$code} reddedilmeliydi");
        }

        $this->assertSame(3, $this->db->table('languages')->countAllResults());
    }

    public function testLanguageCodeCannotBeChangedAfterCreation(): void
    {
        $this->asAdmin()->post('admin/languages/' . $this->fx->lang['de'], [
            'code' => 'xx', 'name' => 'Deutsch2', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'is_active' => '1',
        ]);

        $row = $this->row('languages', $this->fx->lang['de']);
        $this->assertSame('de', $row['code']);
        $this->assertSame('Deutsch2', $row['name']);
    }

    public function testLanguageUsedAsDefaultCannotBeDeactivated(): void
    {
        // tr, restoranların ve şubelerin varsayılan dili.
        $this->asAdmin()->post('admin/languages/' . $this->fx->lang['tr'] . '/toggle');
        $this->assertSame(1, (int) $this->row('languages', $this->fx->lang['tr'])['is_active']);
        $this->assertNotEmpty($_SESSION['error'] ?? null);

        // Güncelleme formuyla da pasifleştirilemez.
        $this->asAdmin()->post('admin/languages/' . $this->fx->lang['tr'], [
            'name' => 'Turkish', 'native_name' => 'Türkçe', 'direction' => 'ltr',
        ]);
        $this->assertSame(1, (int) $this->row('languages', $this->fx->lang['tr'])['is_active']);

        // en varsayılan değil → pasifleştirilebilir.
        $this->asAdmin()->post('admin/languages/' . $this->fx->lang['en'] . '/toggle');
        $this->assertSame(0, (int) $this->row('languages', $this->fx->lang['en'])['is_active']);
    }

    public function testReferencedLanguageCannotBeDeletedButUnusedCan(): void
    {
        $this->asAdmin()->post('admin/languages/' . $this->fx->lang['en'] . '/delete');
        $this->assertNotNull($this->row('languages', $this->fx->lang['en']));
        $this->assertNotEmpty($_SESSION['error'] ?? null);

        // de hiçbir yerde kullanılmıyor.
        $this->asAdmin()->post('admin/languages/' . $this->fx->lang['de'] . '/delete');
        $this->assertNull($this->row('languages', $this->fx->lang['de']));
    }

    public function testReorderLanguages(): void
    {
        $ids = [$this->fx->lang['de'], $this->fx->lang['tr'], $this->fx->lang['en']];

        $this->asAdmin()->postJson('admin/languages/reorder', ['ids' => $ids])->assertOK();

        $this->assertSame(0, (int) $this->row('languages', $this->fx->lang['de'])['sort_order']);
        $this->assertSame(1, (int) $this->row('languages', $this->fx->lang['tr'])['sort_order']);
        $this->assertSame(2, (int) $this->row('languages', $this->fx->lang['en'])['sort_order']);

        $this->asAdmin()->postJson('admin/languages/reorder', ['ids' => [99999, $this->fx->lang['tr']]])->assertStatus(422);
        $this->asAdmin()->postJson('admin/languages/reorder', ['ids' => 'x'])->assertStatus(422);
        $this->assertSame(1, (int) $this->row('languages', $this->fx->lang['tr'])['sort_order']);
    }

    // -------------------------------------------------------------- Alerjenler

    public function testAllergenListAndForms(): void
    {
        $list = $this->asAdmin()->get('admin/allergens');
        $list->assertOK();
        $list->assertSee('Gluten');
        $this->asAdmin()->get('admin/allergens/new')->assertOK();
        $this->asAdmin()->get('admin/allergens/' . $this->fx->allergen['milk'] . '/edit')->assertOK();
        $this->asAdmin()->get('admin/allergens/99999/edit')->assertStatus(404);
    }

    public function testCreateAllergenWithTranslations(): void
    {
        $response = $this->asAdmin()->post('admin/allergens', [
            'code'         => 'sesame',
            'is_active'    => '1',
            'sort_order'   => '7',
            'translations' => [
                'tr' => ['name' => 'Susam', 'description' => 'Susam ve ürünleri'],
                'en' => ['name' => 'Sesame'],
                'xx' => ['name' => 'yok sayılmalı'],
            ],
        ]);

        $this->assertRedirectsTo($response, 'admin/allergens');
        $row = $this->db->table('allergens')->where('code', 'sesame')->get()->getRowArray();
        $this->assertSame(7, (int) $row['sort_order']);
        $this->assertSame(2, $this->db->table('allergen_translations')->where('allergen_id', $row['id'])->countAllResults());
    }

    public function testAllergenRequiresTurkishNameAndUniqueValidCode(): void
    {
        $this->asAdmin()->post('admin/allergens', ['code' => 'sesame', 'translations' => ['en' => ['name' => 'Sesame']]]);
        $this->assertArrayHasKey('translations.tr.name', $_SESSION['errors'] ?? []);

        $this->asAdmin()->post('admin/allergens', ['code' => 'gluten', 'translations' => ['tr' => ['name' => 'Gluten 2']]]);
        $this->assertArrayHasKey('code', $_SESSION['errors'] ?? []);

        $this->asAdmin()->post('admin/allergens', ['code' => 'Bad Code!', 'translations' => ['tr' => ['name' => 'X']]]);
        $this->assertArrayHasKey('code', $_SESSION['errors'] ?? []);

        $this->assertSame(3, $this->db->table('allergens')->countAllResults());
    }

    public function testUpdateAndToggleAllergen(): void
    {
        $id = $this->fx->allergen['milk'];

        $this->asAdmin()->post('admin/allergens/' . $id, [
            'code' => 'milk', 'is_active' => '1', 'sort_order' => '3',
            'translations' => ['tr' => ['name' => 'Süt ve süt ürünleri'], 'en' => ['name' => '']],
        ]);

        $names = array_column(
            $this->db->table('allergen_translations')->where('allergen_id', $id)->get()->getResultArray(),
            'name',
        );
        $this->assertSame(['Süt ve süt ürünleri'], $names); // en satırı silindi

        $this->asAdmin()->post('admin/allergens/' . $id . '/toggle');
        $this->assertSame(0, (int) $this->row('allergens', $id)['is_active']);
    }

    public function testAllergenUsedInProductCannotBeDeleted(): void
    {
        $this->fx->productAllergen($this->fx->restaurantA, $this->fx->productA1, $this->fx->allergen['gluten']);

        $this->asAdmin()->post('admin/allergens/' . $this->fx->allergen['gluten'] . '/delete');
        $this->assertNotNull($this->row('allergens', $this->fx->allergen['gluten']));
        $this->assertNotEmpty($_SESSION['error'] ?? null);

        $this->asAdmin()->post('admin/allergens/' . $this->fx->allergen['milk'] . '/delete');
        $this->assertNull($this->row('allergens', $this->fx->allergen['milk']));
        $this->assertSame(0, $this->db->table('allergen_translations')->where('allergen_id', $this->fx->allergen['milk'])->countAllResults());
    }

    public function testReorderAllergens(): void
    {
        $ids = [$this->fx->allergen['lupin'], $this->fx->allergen['gluten'], $this->fx->allergen['milk']];

        $this->asAdmin()->postJson('admin/allergens/reorder', ['ids' => $ids])->assertOK();

        $this->assertSame(0, (int) $this->row('allergens', $this->fx->allergen['lupin'])['sort_order']);
        $this->assertSame(2, (int) $this->row('allergens', $this->fx->allergen['milk'])['sort_order']);
    }

    public function testAdminAreaRequiresAdminSession(): void
    {
        $this->assertRedirectsTo($this->get('admin/languages'), 'admin/login');
        $this->assertRedirectsTo($this->post('admin/allergens', ['code' => 'x']), 'admin/login');
        $this->assertSame(3, $this->db->table('allergens')->countAllResults());
    }
}
