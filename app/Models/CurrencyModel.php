<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Para birimleri (ISO 4217); anahtar `code`. Liste migration ile tutulur, yönetim ekranı yok.
 */
final class CurrencyModel extends Model
{
    protected $table            = 'currencies';
    protected $primaryKey       = 'code';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = ['code', 'name', 'symbol', 'decimals', 'is_active', 'sort_order'];

    /**
     * Form seçenekleri: aktif para birimleri; `$current` pasif olsa bile listeye eklenir
     * (mevcut kaydı düzenlerken seçimi kaybetmemek için).
     *
     * @return list<array<string, mixed>>
     */
    public function options(?string $current = null): array
    {
        $this->groupStart()->where('is_active', 1);

        if ($current !== null && $current !== '') {
            $this->orWhere('code', $current);
        }

        return $this->groupEnd()->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
    }

    /**
     * Kod aktifse ya da kaydın zaten kullandığı kodsa seçilebilir.
     */
    public function isSelectable(string $code, ?string $current = null): bool
    {
        if ($current !== null && $code === $current) {
            return true;
        }

        return $this->where('code', $code)->where('is_active', 1)->countAllResults() > 0;
    }
}
