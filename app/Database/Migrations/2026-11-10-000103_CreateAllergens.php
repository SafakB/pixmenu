<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

final class CreateAllergens extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => $this->idField(),
            'code'       => ['type' => 'VARCHAR', 'constraint' => 40],
            'icon_path'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'  => $this->flagField(),
            'sort_order' => $this->sortField(),
        ] + $this->timestampFields());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->create('allergens');

        $this->createTranslationTable('allergen_translations', 'allergen_id', 'allergens', [
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('allergen_translations', true);
        $this->forge->dropTable('allergens', true);
    }
}
