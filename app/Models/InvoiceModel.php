<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Faturalar (tenant). Silinmez; yalnızca `void` edilir.
 * "Gecikmiş" ayrı durum değildir: status = pending AND due_date < bugün.
 */
final class InvoiceModel extends TenantModel
{
    protected $table         = 'invoices';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'restaurant_id', 'subscription_id', 'number', 'plan_id', 'description', 'period_start', 'period_end',
        'subtotal', 'discount', 'tax_rate', 'tax_amount', 'total', 'currency_code', 'status', 'issued_at',
        'due_date', 'paid_at', 'payment_method', 'payment_reference', 'paid_marked_by',
        'issued_notified_at', 'reminder_notified_at', 'overdue_notified_at', 'notes',
    ];

    public const METHODS = ['bank_transfer', 'cash', 'card', 'other'];

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(?int $limit = null): array
    {
        return $this->orderBy('issued_at', 'DESC')->orderBy('id', 'DESC')->findAll($limit);
    }

    public function existsForPeriod(int $subscriptionId, string $periodStart): bool
    {
        return $this->where('subscription_id', $subscriptionId)
            ->where('period_start', $periodStart)
            ->countAllResults() > 0;
    }

    /**
     * Vadesi verilen tarihten ÖNCE olan açık faturalar.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingDueBefore(string $date): array
    {
        return $this->where('status', 'pending')->where('due_date <', $date)->orderBy('due_date', 'ASC')->findAll();
    }

    /**
     * Aboneliğin ödenmemiş (en yeni) faturası — self-servis kayıt/geçiş sonrası ödeme ekranı için.
     *
     * @return array<string, mixed>|null
     */
    public function openForSubscription(int $subscriptionId): ?array
    {
        return $this->where('subscription_id', $subscriptionId)
            ->where('status', 'pending')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        return $this->where('status', 'pending')->orderBy('due_date', 'ASC')->findAll();
    }
}
