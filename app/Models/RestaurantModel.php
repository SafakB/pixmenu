<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Restoran kaydının kendisi. Tenant tablosu DEĞİLDİR (restaurant_id kolonu yok);
 * panel tarafında yalnızca `find(service('tenant')->restaurantId())` ile okunur.
 */
final class RestaurantModel extends Model
{
    protected $table          = 'restaurants';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['slug', 'default_language_id', 'currency_code', 'logo_path', 'is_active', 'suspended_at', 'suspension_reason', 'discount_percent', 'discount_until'];
}
