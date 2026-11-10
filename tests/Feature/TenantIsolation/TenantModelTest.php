<?php

declare(strict_types=1);

namespace Tests\Feature\TenantIsolation;

use App\Libraries\Tenant\TenantNotSetException;
use App\Models\BranchModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use LogicException;
use Tests\Support\DbTestCase;

/**
 * TenantModel davranışı (spec §6.2) — Test 13 dahil.
 *
 * @internal
 */
final class TenantModelTest extends DbTestCase
{
    private function actAsOwnerA(): void
    {
        service('tenant')->set($this->fx->restaurantA, (int) $this->fx->ownerA['id'], 'owner', null);
    }

    /** Test 13 */
    public function testFindWithoutTenantThrows(): void
    {
        $this->expectException(TenantNotSetException::class);

        (new ProductModel())->find($this->fx->productA1);
    }

    public function testFindAllCountAndFirstWithoutTenantThrow(): void
    {
        foreach (['findAll', 'first', 'countAllResults'] as $method) {
            try {
                (new ProductModel())->{$method}();
                $this->fail("{$method} tenant olmadan çalışmamalı");
            } catch (TenantNotSetException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFindIsScopedToTenant(): void
    {
        $this->actAsOwnerA();
        $model = new ProductModel();

        $this->assertNotNull($model->find($this->fx->productA1));
        $this->assertNull($model->find($this->fx->productB1));
        $this->assertSame([], $model->find([$this->fx->productB1]));
    }

    public function testFindAllAndCountNeverIncludeOtherTenant(): void
    {
        $this->actAsOwnerA();
        $model = new ProductModel();

        $ids = array_map('intval', array_column($model->findAll(), 'id'));

        $this->assertNotContains($this->fx->productB1, $ids);
        $this->assertCount(3, $ids);
        $this->assertSame(3, $model->countAllResults());
        $this->assertNull($model->where('id', $this->fx->productB1)->first());
    }

    public function testUpdateAndDeleteCannotTouchOtherTenant(): void
    {
        $this->actAsOwnerA();
        $model = new ProductModel();

        $model->update($this->fx->productB1, ['price' => '1.00']);
        $model->delete($this->fx->productB1);
        $model->where('id', $this->fx->productB1)->set(['price' => '2.00'])->update();

        $row = $this->row('products', $this->fx->productB1);
        $this->assertSame('999.00', $row['price']);
        $this->assertNull($row['deleted_at']);
    }

    public function testInsertForcesTenantId(): void
    {
        $this->actAsOwnerA();
        $model = new CategoryModel();

        $id = $model->insert([
            'restaurant_id' => $this->fx->restaurantB, // yok sayılmalı
            'branch_id'     => $this->fx->branchA1,
            'is_active'     => 1,
            'sort_order'    => 9,
        ]);

        $this->assertSame($this->fx->restaurantA, (int) $this->row('categories', (int) $id)['restaurant_id']);
    }

    public function testUpdateCannotChangeTenantId(): void
    {
        $this->actAsOwnerA();

        (new ProductModel())->update($this->fx->productA1, [
            'restaurant_id' => $this->fx->restaurantB,
            'price'         => '55.00',
        ]);

        $row = $this->row('products', $this->fx->productA1);
        $this->assertSame($this->fx->restaurantA, (int) $row['restaurant_id']);
        $this->assertSame('55.00', $row['price']);
    }

    public function testUnconditionalUpdateAndDeleteAreRejected(): void
    {
        $this->actAsOwnerA();

        foreach ([
            static fn () => (new ProductModel())->set(['price' => '0.00'])->update(),
            static fn () => (new ProductModel())->delete(),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Koşulsuz yazma reddedilmeli');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('100.00', $this->row('products', $this->fx->productA1)['price']);
    }

    public function testRawBuilderTerminalsAreBlockedOnScopedModel(): void
    {
        $this->actAsOwnerA();

        $this->expectException(LogicException::class);

        (new ProductModel())->where('id >', 0)->get();
    }

    public function testForRestaurantPinsScopeWithoutTenantContext(): void
    {
        $model = (new BranchModel())->forRestaurant($this->fx->restaurantB);

        $this->assertNotNull($model->find($this->fx->branchB1));
        $this->assertNull($model->find($this->fx->branchA1));
        $this->assertCount(1, $model->findAll());
    }

    public function testWithoutTenantScopeDoesNotLeakIntoOriginalModel(): void
    {
        $this->actAsOwnerA();
        $model = new BranchModel();

        $this->assertCount(3, $model->withoutTenantScope()->findAll());
        $this->assertCount(2, $model->findAll());
    }
}
