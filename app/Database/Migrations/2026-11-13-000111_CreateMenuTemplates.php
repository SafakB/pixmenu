<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Menü tasarım şablonları. Tasarımlar ayrı ön uç projesindedir; anahtar `code` ön ucun
 * tasarımı seçtiği tanımlayıcıdır (menü API'sinde `branch.template`). Her şube bir şablona bağlıdır;
 * varsayılan `standart` (DB varsayılanı, mevcut şubeler de buna düşer).
 */
final class CreateMenuTemplates extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addField([
            'code'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'   => $this->flagField(),
            'sort_order'  => $this->sortField(),
        ] + $this->timestampFields());
        $this->forge->addKey('code', true);
        $this->create('menu_templates');

        $now = date('Y-m-d H:i:s');

        $this->db->table('menu_templates')->insert([
            'code' => 'standart', 'name' => 'Standart', 'description' => 'Varsayılan menü tasarımı.',
            'is_active' => 1, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->forge->addColumn('branches', [
            'template_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false, 'default' => 'standart', 'after' => 'default_language_id'],
        ]);
        $this->forge->addForeignKey('template_code', 'menu_templates', 'code', 'CASCADE', 'RESTRICT', 'branches_template_code_foreign');
        $this->forge->processIndexes('branches');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('branches', 'branches_template_code_foreign');
        $this->forge->dropColumn('branches', 'template_code');
        $this->forge->dropTable('menu_templates', true);
    }
}
