<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Üçüncü menü şablonu: `modern` — tek sayfa, fotoğraflı ürün kartları, kategori çipleri ve
 * ortada açılan ürün penceresi. Tasarım menu-ui/src/templates/modern altındadır.
 *
 * Kod admin panelinden önceden eklenmişse dokunulmaz (idempotent).
 */
final class AddModernMenuTemplate extends BaseMigration
{
    private const CODE = 'modern';

    public function up(): void
    {
        if ($this->db->table('menu_templates')->where('code', self::CODE)->countAllResults() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('menu_templates')->insert([
            'code'        => self::CODE,
            'name'        => 'Modern',
            'description' => 'Tek sayfa; fotoğraflı ürün kartları, kategori çipleri ve ortada açılan ürün penceresi.',
            'is_active'   => 1,
            'sort_order'  => 2,
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
