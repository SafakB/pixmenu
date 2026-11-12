<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;
use App\Database\Seeds\PlanSeeder;
use Config\Database;

/**
 * Var olan restoranlara bugünden başlayan Demo denemesi atar (docs/ABONELIK_PLANI.md §1, varsayım 2).
 * Boş veritabanında (ilk kurulum / test) hiçbir şey yapmaz; paketler PlanSeeder ile gelir.
 */
final class AssignInitialSubscriptions extends BaseMigration
{
    public function up(): void
    {
        $restaurants = $this->db->table('restaurants')->select('id')->get()->getResultArray();

        if ($restaurants === []) {
            return;
        }

        Database::seeder($this->DBGroup)->setSilent(true)->call(PlanSeeder::class);

        $plan = $this->db->table('plans')->where('code', config('Billing')->defaultPlanCode)->get()->getRowArray();

        if ($plan === null) {
            return;
        }

        $today = date('Y-m-d');
        $end   = date('Y-m-d', strtotime($today . ' +' . max(1, (int) $plan['trial_days']) . ' days -1 day'));
        $now   = date('Y-m-d H:i:s');

        foreach ($restaurants as $restaurant) {
            $hasCurrent = $this->db->table('subscriptions')
                ->where('restaurant_id', $restaurant['id'])
                ->where('is_current', 1)
                ->countAllResults() > 0;

            if ($hasCurrent) {
                continue;
            }

            $this->db->table('subscriptions')->insert([
                'restaurant_id'        => $restaurant['id'],
                'plan_id'              => $plan['id'],
                'billing_cycle'        => 'trial',
                'status'               => 'trialing',
                'is_current'           => 1,
                'price'                => '0.00',
                'currency_code'        => $plan['currency_code'],
                'started_at'           => $today,
                'current_period_start' => $today,
                'current_period_end'   => $end,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Veri migration'ı; geri alma CreateBilling::down ile tablo silinerek yapılır.
    }
}
