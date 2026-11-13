<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\DbTestCase;

/**
 * Admin: menü şablonları ve şubeye şablon atama.
 *
 * @internal
 */
final class MenuTemplateAdminTest extends DbTestCase
{
    public function testListAndForms(): void
    {
        $response = $this->asAdmin()->get('admin/templates');
        $response->assertOK();
        $response->assertSee('Modern');
        $this->asAdmin()->get('admin/templates/new')->assertOK();
        $this->asAdmin()->get('admin/templates/modern/edit')->assertOK();
        $this->asAdmin()->get('admin/templates/yok/edit')->assertStatus(404);
        $this->asAdmin()->get('admin/templates/BAD_CODE/edit')->assertStatus(404);
    }

    public function testCreateTemplate(): void
    {
        $response = $this->asAdmin()->post('admin/templates', [
            'code' => 'klasik-koyu', 'name' => 'Klasik Koyu', 'description' => 'Koyu tema', 'is_active' => '1', 'sort_order' => '4',
        ]);

        $this->assertRedirectsTo($response, 'admin/templates');
        $this->seeInDatabase('menu_templates', ['code' => 'klasik-koyu', 'name' => 'Klasik Koyu', 'is_active' => 1, 'sort_order' => 4]);
    }

    public function testCreateRejectsInvalidReservedAndDuplicateCodes(): void
    {
        foreach (['Modern', 'a_b', '-x', 'new', 'reorder', 'modern', str_repeat('a', 31)] as $code) {
            $this->asAdmin()->post('admin/templates', ['code' => $code, 'name' => 'X']);
            $this->assertArrayHasKey('code', $_SESSION['errors'] ?? [], "{$code} reddedilmeliydi");
        }

        $this->assertSame(3, $this->db->table('menu_templates')->countAllResults());
    }

    public function testCodeCannotBeChanged(): void
    {
        $this->asAdmin()->post('admin/templates/modern', ['code' => 'xx', 'name' => 'Modern 2', 'is_active' => '1']);

        $this->seeInDatabase('menu_templates', ['code' => 'modern', 'name' => 'Modern 2']);
        $this->dontSeeInDatabase('menu_templates', ['code' => 'xx']);
    }

    public function testDefaultTemplateCannotBeDeletedOrDeactivated(): void
    {
        $this->asAdmin()->post('admin/templates/standart/delete')->assertSessionHas('error');
        $this->asAdmin()->post('admin/templates/standart/toggle')->assertSessionHas('error');
        $this->asAdmin()->post('admin/templates/standart', ['name' => 'Standart']); // is_active gönderilmedi

        $this->seeInDatabase('menu_templates', ['code' => 'standart', 'is_active' => 1]);
    }

    public function testTemplateInUseCannotBeDeletedOrDeactivated(): void
    {
        $this->db->table('branches')->where('id', $this->fx->branchA1)->update(['template_code' => 'modern']);

        $this->asAdmin()->post('admin/templates/modern/delete')->assertSessionHas('error');
        $this->asAdmin()->post('admin/templates/modern/toggle')->assertSessionHas('error');

        $this->seeInDatabase('menu_templates', ['code' => 'modern', 'is_active' => 1]);
    }

    public function testUnusedTemplateCanBeToggledAndDeleted(): void
    {
        $this->asAdmin()->post('admin/templates/modern/toggle');
        $this->seeInDatabase('menu_templates', ['code' => 'modern', 'is_active' => 0]);

        $this->asAdmin()->post('admin/templates/modern/delete');
        $this->dontSeeInDatabase('menu_templates', ['code' => 'modern']);
    }

    public function testReorder(): void
    {
        $this->asAdmin()
            ->withBodyFormat('json')
            ->post('admin/templates/reorder', ['ids' => ['eski', 'modern', 'standart']])
            ->assertOK();

        $this->seeInDatabase('menu_templates', ['code' => 'eski', 'sort_order' => 0]);
        $this->seeInDatabase('menu_templates', ['code' => 'standart', 'sort_order' => 2]);

        $this->asAdmin()->withBodyFormat('json')->post('admin/templates/reorder', ['ids' => ['yok']])->assertStatus(422);
    }

    // ------------------------------------------------------------ Şube ataması

    public function testAdminAssignsTemplateToBranch(): void
    {
        $a = $this->fx->restaurantA;

        $response = $this->asAdmin()->get("admin/restaurants/{$a}/branches/{$this->fx->branchA1}/edit");

        $response->assertOK();

        $response->assertSee('name="template_code"', null);

        $this->asAdmin()->post("admin/restaurants/{$a}/branches/{$this->fx->branchA1}", $this->branchPost(['template_code' => 'modern']));

        $this->assertSame('modern', $this->row('branches', $this->fx->branchA1)['template_code']);
    }

    public function testAdminCannotAssignPassiveOrUnknownTemplate(): void
    {
        $a = $this->fx->restaurantA;

        foreach (['eski', 'yok'] as $code) {
            $this->asAdmin()->post("admin/restaurants/{$a}/branches/{$this->fx->branchA1}", $this->branchPost(['template_code' => $code]));
            $this->assertArrayHasKey('template_code', $_SESSION['errors'] ?? [], "{$code} reddedilmeliydi");
        }

        $this->assertSame('standart', $this->row('branches', $this->fx->branchA1)['template_code']);
    }

    public function testBranchKeepsItsPassiveTemplateOnUpdate(): void
    {
        $a = $this->fx->restaurantA;
        $this->db->table('branches')->where('id', $this->fx->branchA1)->update(['template_code' => 'eski']);

        $this->asAdmin()->post("admin/restaurants/{$a}/branches/{$this->fx->branchA1}", $this->branchPost(['template_code' => 'eski', 'phone' => '123']));

        $row = $this->row('branches', $this->fx->branchA1);
        $this->assertSame('eski', $row['template_code']);
        $this->assertSame('123', $row['phone']);
    }

    public function testPanelCannotChangeTemplateAndNewPanelBranchGetsDefault(): void
    {
        $this->db->table('branches')->where('id', $this->fx->branchA1)->update(['template_code' => 'modern']);

        $response = $this->asPanelUser($this->fx->ownerA)->get("panel/branches/{$this->fx->branchA1}/edit");

        $response->assertOK();

        $response->assertDontSee('name="template_code"', null);

        $this->asPanelUser($this->fx->ownerA)->post("panel/branches/{$this->fx->branchA1}", $this->branchPost(['template_code' => 'standart', 'phone' => '555']));

        $row = $this->row('branches', $this->fx->branchA1);
        $this->assertSame('modern', $row['template_code']);
        $this->assertSame('555', $row['phone']);
    }

    // ------------------------------------------------------------ Menü API'si

    public function testMenuApiExposesBranchTemplateCode(): void
    {
        $code = (string) $this->row('branches', $this->fx->branchA1)['public_code'];

        $this->assertSame('standart', $this->apiTemplate($code));

        $this->db->table('branches')->where('id', $this->fx->branchA1)->update(['template_code' => 'modern']);
        cache()->clean();

        $this->assertSame('modern', $this->apiTemplate($code));
    }

    private function apiTemplate(string $publicCode): ?string
    {
        $response = $this->get('api/v1/menu/' . $publicCode);
        $response->assertOK();

        return json_decode((string) $response->getJSON(), true)['data']['branch']['template'] ?? null;
    }

    /**
     * @param array<string, string> $extra
     *
     * @return array<string, mixed>
     */
    private function branchPost(array $extra = []): array
    {
        return $extra + [
            'slug' => 'a1', 'default_language_id' => (string) $this->fx->lang['tr'], 'phone' => '',
            'is_active' => '1', 'sort_order' => '0',
            'translations' => ['tr' => ['name' => 'Şube A1', 'address' => 'Şube A1 adresi']],
        ];
    }
}
