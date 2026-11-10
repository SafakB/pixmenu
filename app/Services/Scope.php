<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TenantModel;

/**
 * Bir servisin HANGİ restoran üzerinde çalıştığını belirler.
 *
 *  - Scope::tenant()        → restoran paneli: restaurant_id yalnızca TenantContext'ten gelir.
 *  - Scope::admin($id)      → admin: URL'de doğrulanmış restorana sabitlenir.
 *
 * Panel kodu (app/Controllers/Panel) Scope::admin() ÇAĞIRAMAZ.
 * Her iki durumda da modeller restoran kapsamlıdır; kapsam dışı sorgu üretilmez.
 */
final class Scope
{
    private function __construct(private readonly ?int $pinnedRestaurantId)
    {
    }

    public static function tenant(): self
    {
        return new self(null);
    }

    public static function admin(int $restaurantId): self
    {
        if ($restaurantId <= 0) {
            throw new \InvalidArgumentException('Geçersiz restoran kimliği.');
        }

        return new self($restaurantId);
    }

    public function isAdmin(): bool
    {
        return $this->pinnedRestaurantId !== null;
    }

    public function restaurantId(): int
    {
        return $this->pinnedRestaurantId ?? service('tenant')->restaurantId();
    }

    /**
     * Bu kapsamdaki tenant modeli.
     *
     * @template T of TenantModel
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function model(string $class): TenantModel
    {
        $model = new $class();

        return $this->pinnedRestaurantId === null ? $model : $model->forRestaurant($this->pinnedRestaurantId);
    }
}
