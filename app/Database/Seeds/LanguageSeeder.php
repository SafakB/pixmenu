<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['tr', 'Turkish', 'Türkçe', 'ltr'],
            ['en', 'English', 'English', 'ltr'],
            ['de', 'German', 'Deutsch', 'ltr'],
            ['ru', 'Russian', 'Русский', 'ltr'],
            ['ar', 'Arabic', 'العربية', 'rtl'],
        ];

        $now     = date('Y-m-d H:i:s');
        $builder = $this->db->table('languages');

        foreach ($rows as $i => [$code, $name, $native, $direction]) {
            if ($this->db->table('languages')->where('code', $code)->countAllResults() > 0) {
                continue;
            }

            $builder->insert([
                'code'        => $code,
                'name'        => $name,
                'native_name' => $native,
                'direction'   => $direction,
                'is_active'   => 1,
                'sort_order'  => $i,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }
}
