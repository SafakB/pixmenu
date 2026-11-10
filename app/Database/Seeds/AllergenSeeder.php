<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * AB 14 alerjeni (tr + en). İkonlar boş bırakılır.
 */
final class AllergenSeeder extends Seeder
{
    public function run(): void
    {
        $allergens = [
            'gluten'      => ['Gluten', 'Gluten'],
            'crustaceans' => ['Kabuklular', 'Crustaceans'],
            'eggs'        => ['Yumurta', 'Eggs'],
            'fish'        => ['Balık', 'Fish'],
            'peanuts'     => ['Yer fıstığı', 'Peanuts'],
            'soybeans'    => ['Soya', 'Soybeans'],
            'milk'        => ['Süt', 'Milk'],
            'nuts'        => ['Sert kabuklu yemişler', 'Tree nuts'],
            'celery'      => ['Kereviz', 'Celery'],
            'mustard'     => ['Hardal', 'Mustard'],
            'sesame'      => ['Susam', 'Sesame'],
            'sulphites'   => ['Sülfitler', 'Sulphites'],
            'lupin'       => ['Acı bakla', 'Lupin'],
            'molluscs'    => ['Yumuşakçalar', 'Molluscs'],
        ];

        $langIds = [];

        foreach ($this->db->table('languages')->whereIn('code', ['tr', 'en'])->get()->getResultArray() as $lang) {
            $langIds[$lang['code']] = (int) $lang['id'];
        }

        if (! isset($langIds['tr'], $langIds['en'])) {
            throw new RuntimeException('AllergenSeeder: önce LanguageSeeder çalıştırılmalı (tr ve en dilleri gerekli).');
        }

        $now   = date('Y-m-d H:i:s');
        $order = 0;

        foreach ($allergens as $code => [$tr, $en]) {
            $sort = $order++;

            if ($this->db->table('allergens')->where('code', $code)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('allergens')->insert([
                'code'       => $code,
                'icon_path'  => null,
                'is_active'  => 1,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) $this->db->insertID();

            $this->db->table('allergen_translations')->insertBatch([
                ['allergen_id' => $id, 'language_id' => $langIds['tr'], 'name' => $tr, 'description' => null],
                ['allergen_id' => $id, 'language_id' => $langIds['en'], 'name' => $en, 'description' => null],
            ]);
        }
    }
}
