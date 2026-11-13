<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Admin rolleri (Config\AdminRoles). Mevcut adminler süper admin olur ki
 * migration sonrası kimse yetkisiz kalmasın; yeni kayıtlar rolü AÇIKÇA verir.
 */
final class AddRoleToAdmins extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addColumn('admins', [
            'role' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'manager', 'after' => 'password_hash'],
        ]);

        $this->db->table('admins')->set('role', 'super_admin')->update();
    }

    public function down(): void
    {
        $this->forge->dropColumn('admins', 'role');
    }
}
