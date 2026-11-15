<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Kategori emojisi: görsel hazırlayamayan müşteriler `Config\CategoryEmojis` listesinden seçer.
 * Menüde görsel varsa görsel, yoksa emoji gösterilir. Boş = emoji yok.
 */
final class AddEmojiToCategories extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addColumn('categories', [
            'emoji' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'after' => 'image_path'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('categories', 'emoji');
    }
}
