<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * .env içindeki pixmenu.adminEmail / pixmenu.adminPassword ile ilk admini (süper admin) oluşturur.
 * Değerler yoksa hata verir. Sonraki adminler: `php spark admin:create`.
 */
final class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = trim((string) env('pixmenu.adminEmail', ''));
        $password = (string) env('pixmenu.adminPassword', '');

        if ($email === '' || $password === '') {
            throw new RuntimeException('AdminSeeder: .env içinde pixmenu.adminEmail ve pixmenu.adminPassword tanımlı olmalı.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('AdminSeeder: pixmenu.adminEmail geçerli bir e-posta değil.');
        }

        if ($this->db->table('admins')->where('email', $email)->countAllResults() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('admins')->insert([
            'name'          => 'Admin',
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => 'super_admin',
            'is_active'     => 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }
}
