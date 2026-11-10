<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Servis katmanındaki doğrulama hatası. Controller bunu yakalar:
 * HTML → redirect()->back()->withInput()->with('errors', ...), JSON → 422.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors alan => mesaj
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct((string) (reset($errors) ?: 'Doğrulama hatası.'));
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public static function single(string $field, string $message): self
    {
        return new self([$field => $message]);
    }
}
