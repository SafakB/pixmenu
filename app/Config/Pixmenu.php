<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Proje sabitleri: upload limitleri ve görsel boyutları (spec §12).
 */
final class Pixmenu extends BaseConfig
{
    /** public/ altındaki upload kök dizini (sonunda / yok). */
    public string $uploadDir = 'uploads';

    /**
     * Görsel türleri.
     * maxSizeKb: KB, longEdge: uzun kenar px, thumbEdge: thumbnail uzun kenar px (0 = yok).
     *
     * @var array<string, array{ext: list<string>, mime: list<string>, maxSizeKb: int, longEdge: int, thumbEdge: int, quality: int}>
     */
    public array $images = [
        'product' => [
            'ext'       => ['jpg', 'jpeg', 'png', 'webp'],
            'mime'      => ['image/jpeg', 'image/png', 'image/webp'],
            'maxSizeKb' => 5120,
            'longEdge'  => 1600,
            'thumbEdge' => 480,
            'quality'   => 82,
        ],
        'category' => [
            'ext'       => ['jpg', 'jpeg', 'png', 'webp'],
            'mime'      => ['image/jpeg', 'image/png', 'image/webp'],
            'maxSizeKb' => 3072,
            'longEdge'  => 1200,
            'thumbEdge' => 0,
            'quality'   => 82,
        ],
        'logo' => [
            'ext'       => ['jpg', 'jpeg', 'png', 'webp'],
            'mime'      => ['image/jpeg', 'image/png', 'image/webp'],
            'maxSizeKb' => 2048,
            'longEdge'  => 600,
            'thumbEdge' => 0,
            'quality'   => 90,
        ],
        'allergen' => [
            'ext'       => ['svg', 'png', 'webp'],
            'mime'      => ['image/svg+xml', 'image/svg', 'image/png', 'image/webp'],
            'maxSizeKb' => 512,
            'longEdge'  => 128,
            'thumbEdge' => 0,
            'quality'   => 90,
        ],
    ];

    /** Kabul edilen en büyük kaynak görsel boyutu (px). */
    public int $maxSourceDimension = 6000;

    /** Menü cache süresi (saniye). */
    public int $menuCacheTtl = 300;

    /** Ziyaretçi dil çerezi ömrü (saniye) — 30 gün. */
    public int $langCookieTtl = 2_592_000;

    /** Login throttle: dakikada izin verilen deneme. */
    public int $loginAttemptsPerMinute = 5;

    /** Liste sayfalarında sayfa başına kayıt. */
    public int $perPage = 50;
}
