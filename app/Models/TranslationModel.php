<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * `*_translations` tablolarının ortak tabanı.
 *
 * Çeviri tablolarına DOĞRUDAN erişilmez (spec §2.1.7): önce ebeveyn kayıt tenant
 * kapsamlı model ile yüklenir, sonra çeviriler o ebeveynin ID'si ile
 * TranslationService üzerinden okunur/yazılır.
 */
abstract class TranslationModel extends Model
{
    /** Ebeveyn tabloyu gösteren kolon. */
    public const FK = '';

    /**
     * Metin alanları: alan => azami uzunluk (null = sınırsız TEXT).
     *
     * @var array<string, int|null>
     */
    public const TEXT_FIELDS = ['name' => 150, 'description' => null];

    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    public function __construct(...$args)
    {
        $this->allowedFields = array_merge([static::FK, 'language_id'], array_keys(static::TEXT_FIELDS));

        parent::__construct(...$args);
    }
}
