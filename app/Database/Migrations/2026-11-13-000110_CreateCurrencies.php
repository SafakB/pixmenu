<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Para birimi listesi (ISO 4217). Anahtar ISO kodudur; restaurants/plans.currency_code
 * buraya FK ile bağlanır. subscriptions/invoices.currency_code anlık görüntüdür (FK yok).
 */
final class CreateCurrencies extends BaseMigration
{
    /** @var list<array{0: string, 1: string, 2: string}> code, name, symbol */
    private const DEFAULTS = [
        ['TRY', 'Türk Lirası', '₺'],
        ['USD', 'ABD Doları', '$'],
        ['EUR', 'Euro', '€'],
        ['GBP', 'İngiliz Sterlini', '£'],
    ];

    public function up(): void
    {
        $this->forge->addField([
            'code'       => ['type' => 'CHAR', 'constraint' => 3],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'symbol'     => ['type' => 'VARCHAR', 'constraint' => 8],
            'decimals'   => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 2],
            'is_active'  => $this->flagField(),
            'sort_order' => $this->sortField(),
        ] + $this->timestampFields());
        $this->forge->addKey('code', true);
        $this->create('currencies');

        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach (self::DEFAULTS as $i => [$code, $name, $symbol]) {
            $rows[$code] = [
                'code' => $code, 'name' => $name, 'symbol' => $symbol, 'decimals' => 2,
                'is_active' => 1, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        // Serbest metin döneminden kalan, listede olmayan kodlar pasif olarak eklenir;
        // böylece mevcut kayıtlar FK'yi bozmaz.
        foreach (['restaurants', 'plans'] as $table) {
            foreach ($this->db->table($table)->select('currency_code')->distinct()->get()->getResultArray() as $row) {
                $code = strtoupper((string) $row['currency_code']);

                if (! isset($rows[$code])) {
                    $rows[$code] = [
                        'code' => $code, 'name' => $code, 'symbol' => $code, 'decimals' => 2,
                        'is_active' => 0, 'sort_order' => 99, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
        }

        $this->db->table('currencies')->insertBatch(array_values($rows));

        foreach (['restaurants', 'plans'] as $table) {
            $this->forge->addForeignKey('currency_code', 'currencies', 'code', 'CASCADE', 'RESTRICT', $table . '_currency_code_foreign');
            $this->forge->processIndexes($table);
        }
    }

    public function down(): void
    {
        foreach (['restaurants', 'plans'] as $table) {
            $this->forge->dropForeignKey($table, $table . '_currency_code_foreign');
        }

        $this->forge->dropTable('currencies', true);
    }
}
