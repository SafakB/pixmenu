<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Menü tasarım şablonları (global, yalnızca admin yönetir); anahtar `code`.
 * Tasarımlar bu projede DEĞİL, ayrı ön uç projesindedir: kod, ön ucun hangi tasarımı
 * uygulayacağını seçtiği tanımlayıcıdır (menü API'sinde `branch.template`).
 */
final class MenuTemplateModel extends Model
{
    /** Silinemez/pasifleştirilemez; yeni şubelerin varsayılan şablonu. */
    public const DEFAULT_CODE = 'standart';

    protected $table            = 'menu_templates';
    protected $primaryKey       = 'code';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = ['code', 'name', 'description', 'is_active', 'sort_order'];

    /**
     * @return list<array<string, mixed>>
     */
    public function ordered(): array
    {
        return $this->orderBy('sort_order', 'ASC')->orderBy('code', 'ASC')->findAll();
    }

    /**
     * Form seçenekleri: aktif şablonlar; `$current` pasif olsa bile listeye eklenir.
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
