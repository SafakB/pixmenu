<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Faker\Factory;
use RuntimeException;

/**
 * YALNIZCA development. İzolasyonu elle denemek için 2 restoran, her birinde
 * 2 şube, owner + manager, kategoriler, ürünler ve alerjen atamaları.
 *
 * Tüm demo kullanıcılarının parolası: Demo12345678
 */
final class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo12345678';

    public function run(): void
    {
        if (ENVIRONMENT !== 'development') {
            throw new RuntimeException('DemoSeeder yalnızca development ortamında çalışır.');
        }

        helper(['url', 'text']);

        $langs = [];

        foreach ($this->db->table('languages')->whereIn('code', ['tr', 'en'])->get()->getResultArray() as $lang) {
            $langs[$lang['code']] = (int) $lang['id'];
        }

        if (! isset($langs['tr'], $langs['en'])) {
            throw new RuntimeException('DemoSeeder: önce DatabaseSeeder çalıştırılmalı.');
        }

        $allergenIds = array_map('intval', array_column(
            $this->db->table('allergens')->select('id')->where('is_active', 1)->get()->getResultArray(),
            'id',
        ));

        $faker = Factory::create('tr_TR');
        $faker->seed(20261110);
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $now  = date('Y-m-d H:i:s');

        $restaurants = [
            'demo-lezzet' => ['Lezzet Sofrası', 'Lezzet Sofrasi Restaurant', ['Kadıköy', 'Beşiktaş']],
            'demo-anadolu' => ['Anadolu Mutfağı', 'Anatolian Kitchen', ['Alsancak', 'Karşıyaka']],
        ];

        $menu = [
            ['Çorbalar', 'Soups', [['Mercimek Çorbası', 'Lentil Soup'], ['Ezogelin Çorbası', 'Ezogelin Soup'], ['Tavuk Suyu Çorba', 'Chicken Soup']]],
            ['Ana Yemekler', 'Main Courses', [['Izgara Köfte', 'Grilled Meatballs'], ['Tavuk Şiş', 'Chicken Skewer'], ['Karnıyarık', 'Stuffed Eggplant'], ['Mantı', 'Turkish Dumplings']]],
            ['Tatlılar', 'Desserts', [['Sütlaç', 'Rice Pudding'], ['Baklava', 'Baklava'], ['Künefe', 'Kunefe']]],
            ['İçecekler', 'Beverages', [['Ayran', 'Ayran'], ['Türk Kahvesi', 'Turkish Coffee'], ['Çay', 'Tea']]],
        ];

        $this->db->transStart();

        foreach ($restaurants as $slug => [$nameTr, $nameEn, $branchNames]) {
            if ($this->db->table('restaurants')->where('slug', $slug)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('restaurants')->insert([
                'slug'                => $slug,
                'default_language_id' => $langs['tr'],
                'currency_code'       => 'TRY',
                'is_active'           => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
            $rid = (int) $this->db->insertID();

            $this->db->table('restaurant_translations')->insertBatch([
                ['restaurant_id' => $rid, 'language_id' => $langs['tr'], 'name' => $nameTr, 'description' => $faker->sentence(8)],
                ['restaurant_id' => $rid, 'language_id' => $langs['en'], 'name' => $nameEn, 'description' => null],
            ]);
            $this->db->table('restaurant_languages')->insertBatch([
                ['restaurant_id' => $rid, 'language_id' => $langs['tr'], 'sort_order' => 0, 'is_active' => 1],
                ['restaurant_id' => $rid, 'language_id' => $langs['en'], 'sort_order' => 1, 'is_active' => 1],
            ]);

            $branchIds = [];

            foreach ($branchNames as $i => $branchName) {
                $this->db->table('branches')->insert([
                    'restaurant_id'       => $rid,
                    'slug'                => url_title(convert_accented_characters($branchName), '-', true),
                    'public_code'         => bin2hex(random_bytes(5)),
                    'default_language_id' => $langs['tr'],
                    'phone'               => $faker->phoneNumber(),
                    'is_active'           => 1,
                    'sort_order'          => $i,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);
                $bid         = (int) $this->db->insertID();
                $branchIds[] = $bid;

                $this->db->table('branch_translations')->insertBatch([
                    ['branch_id' => $bid, 'language_id' => $langs['tr'], 'name' => $branchName, 'address' => $faker->address(), 'description' => null],
                    ['branch_id' => $bid, 'language_id' => $langs['en'], 'name' => $branchName, 'address' => null, 'description' => null],
                ]);

                foreach ($menu as $ci => [$catTr, $catEn, $products]) {
                    $this->db->table('categories')->insert([
                        'restaurant_id' => $rid,
                        'branch_id'     => $bid,
                        'is_active'     => 1,
                        'sort_order'    => $ci,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                    $cid = (int) $this->db->insertID();

                    $this->db->table('category_translations')->insertBatch([
                        ['category_id' => $cid, 'language_id' => $langs['tr'], 'name' => $catTr, 'description' => null],
                        ['category_id' => $cid, 'language_id' => $langs['en'], 'name' => $catEn, 'description' => null],
                    ]);

                    foreach ($products as $pi => [$prodTr, $prodEn]) {
                        $this->db->table('products')->insert([
                            'restaurant_id' => $rid,
                            'branch_id'     => $bid,
                            'category_id'   => $cid,
                            'price'         => number_format($faker->numberBetween(40, 450) + 0.9, 2, '.', ''),
                            'is_active'     => 1,
                            'sort_order'    => $pi,
                            'created_at'    => $now,
                            'updated_at'    => $now,
                        ]);
                        $pid = (int) $this->db->insertID();

                        $this->db->table('product_translations')->insertBatch([
                            ['product_id' => $pid, 'language_id' => $langs['tr'], 'name' => $prodTr, 'description' => $faker->sentence(6)],
                            ['product_id' => $pid, 'language_id' => $langs['en'], 'name' => $prodEn, 'description' => null],
                        ]);

                        if ($allergenIds !== [] && $faker->boolean(60)) {
                            foreach ($faker->randomElements($allergenIds, min(2, count($allergenIds))) as $aid) {
                                $this->db->table('product_allergens')->insert([
                                    'restaurant_id' => $rid,
                                    'product_id'    => $pid,
                                    'allergen_id'   => $aid,
                                    'level'         => $faker->randomElement(['contains', 'may_contain']),
                                ]);
                            }
                        }
                    }
                }
            }

            $this->db->table('restaurant_users')->insert([
                'restaurant_id' => $rid,
                'name'          => $nameTr . ' Sahibi',
                'email'         => 'owner@' . $slug . '.local',
                'password_hash' => $hash,
                'role'          => 'owner',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            $this->db->table('restaurant_users')->insert([
                'restaurant_id' => $rid,
                'name'          => $branchNames[0] . ' Yöneticisi',
                'email'         => 'manager@' . $slug . '.local',
                'password_hash' => $hash,
                'role'          => 'manager',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $managerId = (int) $this->db->insertID();

            // Manager yalnızca ilk şubeye atanır.
            $this->db->table('restaurant_user_branches')->insert([
                'restaurant_id' => $rid,
                'user_id'       => $managerId,
                'branch_id'     => $branchIds[0],
            ]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('DemoSeeder: veri yazılamadı.');
        }
    }
}
