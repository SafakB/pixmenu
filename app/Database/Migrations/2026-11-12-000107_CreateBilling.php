<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\BaseMigration;

/**
 * Abonelik, faturalama ve özel alan adı şeması (docs/ABONELIK_PLANI.md §3).
 */
final class CreateBilling extends BaseMigration
{
    public function up(): void
    {
        $money = ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => '0.00'];
        $dt    = ['type' => 'DATETIME', 'null' => true];

        // plans (global)
        $this->forge->addField([
            'id'                  => $this->idField(),
            'code'                => ['type' => 'VARCHAR', 'constraint' => 30],
            'max_branches'        => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true], // NULL = sınırsız
            'allow_custom_domain' => $this->flagField(0),
            'allow_custom_design' => $this->flagField(0),
            'trial_days'          => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true], // dolu = deneme paketi
            'price_monthly'       => $money,
            'price_yearly'        => $money,
            'currency_code'       => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'TRY'],
            'is_featured'         => $this->flagField(0),
            'is_active'           => $this->flagField(),
            'sort_order'          => $this->sortField(),
        ] + $this->timestampFields());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->create('plans');

        $this->createTranslationTable('plan_translations', 'plan_id', 'plans', [
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
        ]);

        // subscriptions (tenant)
        $this->forge->addField([
            'id'                     => $this->idField(),
            'restaurant_id'          => $this->refField(),
            'plan_id'                => $this->refField(),
            'billing_cycle'          => ['type' => 'ENUM', 'constraint' => ['trial', 'monthly', 'yearly']],
            'status'                 => ['type' => 'ENUM', 'constraint' => ['trialing', 'active', 'past_due', 'suspended', 'ended']],
            'is_current'             => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => true],
            'price'                  => $money,
            'currency_code'          => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'TRY'],
            'started_at'             => ['type' => 'DATE'],
            'current_period_start'   => ['type' => 'DATE'],
            'current_period_end'     => ['type' => 'DATE'],
            'cancel_at_period_end'   => $this->flagField(0),
            'grace_days'             => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'suspended_at'           => $dt,
            'ended_at'               => $dt,
            'trial_reminder_sent_at' => $dt,
            'notes'                  => ['type' => 'TEXT', 'null' => true],
        ] + $this->timestampFields());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['restaurant_id', 'is_current']);   // restoran başına tek güncel abonelik
        $this->forge->addUniqueKey(['id', 'restaurant_id']);           // bileşik FK hedefi
        $this->forge->addKey(['status', 'current_period_end']);
        $this->forge->addForeignKey('restaurant_id', 'restaurants', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('plan_id', 'plans', 'id', '', 'RESTRICT');
        $this->create('subscriptions');

        // invoices (tenant) — silinmez, yalnızca void edilir
        $this->forge->addField([
            'id'                   => $this->idField(),
            'restaurant_id'        => $this->refField(),
            'subscription_id'      => $this->refField(),
            'number'               => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'plan_id'              => $this->refField(),
            'description'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'period_start'         => ['type' => 'DATE'],
            'period_end'           => ['type' => 'DATE'],
            'subtotal'             => $money,
            'discount'             => $money,
            'tax_rate'             => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '0.00'],
            'tax_amount'           => $money,
            'total'                => $money,
            'currency_code'        => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'TRY'],
            'status'               => ['type' => 'ENUM', 'constraint' => ['pending', 'paid', 'void'], 'default' => 'pending'],
            'issued_at'            => ['type' => 'DATE'],
            'due_date'             => ['type' => 'DATE'],
            'paid_at'              => $dt,
            'payment_method'       => ['type' => 'ENUM', 'constraint' => ['bank_transfer', 'cash', 'card', 'other'], 'null' => true],
            'payment_reference'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'paid_marked_by'       => $this->refField(true),
            'issued_notified_at'   => $dt,
            'reminder_notified_at' => $dt,
            'overdue_notified_at'  => $dt,
            'notes'                => ['type' => 'TEXT', 'null' => true],
        ] + $this->timestampFields());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('number');
        $this->forge->addUniqueKey(['subscription_id', 'period_start']);  // aynı dönem iki kez faturalanamaz
        $this->forge->addKey(['status', 'due_date']);
        $this->forge->addKey('restaurant_id');
        $this->forge->addForeignKey('restaurant_id', 'restaurants', 'id', '', 'CASCADE');
        $this->forge->addForeignKey(['subscription_id', 'restaurant_id'], 'subscriptions', ['id', 'restaurant_id'], '', 'RESTRICT');
        $this->forge->addForeignKey('plan_id', 'plans', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('paid_marked_by', 'admins', 'id', '', 'SET NULL');
        $this->create('invoices');

        // restaurant_domains (tenant)
        $this->forge->addField([
            'id'            => $this->idField(),
            'restaurant_id' => $this->refField(),
            'domain'        => ['type' => 'VARCHAR', 'constraint' => 190],
            'is_active'     => $this->flagField(),
            'verified_at'   => $dt,
        ] + $this->timestampFields());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('restaurant_id');
        $this->forge->addUniqueKey('domain');
        $this->forge->addForeignKey('restaurant_id', 'restaurants', 'id', '', 'CASCADE');
        $this->create('restaurant_domains');

        // restaurants: askı durumu (tek yazan SubscriptionService)
        $this->forge->addColumn('restaurants', [
            'suspended_at'      => $dt + ['after' => 'is_active'],
            'suspension_reason' => [
                'type'       => 'ENUM',
                'constraint' => ['overdue', 'trial_expired', 'no_subscription', 'manual'],
                'null'       => true,
                'after'      => 'suspended_at',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('restaurants', ['suspended_at', 'suspension_reason']);
        $this->forge->dropTable('restaurant_domains', true);
        $this->forge->dropTable('invoices', true);
        $this->forge->dropTable('subscriptions', true);
        $this->forge->dropTable('plan_translations', true);
        $this->forge->dropTable('plans', true);
    }
}
