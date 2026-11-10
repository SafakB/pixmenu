<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

final class CreateLanguages extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => $this->idField(),
            'code'        => ['type' => 'VARCHAR', 'constraint' => 10],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'native_name' => ['type' => 'VARCHAR', 'constraint' => 60],
            'direction'   => ['type' => 'ENUM', 'constraint' => ['ltr', 'rtl'], 'default' => 'ltr'],
            'flag_path'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'   => $this->flagField(),
            'sort_order'  => $this->sortField(),
        ] + $this->timestampFields());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->create('languages');
    }

    public function down(): void
    {
        $this->forge->dropTable('languages', true);
    }
}
