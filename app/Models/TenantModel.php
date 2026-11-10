<?php

declare(strict_types=1);

namespace App\Models;

use Closure;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;
use LogicException;

/**
 * Tenant tablolarına ait tüm modellerin tabanı (spec §6.2).
 *
 * Her okuma/güncelleme/silme sorgusuna otomatik olarak
 * `WHERE <tablo>.restaurant_id = :tenant` eklenir; insert'te restaurant_id
 * ZORLA tenant değerine ayarlanır; update'te restaurant_id değiştirilemez.
 *
 * Üç çalışma kipi vardır:
 *  - varsayılan      : service('tenant') üzerinden kapsam (restoran paneli)
 *  - forRestaurant() : belirli bir restorana sabitlenmiş kapsam (admin servisleri)
 *  - withoutTenantScope(): kapsam yok (yalnızca admin servisleri / auth filtresi)
 */
abstract class TenantModel extends Model
{
    /**
     * Kapsam açıkken model üzerinden __call ile çağrılması yasak builder metodları
     * (kapsam uygulanmadan sorgu çalıştırırlar).
     */
    private const BLOCKED_BUILDER_METHODS = [
        'get', 'getwhere', 'countall', 'truncate', 'emptytable',
        'upsert', 'upsertbatch', 'deletebatch', 'increment', 'decrement',
    ];

    protected bool $tenantScope         = true;
    protected ?int $pinnedRestaurantId  = null;
    protected $returnType               = 'array';
    protected $allowCallbacks           = true;
    protected $beforeInsert             = ['enforceTenantOnInsert'];
    protected $beforeInsertBatch        = ['enforceTenantOnInsertBatch'];
    protected $beforeUpdate             = ['stripTenantOnUpdate'];
    protected $beforeUpdateBatch        = ['stripTenantOnUpdateBatch'];

    public function __clone()
    {
        // Klon, orijinalin yarım kalmış sorgu durumunu paylaşmamalı.
        $this->builder = null;
    }

    /**
     * Yalnızca Admin servisleri ve auth katmanı kullanır.
     * Panel kodunda çağrılması YASAKTIR.
     */
    public function withoutTenantScope(): static
    {
        $clone                     = clone $this;
        $clone->tenantScope        = false;
        $clone->pinnedRestaurantId = null;

        return $clone;
    }

    /**
     * Admin servisleri için: TenantContext yerine verilen restorana sabitlenmiş kapsam.
     * Panel kodunda çağrılması YASAKTIR.
     */
    public function forRestaurant(int $restaurantId): static
    {
        if ($restaurantId <= 0) {
            throw new LogicException('Geçersiz restoran kimliği.');
        }

        $clone                     = clone $this;
        $clone->tenantScope        = false;
        $clone->pinnedRestaurantId = $restaurantId;

        return $clone;
    }

    /**
     * Geçerli kapsamın restoran kimliği; kapsam kapalıysa null.
     * Kapsam açık ama tenant set edilmemişse TenantNotSetException fırlatır.
     */
    protected function scopeRestaurantId(): ?int
    {
        if ($this->pinnedRestaurantId !== null) {
            return $this->pinnedRestaurantId;
        }

        if (! $this->tenantScope) {
            return null;
        }

        return service('tenant')->restaurantId();
    }

    protected function requireScopeRestaurantId(): int
    {
        return $this->scopeRestaurantId()
            ?? throw new LogicException(static::class . ': bu işlem restoran kapsamı gerektirir.');
    }

    protected function applyTenantScope(): void
    {
        $restaurantId = $this->scopeRestaurantId();

        if ($restaurantId === null) {
            return;
        }

        $this->builder()->where($this->table . '.restaurant_id', $restaurantId);
    }

    /**
     * Özel sorgu metodları için kapsam uygulanmış builder.
     * Özel metodlarda `$this->builder()` DEĞİL, bu kullanılır.
     */
    protected function scopedBuilder(): BaseBuilder
    {
        $this->applyTenantScope();

        return $this->builder();
    }

    protected function doFind(bool $singleton, $id = null)
    {
        $this->applyTenantScope();

        return parent::doFind($singleton, $id);
    }

    protected function doFindAll(?int $limit = null, int $offset = 0)
    {
        $this->applyTenantScope();

        return parent::doFindAll($limit, $offset);
    }

    protected function doFirst()
    {
        $this->applyTenantScope();

        return parent::doFirst();
    }

    protected function doUpdate($id = null, $row = null): bool
    {
        $this->guardUnconditional($id, 'update');
        $this->applyTenantScope();

        return parent::doUpdate($id, $row);
    }

    protected function doUpdateBatch(?array $set = null, ?string $index = null, int $batchSize = 100, bool $returnSQL = false)
    {
        if ($this->scopeRestaurantId() !== null) {
            throw new LogicException(static::class . ': tenant kapsamında updateBatch desteklenmez.');
        }

        return parent::doUpdateBatch($set, $index, $batchSize, $returnSQL);
    }

    protected function doDelete($id = null, bool $purge = false)
    {
        $this->guardUnconditional($id, 'delete');
        $this->applyTenantScope();

        return parent::doDelete($id, $purge);
    }

    protected function doPurgeDeleted()
    {
        $this->applyTenantScope();

        return parent::doPurgeDeleted();
    }

    protected function doReplace(?array $row = null, bool $returnSQL = false)
    {
        if ($this->scopeRestaurantId() !== null) {
            throw new LogicException(static::class . ': tenant kapsamında replace desteklenmez.');
        }

        return parent::doReplace($row, $returnSQL);
    }

    public function countAllResults(bool $reset = true, bool $test = false)
    {
        $this->applyTenantScope();

        return parent::countAllResults($reset, $test);
    }

    public function chunk(int $size, Closure $userFunc)
    {
        $this->applyTenantScope();

        parent::chunk($size, $userFunc);
    }

    public function __call(string $name, array $params)
    {
        if (($this->tenantScope || $this->pinnedRestaurantId !== null)
            && in_array(strtolower($name), self::BLOCKED_BUILDER_METHODS, true)) {
            throw new LogicException(
                static::class . "::{$name}() tenant kapsamını atlar; find/findAll/first veya kapsamlı özel bir model metodu kullanın.",
            );
        }

        return parent::__call($name, $params);
    }

    /**
     * Kapsam filtresi her zaman bir WHERE eklediği için CI4'ün "where'siz update/delete"
     * koruması devre dışı kalır; aynı korumayı burada biz uygularız.
     *
     * @param mixed $id
     */
    private function guardUnconditional($id, string $operation): void
    {
        $hasId = ! ($id === null || $id === [] || $id === '');

        if (! $hasId && $this->builder()->getCompiledQBWhere() === []) {
            throw new LogicException(static::class . ": koşulsuz {$operation} yapılamaz.");
        }
    }

    /**
     * beforeInsert: restaurant_id kapsam açıksa ZORLA üzerine yazılır.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function enforceTenantOnInsert(array $data): array
    {
        $data['data'] = $this->withTenantId($data['data']);

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function enforceTenantOnInsertBatch(array $data): array
    {
        foreach ($data['data'] as $i => $row) {
            $data['data'][$i] = $this->withTenantId($row);
        }

        return $data;
    }

    /**
     * beforeUpdate: restaurant_id hiçbir koşulda değiştirilemez.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function stripTenantOnUpdate(array $data): array
    {
        unset($data['data']['restaurant_id']);

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function stripTenantOnUpdateBatch(array $data): array
    {
        foreach ($data['data'] as $i => $row) {
            unset($row['restaurant_id']);
            $data['data'][$i] = $row;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function withTenantId(array $row): array
    {
        $restaurantId = $this->scopeRestaurantId();

        if ($restaurantId !== null) {
            $row['restaurant_id'] = $restaurantId;

            return $row;
        }

        if (! isset($row['restaurant_id']) || (int) $row['restaurant_id'] <= 0) {
            throw new LogicException(static::class . ': kapsam dışı insert için restaurant_id açıkça verilmelidir.');
        }

        return $row;
    }
}
