<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

final class CreateRestaurantUsersAndBranches extends BaseMigration
{
    public function up(): void
    {
        // restaurant_users
        $this->forge->addField([
            'id'                  => $this->idField(),
            'restaurant_id'       => $this->refField(),
            'name'                => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'               => ['type' => 'VARCHAR', 'constraint' => 190],
            'password_hash'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'                => ['type' => 'ENUM', 'constraint' => ['owner', 'manager']],
            'is_active'           => $this->flagField(),
            'last_login_at'       => ['type' => 'DATETIME', 'null' => true],
            'password_changed_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->timestampFields(true));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');                    // global unique: login'de restoran seçtirilmez
        $this->forge->addUniqueKey(['id', 'restaurant_id']);    // bileşik FK hedefi
        $this->forge->addForeignKey('restaurant_id', 'restaurants', 'id', '', 'CASCADE');
        $this->create('restaurant_users');

        // branches
        $this->forge->addField([
            'id'                  => $this->idField(),
            'restaurant_id'       => $this->refField(),
            'slug'                => ['type' => 'VARCHAR', 'constraint' => 120],
            'public_code'         => ['type' => 'CHAR', 'constraint' => 10],
            'default_language_id' => $this->refField(),
            'phone'               => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'is_active'           => $this->flagField(),
            'sort_order'          => $this->sortField(),
        ] + $this->timestampFields(true));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['restaurant_id', 'slug']);
        $this->forge->addUniqueKey('public_code');              // global unique
        $this->forge->addUniqueKey(['id', 'restaurant_id']);    // bileşik FK hedefi
        $this->forge->addForeignKey('restaurant_id', 'restaurants', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('default_language_id', 'languages', 'id', '', 'RESTRICT');
        $this->create('branches');

        $this->createTranslationTable('branch_translations', 'branch_id', 'branches', [
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'address'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
        ]);

        // restaurant_user_branches — bileşik FK'ler sayesinde bir kullanıcı
        // başka restoranın şubesine DB seviyesinde atanamaz.
        $this->forge->addField([
            'restaurant_id' => $this->refField(),
            'user_id'       => $this->refField(),
            'branch_id'     => $this->refField(),
        ]);
        $this->forge->addKey(['user_id', 'branch_id'], true);
        $this->forge->addForeignKey(['user_id', 'restaurant_id'], 'restaurant_users', ['id', 'restaurant_id'], '', 'CASCADE');
        $this->forge->addForeignKey(['branch_id', 'restaurant_id'], 'branches', ['id', 'restaurant_id'], '', 'CASCADE');
        $this->create('restaurant_user_branches');
    }

    public function down(): void
    {
        $this->forge->dropTable('restaurant_user_branches', true);
        $this->forge->dropTable('branch_translations', true);
        $this->forge->dropTable('branches', true);
        $this->forge->dropTable('restaurant_users', true);
    }
}
