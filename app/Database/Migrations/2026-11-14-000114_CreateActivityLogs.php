<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Admin ve panel kullanıcılarının işlem kayıtları (App\Services\ActivityLogger).
 *
 * Yalnızca eklenen bir tablodur. FK YOK: kayıtlar, ilgili kullanıcı/restoran
 * silindikten sonra da okunabilmelidir (actor_label anlık kopyadır).
 */
final class CreateActivityLogs extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'created_at'    => ['type' => 'DATETIME'],
            'area'          => ['type' => 'VARCHAR', 'constraint' => 10],
            'actor_id'      => $this->refField(),
            'actor_label'   => ['type' => 'VARCHAR', 'constraint' => 190],
            'restaurant_id' => $this->refField(true),
            'action'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'path'          => ['type' => 'VARCHAR', 'constraint' => 190],
            'ip'            => ['type' => 'VARCHAR', 'constraint' => 45],
            'detail'        => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('created_at');
        $this->forge->addKey(['restaurant_id', 'created_at']);
        $this->forge->addKey(['area', 'actor_id', 'created_at']);
        $this->create('activity_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('activity_logs', true);
    }
}
