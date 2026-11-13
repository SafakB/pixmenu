<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Restoran geneli yüzdelik indirim: kesilen her abonelik faturasına uygulanır.
 * discount_percent NULL = indirim yok; discount_until NULL = süresiz (dönem başı bu tarihten sonraysa uygulanmaz).
 */
final class AddDiscountToRestaurants extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addColumn('restaurants', [
            'discount_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true, 'after' => 'suspension_reason'],
            'discount_until'   => ['type' => 'DATE', 'null' => true, 'after' => 'discount_percent'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('restaurants', ['discount_percent', 'discount_until']);
    }
}
