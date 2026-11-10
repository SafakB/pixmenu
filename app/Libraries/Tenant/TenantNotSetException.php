<?php

declare(strict_types=1);

namespace App\Libraries\Tenant;

use RuntimeException;

/**
 * Tenant bağlamı kurulmadan tenant kapsamlı bir sorgu yapılmaya çalışıldı.
 * Bu bir programlama hatasıdır; sessizce yutulmaz (500 + log).
 */
final class TenantNotSetException extends RuntimeException
{
    public static function create(): self
    {
        return new self('Tenant bağlamı ayarlanmadan tenant kapsamlı işlem yapılamaz.');
    }
}
