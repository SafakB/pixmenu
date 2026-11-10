<?php

declare(strict_types=1);

namespace App\Database;

use CodeIgniter\Database\Migration;

/**
 * Ortak migration yardımcıları.
 *
 * Sunucunun varsayılan motoru MyISAM olabildiği için (FK'leri sessizce yok sayar)
 * her tablo AÇIKÇA InnoDB olarak oluşturulur. Bu sabiti kaldırma.
 */
abstract class BaseMigration extends Migration
{
    /** @var array<string, string> */
    protected const TABLE_ATTRIBUTES = [
        'ENGINE'          => 'InnoDB',
        'DEFAULT CHARSET' => 'utf8mb4',
        'COLLATE'         => 'utf8mb4_unicode_ci',
    ];

    /** @return array<string, mixed> */
    protected function idField(): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true];
    }

    /** @return array<string, mixed> */
    protected function refField(bool $null = false): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'null' => $null];
    }

    /** @return array<string, mixed> */
    protected function flagField(int $default = 1): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => $default];
    }

    /** @return array<string, mixed> */
    protected function sortField(): array
    {
        return ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0];
    }

    /** @return array<string, array<string, mixed>> */
    protected function timestampFields(bool $softDelete = false): array
    {
        $fields = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        if ($softDelete) {
            $fields['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        return $fields;
    }

    protected function create(string $table): void
    {
        $this->forge->createTable($table, false, self::TABLE_ATTRIBUTES);
    }

    /**
     * `{parent}_translations` tablosu: UNIQUE (parent, language), ikisi de CASCADE.
     *
     * @param array<string, array<string, mixed>> $textFields
     */
    protected function createTranslationTable(string $table, string $fkColumn, string $parentTable, array $textFields): void
    {
        $this->forge->addField([
            'id'          => $this->idField(),
            $fkColumn     => $this->refField(),
            'language_id' => $this->refField(),
        ] + $textFields);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey([$fkColumn, 'language_id']);
        $this->forge->addForeignKey($fkColumn, $parentTable, 'id', '', 'CASCADE');
        $this->forge->addForeignKey('language_id', 'languages', 'id', '', 'CASCADE');
        $this->create($table);
    }
}
