<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Database\Exceptions\DatabaseException;
use Tests\Support\DbTestCase;

/**
 * Şema güvenceleri: InnoDB + bileşik foreign key'ler (spec §2.1.8, §4).
 * Sunucunun varsayılan motoru MyISAM olabildiği için bu test kritik.
 *
 * @internal
 */
final class SchemaTest extends DbTestCase
{
    public function testAllApplicationTablesAreInnoDb(): void
    {
        $rows = $this->db->query(
            "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME <> 'migrations'",
        )->getResultArray();

        // 16 çekirdek + 5 abonelik (plans, plan_translations, subscriptions, invoices, restaurant_domains) + currencies + menu_templates + activity_logs
        $this->assertCount(24, $rows);

        foreach ($rows as $row) {
            $this->assertSame('InnoDB', $row['ENGINE'], $row['TABLE_NAME'] . ' InnoDB olmalı');
        }
    }

    public function testCompositeForeignKeysExist(): void
    {
        $rows = $this->db->query(
            'SELECT TABLE_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) AS cols, REFERENCED_TABLE_NAME AS ref,
                    GROUP_CONCAT(REFERENCED_COLUMN_NAME ORDER BY ORDINAL_POSITION) AS ref_cols
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
             GROUP BY TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME',
        )->getResultArray();

        $actual = array_map(
            static fn (array $r): string => "{$r['TABLE_NAME']}({$r['cols']})->{$r['ref']}({$r['ref_cols']})",
            $rows,
        );

        $expected = [
            'restaurant_user_branches(user_id,restaurant_id)->restaurant_users(id,restaurant_id)',
            'restaurant_user_branches(branch_id,restaurant_id)->branches(id,restaurant_id)',
            'categories(branch_id,restaurant_id)->branches(id,restaurant_id)',
            'products(branch_id,restaurant_id)->branches(id,restaurant_id)',
            'products(category_id,branch_id)->categories(id,branch_id)',
            'invoices(subscription_id,restaurant_id)->subscriptions(id,restaurant_id)',
            'restaurants(currency_code)->currencies(code)',
            'plans(currency_code)->currencies(code)',
            'branches(template_code)->menu_templates(code)',
        ];

        foreach ($expected as $fk) {
            $this->assertContains($fk, $actual);
        }
    }

    public function testDatabaseRejectsAssigningUserToAnotherRestaurantsBranch(): void
    {
        $this->expectException(DatabaseException::class);

        // Manager A1'i, restaurant_id'yi A göstererek B'nin şubesine atamaya çalış.
        $this->db->table('restaurant_user_branches')->insert([
            'restaurant_id' => $this->fx->restaurantA,
            'user_id'       => (int) $this->fx->managerA1['id'],
            'branch_id'     => $this->fx->branchB1,
        ]);
    }

    public function testDatabaseRejectsProductInAnotherBranchesCategory(): void
    {
        $this->expectException(DatabaseException::class);

        // Şube A1'in ürünü, şube A2'nin kategorisine bağlanamaz.
        $this->db->table('products')->insert([
            'restaurant_id' => $this->fx->restaurantA,
            'branch_id'     => $this->fx->branchA1,
            'category_id'   => $this->fx->categoryA2,
            'price'         => '10.00',
        ]);
    }

    public function testDatabaseRejectsSecondCurrentSubscription(): void
    {
        $this->expectException(DatabaseException::class);

        // UNIQUE(restaurant_id, is_current): restoran başına tek güncel abonelik.
        $this->fx->subscription($this->fx->restaurantA, 'standard');
    }

    public function testDatabaseRejectsInvoiceForAnotherRestaurantsSubscription(): void
    {
        $this->expectException(DatabaseException::class);

        // restaurant_id = A ama abonelik B'ye ait.
        $this->db->table('invoices')->insert([
            'restaurant_id' => $this->fx->restaurantA, 'subscription_id' => $this->fx->subscriptionB,
            'plan_id' => $this->fx->plan['pro'], 'description' => 'x', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'issued_at' => '2026-01-01', 'due_date' => '2026-01-08',
        ]);
    }

    public function testDatabaseRejectsDuplicateInvoiceForSamePeriod(): void
    {
        $row = [
            'restaurant_id' => $this->fx->restaurantA, 'subscription_id' => $this->fx->subscriptionA,
            'plan_id' => $this->fx->plan['pro'], 'description' => 'x', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'issued_at' => '2026-01-01', 'due_date' => '2026-01-08',
        ];
        $this->db->table('invoices')->insert($row);

        $this->expectException(DatabaseException::class);
        $this->db->table('invoices')->insert($row);
    }

    public function testDatabaseRejectsCategoryWithMismatchedRestaurant(): void
    {
        $this->expectException(DatabaseException::class);

        // restaurant_id = A ama şube B'ye ait.
        $this->db->table('categories')->insert([
            'restaurant_id' => $this->fx->restaurantA,
            'branch_id'     => $this->fx->branchB1,
        ]);
    }
}
