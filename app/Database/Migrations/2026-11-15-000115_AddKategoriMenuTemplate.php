<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * İkinci menü şablonu: `kategori` — önce kategori kartları, tıklanınca o kategorinin ürünleri.
 * Tasarım menu-ui/src/templates/kategori altındadır; `standart` tek sayfa düzenidir.
 *
 * Kod admin panelinden önceden eklenmişse dokunulmaz (idempotent).
 */
final class AddKategoriMenuTemplate extends BaseMigration
{
    private const CODE = 'kategori';

    public function up(): void
    {
        if ($this->db->table('menu_templates')->where('code', self::CODE)->countAllResults() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('menu_templates')->insert([
            'code'        => self::CODE,
            'name'        => 'Kategori kartları',
            'description' => 'Önce kategoriler kart olarak listelenir; tıklanınca o kategorinin ürünleri açılır.',
            'is_active'   => 1,
            'sort_order'  => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    public function down(): void
    {
        // Şubeye atanmışsa FK (RESTRICT) silmeye izin vermez; o durumda kayıt kalır.
        if ($this->db->table('branches')->where('template_code', self::CODE)->countAllResults() === 0) {
            $this->db->table('menu_templates')->where('code', self::CODE)->delete();
        }
    }
}
