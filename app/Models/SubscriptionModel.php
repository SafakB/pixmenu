<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Abonelikler (tenant). Restoran başına en fazla bir satırda is_current = 1;
 * geçmiş aboneliklerde is_current = NULL (UNIQUE(restaurant_id, is_current)).
 */
final class SubscriptionModel extends TenantModel
{
    protected $table         = 'subscriptions';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'restaurant_id', 'plan_id', 'billing_cycle', 'status', 'is_current', 'price', 'currency_code',
        'started_at', 'current_period_start', 'current_period_end', 'cancel_at_period_end', 'grace_days',
        'suspended_at', 'ended_at', 'trial_reminder_sent_at', 'notes',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        return $this->where('is_current', 1)->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(): array
    {
        return $this->orderBy('id', 'DESC')->findAll();
    }
}
