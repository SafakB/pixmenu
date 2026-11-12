<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Varsayılan paketler (docs/ABONELIK_PLANI.md §2). Fiyatlar YER TUTUCUDUR;
 * admin panelden düzenlenir. İdempotent: var olan kod atlanır.
 */
final class PlanSeeder extends Seeder
{
    /**
     * code => [max_branches, custom_domain, custom_design, trial_days, monthly, yearly, featured, tr, en, açıklama tr]
     *
     * @var array<string, array{0: int|null, 1: int, 2: int, 3: int|null, 4: string, 5: string, 6: int, 7: string, 8: string, 9: string}>
     */
    public const PLANS = [
        'demo'       => [1, 0, 0, 14, '0.00', '0.00', 0, 'Demo', 'Demo', 'Ücretsiz deneme: tek şube, temel menü özellikleri.'],
        'standard'   => [1, 1, 0, null, '499.00', '4990.00', 0, 'Standart', 'Standard', 'Tek şubeli işletmeler için; özel alan adı dahil.'],
        'pro'        => [null, 1, 0, null, '999.00', '9990.00', 1, 'Pro', 'Pro', 'Sınırsız şube ve özel alan adı.'],
        'enterprise' => [null, 1, 1, null, '1999.00', '19990.00', 0, 'Kurumsal', 'Enterprise', 'Sınırsız şube, özel alan adı ve özel menü tasarımı.'],
    ];

    public function run(): void
    {
        $langs = [];

        foreach ($this->db->table('languages')->whereIn('code', ['tr', 'en'])->get()->getResultArray() as $lang) {
            $langs[$lang['code']] = (int) $lang['id'];
        }

        $now   = date('Y-m-d H:i:s');
        $order = 0;

        foreach (self::PLANS as $code => [$max, $domain, $design, $trial, $monthly, $yearly, $featured, $tr, $en, $desc]) {
            $existing = $this->db->table('plans')->where('code', $code)->get()->getRowArray();

            if ($existing !== null) {
                $order++;

                continue;
            }

            $this->db->table('plans')->insert([
                'code'                => $code,
                'max_branches'        => $max,
                'allow_custom_domain' => $domain,
                'allow_custom_design' => $design,
                'trial_days'          => $trial,
                'price_monthly'       => $monthly,
                'price_yearly'        => $yearly,
                'currency_code'       => 'TRY',
                'is_featured'         => $featured,
                'is_active'           => 1,
                'sort_order'          => $order++,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
            $id = (int) $this->db->insertID();

            if (isset($langs['tr'])) {
                $this->db->table('plan_translations')->insert(['plan_id' => $id, 'language_id' => $langs['tr'], 'name' => $tr, 'description' => $desc]);
            }

            if (isset($langs['en'])) {
                $this->db->table('plan_translations')->insert(['plan_id' => $id, 'language_id' => $langs['en'], 'name' => $en, 'description' => null]);
            }
        }
    }
}
