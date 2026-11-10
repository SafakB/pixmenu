<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

final class CreateMenuTables extends BaseMigration
{
    public function up(): void
    {
        // categories
        $this->forge->addField([
            'id'            => $this->idField(),
            'restaurant_id' => $this->refField(),
            'branch_id'     => $this->refField(),
            'image_path'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'     => $this->flagField(),
            'sort_order'    => $this->sortField(),
        ] + $this->timestampFields(true));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['id', 'branch_id']);        // ürün FK hedefi
        $this->forge->addKey(['branch_id', 'is_active', 'sort_order']);
        $this->forge->addForeignKey(['branch_id', 'restaurant_id'], 'branches', ['id', 'restaurant_id'], '', 'CASCADE');
        $this->create('categories');

        $this->createTranslationTable('category_translations', 'category_id', 'categories', [
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
        ]);

        // products
        $this->forge->addField([
            'id'            => $this->idField(),
            'restaurant_id' => $this->refField(),
            'branch_id'     => $this->refField(),
            'category_id'   => $this->refField(),
            'price'         => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => '0.00'],
            'image_path'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'thumb_path'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'     => $this->flagField(),
            'sort_order'    => $this->sortField(),
        ] + $this->timestampFields(true));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['category_id', 'is_active', 'sort_order']);
        $this->forge->addForeignKey(['branch_id', 'restaurant_id'], 'branches', ['id', 'restaurant_id'], '', 'CASCADE');
        // Bir ürünün başka şubenin kategorisine bağlanmasını DB seviyesinde engeller.
        $this->forge->addForeignKey(['category_id', 'branch_id'], 'categories', ['id', 'branch_id'], '', 'CASCADE');
        $this->create('products');

        $this->createTranslationTable('product_translations', 'product_id', 'products', [
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
        ]);

        // product_allergens
        $this->forge->addField([
            'restaurant_id' => $this->refField(),
            'product_id'    => $this->refField(),
            'allergen_id'   => $this->refField(),
            'level'         => ['type' => 'ENUM', 'constraint' => ['contains', 'may_contain'], 'default' => 'contains'],
        ]);
        $this->forge->addKey(['product_id', 'allergen_id'], true);
        $this->forge->addKey('restaurant_id');
        $this->forge->addForeignKey('product_id', 'products', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('allergen_id', 'allergens', 'id', '', 'RESTRICT');
        $this->create('product_allergens');
    }

    public function down(): void
    {
        $this->forge->dropTable('product_allergens', true);
        $this->forge->dropTable('product_translations', true);
        $this->forge->dropTable('products', true);
        $this->forge->dropTable('category_translations', true);
        $this->forge->dropTable('categories', true);
    }
}
