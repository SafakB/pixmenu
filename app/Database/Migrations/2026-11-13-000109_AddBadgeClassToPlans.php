<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Paket rozeti için CSS class'ı (ör. "bg-primary", "bg-warning text-dark"). Boş = varsayılan rozet.
 */
final class AddBadgeClassToPlans extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addColumn('plans', [
            'badge_class' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'code'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('plans', 'badge_class');
    }
}
