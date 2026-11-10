<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

final class CreateRestaurants extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => $this->idField(),
            'slug'                => ['type' => 'VARCHAR', 'constraint' => 120],
            'default_language_id' => $this->refField(),
            'currency_code'       => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'TRY'],
            'logo_path'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'           => $this->flagField(),
        ] + $this->timestampFields(true));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addForeignKey('default_language_id', 'languages', 'id', '', 'RESTRICT');
        $this->create('restaurants');

        $this->createTranslationTable('restaurant_translations', 'restaurant_id', 'restaurants', [
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
        ]);

        // Restoranın kullanabileceği diller. PK (restaurant_id, language_id).
        $this->forge->addField([
            'restaurant_id' => $this->refField(),
            'language_id'   => $this->refField(),
            'sort_order'    => $this->sortField(),
            'is_active'     => $this->flagField(),
        ]);
        $this->forge->addKey(['restaurant_id', 'language_id'], true);
        $this->forge->addForeignKey('restaurant_id', 'restaurants', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('language_id', 'languages', 'id', '', 'RESTRICT');
        $this->create('restaurant_languages');
    }

    public function down(): void
    {
        $this->forge->dropTable('restaurant_languages', true);
        $this->forge->dropTable('restaurant_translations', true);
        $this->forge->dropTable('restaurants', true);
    }
}
