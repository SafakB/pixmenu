<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

final class CreateAdmins extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => $this->idField(),
            'name'                => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'               => ['type' => 'VARCHAR', 'constraint' => 190],
            'password_hash'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'is_active'           => $this->flagField(),
            'last_login_at'       => ['type' => 'DATETIME', 'null' => true],
            'password_changed_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->timestampFields());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->create('admins');
    }

    public function down(): void
    {
        $this->forge->dropTable('admins', true);
    }
}
