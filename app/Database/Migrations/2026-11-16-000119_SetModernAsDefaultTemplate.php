<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Yeni şubelerin varsayılan menü şablonu `standart` yerine `modern` olur
 * (kod tarafı: MenuTemplateModel::DEFAULT_CODE). Mevcut şubelerin şablonuna dokunulmaz.
 *
 * Varsayılan şablon silinemez/pasifleştirilemez olduğundan `modern` yoksa eklenir, pasifse aktifleştirilir.
 */
final class SetModernAsDefaultTemplate extends BaseMigration
{
    private const CODE = 'modern';

    public function up(): void
    {
        $now   = date('Y-m-d H:i:s');
        $table = $this->db->table('menu_templates');
        $row   = $table->where('code', self::CODE)->get()->getRowArray();

        if ($row === null) {
            $this->db->table('menu_templates')->insert([
                'code'        => self::CODE,
                'name'        => 'Modern',
                'description' => 'Tek sayfa; fotoğraflı ürün kartları, kategori çipleri ve ortada açılan ürün penceresi.',
                'is_active'   => 1,
                'sort_order'  => 2,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        } elseif ((int) $row['is_active'] !== 1) {
            $this->db->table('menu_templates')->where('code', self::CODE)->update(['is_active' => 1, 'updated_at' => $now]);
        }

        $this->setColumnDefault(self::CODE);
    }

    public function down(): void
    {
        $this->setColumnDefault('standart');
    }

    private function setColumnDefault(string $code): void
    {
        $this->forge->modifyColumn('branches', [
            'template_code' => ['name' => 'template_code', 'type' => 'VARCHAR', 'constraint' => 30, 'null' => false, 'default' => $code],
        ]);
    }
}
