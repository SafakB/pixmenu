<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Bir iş kuralı işlemi reddetti (ör. son owner silinemez, içinde ürün olan
 * kategori silinemez). Controller bunu `error` flash mesajına / 422 JSON'a çevirir.
 */
final class BusinessRuleException extends RuntimeException
{
}
