<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class LanguageModel extends Model
{
    protected $table         = 'languages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'name', 'native_name', 'direction', 'flag_path', 'is_active', 'sort_order'];

    /**
     * @return list<array<string, mixed>>
     */
    public function ordered(bool $activeOnly = false): array
    {
        if ($activeOnly) {
            $this->where('is_active', 1);
        }

        return $this->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }

    /**
     * @param list<string> $codes
     *
     * @return array<string, int> code => id
     */
    public function idsByCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $map = [];

        foreach ($this->select('id, code')->whereIn('code', $codes)->findAll() as $row) {
            $map[(string) $row['code']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public function allCodes(): array
    {
        return array_map('strval', array_column($this->select('code')->findAll(), 'code'));
    }
}
