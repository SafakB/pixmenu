<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Pixmenu;
use Config\Services;
use enshrined\svgSanitize\Sanitizer;
use InvalidArgumentException;
use Throwable;

/**
 * Dosya yükleme (spec §12).
 *
 *  - Dosya adı her zaman rastgeledir; kullanıcının dosya adı kullanılmaz.
 *  - Raster görseller GD ile YENİDEN encode edilir (EXIF ve gömülü içerik atılır).
 *  - SVG yalnızca alerjen ikonunda kabul edilir ve sanitize edilir.
 *  - Yollar public/ köküne görelidir ve tenant'a göre ayrılır: uploads/r/{rid}/...
 *  - Silmede yol YALNIZCA DB kaydından gelir; request'ten gelen yol kullanılmaz.
 */
final class ImageService
{
    private const SUBDIRS = [
        'product'  => 'products',
        'category' => 'categories',
        'logo'     => 'logo',
    ];

    private const RASTER_TYPES = [
        IMAGETYPE_JPEG => ['jpg', 'jpeg'],
        IMAGETYPE_PNG  => ['png'],
        IMAGETYPE_WEBP => ['webp'],
    ];

    private readonly string $basePath;
    private readonly Pixmenu $config;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? FCPATH, '\\/') . DIRECTORY_SEPARATOR;
        $this->config   = config('Pixmenu');
    }

    /**
     * Controller'ların kullandığı CI4 doğrulama kuralları.
     */
    public function rules(string $field, string $type): string
    {
        // Not: dosya alanlarında `permit_empty` KULLANILMAZ — CI4 değeri POST'tan
        // okuduğu için dosya kurallarının tamamını atlatır. Kurallar yalnızca
        // dosya gerçekten gönderildiyse eklenir (BaseController::imageRules).
        $cfg   = $this->typeConfig($type);
        $rules = [
            "uploaded[{$field}]",
            "max_size[{$field},{$cfg['maxSizeKb']}]",
            "ext_in[{$field}," . implode(',', $cfg['ext']) . ']',
            "mime_in[{$field}," . implode(',', $cfg['mime']) . ']',
        ];

        // SVG kabul eden türde is_image / max_dims uygulanmaz (SVG raster değildir);
        // raster kontrolleri store() içinde ayrıca yapılır.
        if (! in_array('svg', $cfg['ext'], true)) {
            $rules[] = "is_image[{$field}]";
            $rules[] = "max_dims[{$field},{$this->config->maxSourceDimension},{$this->config->maxSourceDimension}]";
        }

        return implode('|', $rules);
    }

    /**
     * Dosyayı işler ve diske yazar.
     *
     * @return array{path: string, thumb: string|null} public/ köküne göreli yollar
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file, string $type, ?int $restaurantId = null, string $field = 'image'): array
    {
        $cfg = $this->typeConfig($type);

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw ValidationException::single($field, lang('App.upload.failed'));
        }

        $tmp = $file->getTempName();

        if ($tmp === '' || ! is_file($tmp)) {
            throw ValidationException::single($field, lang('App.upload.failed'));
        }

        $size = (int) filesize($tmp);

        if ($size <= 0 || $size > $cfg['maxSizeKb'] * 1024) {
            throw ValidationException::single($field, lang('App.upload.tooLarge', [$cfg['maxSizeKb']]));
        }

        $dir  = $this->targetDir($type, $restaurantId);
        $name = bin2hex(random_bytes(16));

        if ($type === 'allergen' && $this->looksLikeSvg($file, $tmp)) {
            return ['path' => $this->storeSvg($tmp, $dir, $name, $field), 'thumb' => null];
        }

        $info = @getimagesize($tmp);

        if ($info === false || ! isset(self::RASTER_TYPES[$info[2]])
            || array_intersect(self::RASTER_TYPES[$info[2]], $cfg['ext']) === []) {
            throw ValidationException::single($field, lang('App.upload.invalidType', [implode(', ', $cfg['ext'])]));
        }

        if ($info[0] > $this->config->maxSourceDimension || $info[1] > $this->config->maxSourceDimension) {
            throw ValidationException::single($field, lang('App.upload.tooBig', [$this->config->maxSourceDimension]));
        }

        $this->ensureDir($dir);

        $path    = $dir . '/' . $name . '.webp';
        $thumb   = null;
        $written = [];

        try {
            $this->encode($tmp, $path, $cfg['longEdge'], $cfg['quality'], $info[0], $info[1]);
            $written[] = $path;

            if ($cfg['thumbEdge'] > 0) {
                $thumb = $dir . '/' . $name . '_t.webp';
                $this->encode($tmp, $thumb, $cfg['thumbEdge'], $cfg['quality'], $info[0], $info[1]);
                $written[] = $thumb;
            }
        } catch (Throwable $e) {
            $this->delete(...$written);
            log_message('error', 'ImageService: görsel işlenemedi: {msg}', ['msg' => $e->getMessage()]);

            throw ValidationException::single($field, lang('App.upload.unprocessable'));
        }

        return ['path' => $path, 'thumb' => $thumb];
    }

    /**
     * DB'den gelen göreli yolları diskten siler. Geçersiz/şüpheli yollar yok sayılır.
     */
    public function delete(?string ...$paths): void
    {
        foreach ($paths as $path) {
            $absolute = $this->absolute($path);

            if ($absolute !== null && is_file($absolute)) {
                @unlink($absolute);
            }
        }
    }

    public function exists(?string $path): bool
    {
        $absolute = $this->absolute($path);

        return $absolute !== null && is_file($absolute);
    }

    /**
     * Göreli yolu tam URL'ye çevirir (yoksa null).
     */
    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return base_url($path);
    }

    // ------------------------------------------------------------------

    /**
     * @return array{ext: list<string>, mime: list<string>, maxSizeKb: int, longEdge: int, thumbEdge: int, quality: int}
     */
    private function typeConfig(string $type): array
    {
        return $this->config->images[$type]
            ?? throw new InvalidArgumentException("Bilinmeyen görsel türü: {$type}");
    }

    private function targetDir(string $type, ?int $restaurantId): string
    {
        if ($type === 'allergen') {
            return $this->config->uploadDir . '/allergens';
        }

        if ($restaurantId === null || $restaurantId <= 0) {
            throw new InvalidArgumentException('Tenant görseli için restaurant_id zorunludur.');
        }

        return $this->config->uploadDir . '/r/' . $restaurantId . '/' . self::SUBDIRS[$type];
    }

    private function ensureDir(string $relativeDir): void
    {
        $absolute = $this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        if (! is_dir($absolute) && ! mkdir($absolute, 0755, true) && ! is_dir($absolute)) {
            throw new \RuntimeException('Upload dizini oluşturulamadı.');
        }
    }

    private function absolute(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }

        $prefix = preg_quote($this->config->uploadDir, '#');

        if (str_contains($relative, '..') || preg_match('#^' . $prefix . '/[A-Za-z0-9_\-/]+\.(webp|svg)$#', $relative) !== 1) {
            log_message('warning', 'ImageService: şüpheli dosya yolu yok sayıldı: {path}', ['path' => $relative]);

            return null;
        }

        return $this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function encode(string $source, string $relativeTarget, int $longEdge, int $quality, int $width, int $height): void
    {
        $image = Services::image('gd', null, false)->withFile($source)->reorient(true);

        if (max($width, $height) > $longEdge) {
            $image->resize($longEdge, $longEdge, true, 'auto');
        }

        $image->convert(IMAGETYPE_WEBP)->save($this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $relativeTarget), $quality);
    }

    private function looksLikeSvg(UploadedFile $file, string $tmp): bool
    {
        $ext  = strtolower(pathinfo($file->getClientName(), PATHINFO_EXTENSION));
        $mime = strtolower($file->getMimeType());

        if ($ext === 'svg' || str_contains($mime, 'svg')) {
            return true;
        }

        // Raster olarak okunamayan ama <svg içeren içerik de SVG muamelesi görür (sanitize edilir).
        return @getimagesize($tmp) === false
            && stripos((string) file_get_contents($tmp, false, null, 0, 2048), '<svg') !== false;
    }

    private function storeSvg(string $tmp, string $dir, string $name, string $field): string
    {
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->minify(true);

        $clean = $sanitizer->sanitize((string) file_get_contents($tmp));

        if ($clean === false || $clean === '' || stripos($clean, '<svg') === false) {
            throw ValidationException::single($field, lang('App.upload.invalidSvg'));
        }

        $this->ensureDir($dir);

        $path = $dir . '/' . $name . '.svg';

        if (file_put_contents($this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $path), $clean) === false) {
            throw ValidationException::single($field, lang('App.upload.unprocessable'));
        }

        return $path;
    }
}
