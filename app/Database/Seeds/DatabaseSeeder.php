<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LanguageSeeder::class);
        $this->call(AllergenSeeder::class);
        $this->call(AdminSeeder::class);
    }
}
