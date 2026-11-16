<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Self-servis kayıt + Google ile giriş:
 *  - google_id: Google hesabının `sub` değeri (global unique; NULL = bağlı değil).
 *  - email_verified_at: e-posta sahipliği kanıtlanmış mı (Google veya güvenilir oluşturan).
 *  - password_hash NULL olabilir: yalnızca Google ile giren hesapların şifresi yoktur.
 *
 * Bu migration'dan önce tüm kullanıcıları admin/owner açtığı için mevcut kayıtlar doğrulanmış sayılır.
 */
final class AddGoogleAuthToRestaurantUsers extends BaseMigration
{
    public function up(): void
    {
        $this->forge->addColumn('restaurant_users', [
            'email_verified_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'email'],
            'google_id'         => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'password_hash'],
        ]);

        $this->forge->modifyColumn('restaurant_users', [
            'password_hash' => ['name' => 'password_hash', 'type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);

        $this->forge->addUniqueKey('google_id', 'restaurant_users_google_id');
        $this->forge->processIndexes('restaurant_users');

        $this->db->table('restaurant_users')
            ->set('email_verified_at', 'COALESCE(created_at, NOW())', false)
            ->update();
    }

    public function down(): void
    {
        // Şifresiz (yalnızca Google) hesaplar hiçbir şifreyle doğrulanamayan bir değer alır.
        $this->db->table('restaurant_users')->set('password_hash', '!')->where('password_hash', null)->update();

        $this->forge->dropKey('restaurant_users', 'restaurant_users_google_id', false);
        $this->forge->dropColumn('restaurant_users', ['google_id', 'email_verified_at']);
        $this->forge->modifyColumn('restaurant_users', [
            'password_hash' => ['name' => 'password_hash', 'type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
        ]);
    }
}
