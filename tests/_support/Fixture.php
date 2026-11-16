<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Database\BaseConnection;

/**
 * İzolasyon testlerinin ortak verisi (spec §14):
 *
 *  Restoran A: owner A, manager A1 (yalnızca şube A1), şubeler A1 ve A2
 *  Restoran B: owner B, şube B1
 *  Paketler: demo, standard (1 şube + alan adı), pro (sınırsız), enterprise.
 *  A ve B güncel olarak `pro` paketinde (aylık, aktif, bugün başlayan dönem).
 *
 * Model/servis katmanını atlayıp doğrudan DB'ye yazar; böylece test edilen
 * kodun hatası fixture'ı etkilemez.
 */
final class Fixture
{
    public const PASSWORD = 'Test12345678';

    public int $adminId;

    /** @var array<string, int> code => id */
    public array $lang = [];

    /** @var array<string, int> code => id */
    public array $allergen = [];

    /** @var array<string, int> code => id */
    public array $plan = [];

    public int $subscriptionA;
    public int $subscriptionB;

    public int $restaurantA;
    public int $restaurantB;

    public int $branchA1;
    public int $branchA2;
    public int $branchB1;

    /** @var array<string, mixed> */
    public array $ownerA;

    /** @var array<string, mixed> */
    public array $managerA1;

    /** @var array<string, mixed> */
    public array $ownerB;

    public int $categoryA1;
    public int $categoryA1b;
    public int $categoryA2;
    public int $categoryB1;

    public int $productA1;
    public int $productA1b;
    public int $productA2;
    public int $productB1;

    private string $now;
    private string $hash;

    public function __construct(private readonly BaseConnection $db)
    {
        $this->now  = date('Y-m-d H:i:s');
        $this->hash = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);

        $this->build();
    }

    private function build(): void
    {
        $this->adminId = $this->insert('admins', [
            'name' => 'Admin', 'email' => 'admin@test.local', 'password_hash' => $this->hash,
            'role' => 'super_admin', 'is_active' => 1, 'created_at' => $this->now, 'updated_at' => $this->now,
        ]);

        foreach ([['tr', 'Turkish', 'Türkçe'], ['en', 'English', 'English'], ['de', 'German', 'Deutsch']] as $i => [$code, $name, $native]) {
            $this->lang[$code] = $this->insert('languages', [
                'code' => $code, 'name' => $name, 'native_name' => $native, 'direction' => 'ltr',
                'is_active' => 1, 'sort_order' => $i, 'created_at' => $this->now, 'updated_at' => $this->now,
            ]);
        }

        foreach ([['TRY', 'Türk Lirası', '₺', 1], ['USD', 'ABD Doları', '$', 1], ['EUR', 'Euro', '€', 1], ['XAU', 'Altın', 'XAU', 0]] as $i => [$code, $name, $symbol, $active]) {
            $this->db->table('currencies')->insert([
                'code' => $code, 'name' => $name, 'symbol' => $symbol, 'decimals' => 2,
                'is_active' => $active, 'sort_order' => $i, 'created_at' => $this->now, 'updated_at' => $this->now,
            ]);
        }

        // Migration'ın eklediği `standart` + testler için bir aktif ve bir pasif şablon.
        foreach ([['standart', 'Standart', 1], ['modern', 'Modern', 1], ['eski', 'Eski', 0]] as $i => [$code, $name, $active]) {
            $this->db->table('menu_templates')->insert([
                'code' => $code, 'name' => $name, 'is_active' => $active, 'sort_order' => $i,
                'created_at' => $this->now, 'updated_at' => $this->now,
            ]);
        }

        foreach ([['gluten', 'Gluten', 'Gluten', 1], ['milk', 'Süt', 'Milk', 1], ['lupin', 'Acı bakla', 'Lupin', 0]] as $i => [$code, $tr, $en, $active]) {
            $id = $this->insert('allergens', [
                'code' => $code, 'is_active' => $active, 'sort_order' => $i,
                'created_at' => $this->now, 'updated_at' => $this->now,
            ]);
            $this->allergen[$code] = $id;
            $this->insert('allergen_translations', ['allergen_id' => $id, 'language_id' => $this->lang['tr'], 'name' => $tr]);
            $this->insert('allergen_translations', ['allergen_id' => $id, 'language_id' => $this->lang['en'], 'name' => $en]);
        }

        foreach ([
            ['demo', 1, 0, 0, 14, '0.00', '0.00', 'Demo'],
            ['standard', 1, 1, 0, null, '500.00', '5000.00', 'Standart'],
            ['pro', null, 1, 0, null, '1000.00', '10000.00', 'Pro'],
            ['enterprise', null, 1, 1, null, '2000.00', '20000.00', 'Kurumsal'],
        ] as $i => [$code, $max, $domain, $design, $trial, $monthly, $yearly, $name]) {
            $this->plan[$code] = $this->insert('plans', [
                'code' => $code, 'max_branches' => $max, 'allow_custom_domain' => $domain, 'allow_custom_design' => $design,
                'trial_days' => $trial, 'price_monthly' => $monthly, 'price_yearly' => $yearly, 'currency_code' => 'TRY',
                'is_featured' => $code === 'pro' ? 1 : 0, 'is_active' => 1, 'sort_order' => $i,
                'created_at' => $this->now, 'updated_at' => $this->now,
            ]);
            $this->insert('plan_translations', ['plan_id' => $this->plan[$code], 'language_id' => $this->lang['tr'], 'name' => $name]);
        }

        // --- Restoran A ---
        $this->restaurantA = $this->restaurant('restoran-a', 'Restoran A', 'Restaurant A');
        $this->branchA1    = $this->branch($this->restaurantA, 'a1', 'aaaaaaaaa1', 'Şube A1', 0);
        $this->branchA2    = $this->branch($this->restaurantA, 'a2', 'aaaaaaaaa2', 'Şube A2', 1);
        $this->ownerA      = $this->user($this->restaurantA, 'Owner A', 'owner-a@test.local', 'owner');
        $this->managerA1   = $this->user($this->restaurantA, 'Manager A1', 'manager-a1@test.local', 'manager');
        $this->assign($this->restaurantA, (int) $this->managerA1['id'], $this->branchA1);

        $this->categoryA1  = $this->category($this->restaurantA, $this->branchA1, 'Çorbalar A1', 'Soups A1', 0);
        $this->categoryA1b = $this->category($this->restaurantA, $this->branchA1, 'Tatlılar A1', 'Desserts A1', 1);
        $this->categoryA2  = $this->category($this->restaurantA, $this->branchA2, 'Çorbalar A2', 'Soups A2', 0);
        $this->productA1   = $this->product($this->restaurantA, $this->branchA1, $this->categoryA1, 'Mercimek A1', 'Lentil A1', '100.00', 0);
        $this->productA1b  = $this->product($this->restaurantA, $this->branchA1, $this->categoryA1, 'Ezogelin A1', 'Ezogelin A1', '110.00', 1);
        $this->productA2   = $this->product($this->restaurantA, $this->branchA2, $this->categoryA2, 'Mercimek A2', 'Lentil A2', '120.00', 0);

        // --- Restoran B ---
        $this->restaurantB = $this->restaurant('restoran-b', 'Restoran B', 'Restaurant B');
        $this->branchB1    = $this->branch($this->restaurantB, 'b1', 'bbbbbbbbb1', 'Şube B1', 0);
        $this->ownerB      = $this->user($this->restaurantB, 'Owner B', 'owner-b@test.local', 'owner');
        $this->categoryB1  = $this->category($this->restaurantB, $this->branchB1, 'Gizli Kategori B', 'Secret Category B', 0);
        $this->productB1   = $this->product($this->restaurantB, $this->branchB1, $this->categoryB1, 'Gizli Ürün B', 'Secret Product B', '999.00', 0);

        $this->subscriptionA = $this->subscription($this->restaurantA, 'pro');
        $this->subscriptionB = $this->subscription($this->restaurantB, 'pro');
    }

    /**
     * Belirli roldeki ek admin (Config\AdminRoles). E-posta: {rol}@test.local
     */
    public function createAdmin(string $role, int $active = 1, ?string $email = null): int
    {
        return $this->insert('admins', [
            'name' => ucfirst($role), 'email' => $email ?? $role . '@test.local', 'password_hash' => $this->hash,
            'role' => $role, 'is_active' => $active, 'created_at' => $this->now, 'updated_at' => $this->now,
        ]);
    }

    /**
     * Güncel abonelik ekler (varsa eskisini sonlandırmaz; çağıran dikkat eder).
     */
    public function subscription(int $restaurantId, string $planCode, string $cycle = 'monthly', ?string $start = null, ?string $end = null, string $status = 'active'): int
    {
        $start ??= date('Y-m-d');
        $end   ??= date('Y-m-d', strtotime($start . ($cycle === 'trial' ? ' +13 days' : ' +1 month -1 day')));
        $plan    = $this->db->table('plans')->where('id', $this->plan[$planCode])->get()->getRowArray();

        return $this->insert('subscriptions', [
            'restaurant_id' => $restaurantId, 'plan_id' => $plan['id'], 'billing_cycle' => $cycle,
            'status' => $cycle === 'trial' && $status === 'active' ? 'trialing' : $status, 'is_current' => 1,
            'price' => $cycle === 'yearly' ? $plan['price_yearly'] : ($cycle === 'trial' ? '0.00' : $plan['price_monthly']),
            'currency_code' => 'TRY', 'started_at' => $start, 'current_period_start' => $start, 'current_period_end' => $end,
            'created_at' => $this->now, 'updated_at' => $this->now,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    public function restaurant(string $slug, string $nameTr, string $nameEn): int
    {
        $id = $this->insert('restaurants', [
            'slug' => $slug, 'default_language_id' => $this->lang['tr'], 'currency_code' => 'TRY',
            'is_active' => 1, 'created_at' => $this->now, 'updated_at' => $this->now,
        ]);
        $this->insert('restaurant_translations', ['restaurant_id' => $id, 'language_id' => $this->lang['tr'], 'name' => $nameTr]);
        $this->insert('restaurant_translations', ['restaurant_id' => $id, 'language_id' => $this->lang['en'], 'name' => $nameEn]);
        $this->db->table('restaurant_languages')->insert(['restaurant_id' => $id, 'language_id' => $this->lang['tr'], 'sort_order' => 0, 'is_active' => 1]);
        $this->db->table('restaurant_languages')->insert(['restaurant_id' => $id, 'language_id' => $this->lang['en'], 'sort_order' => 1, 'is_active' => 1]);

        return $id;
    }

    public function branch(int $restaurantId, string $slug, string $code, string $name, int $sort): int
    {
        $id = $this->insert('branches', [
            'restaurant_id' => $restaurantId, 'slug' => $slug, 'public_code' => $code,
            'default_language_id' => $this->lang['tr'], 'is_active' => 1, 'sort_order' => $sort,
            'template_code' => 'standart', // "eski" şube: DB varsayılanından (modern) bağımsız
            'created_at' => $this->now, 'updated_at' => $this->now,
        ]);
        $this->insert('branch_translations', ['branch_id' => $id, 'language_id' => $this->lang['tr'], 'name' => $name, 'address' => $name . ' adresi']);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function user(int $restaurantId, string $name, string $email, string $role, int $active = 1): array
    {
        $id = $this->insert('restaurant_users', [
            'restaurant_id' => $restaurantId, 'name' => $name, 'email' => $email,
            'password_hash' => $this->hash, 'role' => $role, 'is_active' => $active,
            'created_at' => $this->now, 'updated_at' => $this->now,
        ]);

        return $this->db->table('restaurant_users')->where('id', $id)->get()->getRowArray();
    }

    public function assign(int $restaurantId, int $userId, int $branchId): void
    {
        $this->db->table('restaurant_user_branches')->insert([
            'restaurant_id' => $restaurantId, 'user_id' => $userId, 'branch_id' => $branchId,
        ]);
    }

    public function category(int $restaurantId, int $branchId, string $nameTr, ?string $nameEn, int $sort, int $active = 1): int
    {
        $id = $this->insert('categories', [
            'restaurant_id' => $restaurantId, 'branch_id' => $branchId, 'is_active' => $active,
            'sort_order' => $sort, 'created_at' => $this->now, 'updated_at' => $this->now,
        ]);
        $this->insert('category_translations', ['category_id' => $id, 'language_id' => $this->lang['tr'], 'name' => $nameTr]);

        if ($nameEn !== null) {
            $this->insert('category_translations', ['category_id' => $id, 'language_id' => $this->lang['en'], 'name' => $nameEn]);
        }

        return $id;
    }

    public function product(int $restaurantId, int $branchId, int $categoryId, string $nameTr, ?string $nameEn, string $price, int $sort, int $active = 1): int
    {
        $id = $this->insert('products', [
            'restaurant_id' => $restaurantId, 'branch_id' => $branchId, 'category_id' => $categoryId,
            'price' => $price, 'is_active' => $active, 'sort_order' => $sort,
            'created_at' => $this->now, 'updated_at' => $this->now,
        ]);
        $this->insert('product_translations', ['product_id' => $id, 'language_id' => $this->lang['tr'], 'name' => $nameTr]);

        if ($nameEn !== null) {
            $this->insert('product_translations', ['product_id' => $id, 'language_id' => $this->lang['en'], 'name' => $nameEn]);
        }

        return $id;
    }

    public function productAllergen(int $restaurantId, int $productId, int $allergenId, string $level = 'contains'): void
    {
        $this->db->table('product_allergens')->insert([
            'restaurant_id' => $restaurantId, 'product_id' => $productId,
            'allergen_id' => $allergenId, 'level' => $level,
        ]);
    }
}
