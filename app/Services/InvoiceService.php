<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Libraries\Billing\Period;
use App\Models\InvoiceModel;
use App\Models\RestaurantModel;
use App\Services\Concerns\Transactional;

/**
 * Fatura kesimi, ödeme ve iptal — docs/ABONELIK_PLANI.md §3, §6.
 *
 * Tutar hesabı kuruş (tam sayı) üzerinden yapılır:
 *   matrah = ara toplam − indirim, KDV = round(matrah × oran / 100), toplam = matrah + KDV.
 */
final class InvoiceService
{
    use Transactional;

    public const METHOD_LABELS = [
        'bank_transfer' => 'Havale / EFT',
        'cash'          => 'Nakit',
        'card'          => 'Kredi kartı',
        'other'         => 'Diğer',
    ];

    /**
     * Abonelik dönemi için fatura keser. Aynı (abonelik, dönem başı) için ikinci fatura
     * DB'deki UNIQUE anahtar nedeniyle kesilemez; çağıran önce existsForPeriod() ile bakar.
     *
     * @param array<string, mixed> $subscription
     */
    public function issue(
        array $subscription,
        string $periodStart,
        string $periodEnd,
        string $issuedAt,
        string $dueDate,
        string $discount = '0.00',
        ?string $notes = null,
    ): int {
        $restaurantId = (int) $subscription['restaurant_id'];
        $subtotal     = self::cents((string) $subscription['price']);
        $discountC    = self::cents($discount);

        if ($discountC < 0 || $discountC > $subtotal) {
            throw ValidationException::single('discount', lang('App.invoice.discountInvalid'));
        }

        // Restoran geneli yüzdelik indirim + (varsa) tek seferlik TL indirimi; ara toplamı aşamaz.
        $restaurant = (new RestaurantModel())->withDeleted()->find($restaurantId) ?? [];
        $discountC  = min($subtotal, $discountC + self::percentDiscount($restaurant, $periodStart, $subtotal));

        $config = config('Billing');
        $rate   = (float) $config->taxRate;
        ['tax' => $tax, 'taxable' => $taxable] = self::totals($subtotal, $discountC, $rate);
        $model  = (new InvoiceModel())->forRestaurant($restaurantId);

        return $this->atomic(function () use ($model, $subscription, $periodStart, $periodEnd, $issuedAt, $dueDate, $subtotal, $discountC, $rate, $tax, $taxable, $notes, $config): int {
            $id = (int) $model->insert([
                'restaurant_id'   => (int) $subscription['restaurant_id'],
                'subscription_id' => (int) $subscription['id'],
                'plan_id'         => (int) $subscription['plan_id'],
                'description'     => mb_substr($this->describe($subscription, $periodStart, $periodEnd), 0, 255),
                'period_start'    => $periodStart,
                'period_end'      => $periodEnd,
                'subtotal'        => self::money($subtotal),
                'discount'        => self::money($discountC),
                'tax_rate'        => number_format($rate, 2, '.', ''),
                'tax_amount'      => self::money($tax),
                'total'           => self::money($taxable + $tax),
                'currency_code'   => (string) $subscription['currency_code'],
                'status'          => 'pending',
                'issued_at'       => $issuedAt,
                'due_date'        => $dueDate,
                'notes'           => $notes === '' ? null : $notes,
            ]);

            $model->update($id, [
                'number' => sprintf('%s-%s-%06d', $config->invoicePrefix, substr($issuedAt, 0, 4), $id),
            ]);

            return $id;
        });
    }

    /**
     * Ödenmemiş faturanın indirimini (TL, KDV hariç) değiştirir; KDV ve toplam faturanın
     * kendi KDV oranıyla yeniden hesaplanır. Girilen tutar mevcut indirimin yerine geçer.
     *
     * @param array<string, mixed> $invoice
     */
    public function updateDiscount(array $invoice, string $discount): void
    {
        if ($invoice['status'] !== 'pending') {
            throw new BusinessRuleException(lang('App.invoice.notPending'));
        }

        $subtotal  = self::cents((string) $invoice['subtotal']);
        $discountC = self::cents($discount);

        if ($discountC < 0 || $discountC > $subtotal) {
            throw ValidationException::single('discount', lang('App.invoice.discountInvalid'));
        }

        ['tax' => $tax, 'taxable' => $taxable] = self::totals($subtotal, $discountC, (float) $invoice['tax_rate']);

        (new InvoiceModel())->forRestaurant((int) $invoice['restaurant_id'])->update((int) $invoice['id'], [
            'discount'   => self::money($discountC),
            'tax_amount' => self::money($tax),
            'total'      => self::money($taxable + $tax),
        ]);
    }

    /**
     * Ödendi işaretler; ardından restoranın durumu yeniden değerlendirilir (askı kalkabilir).
     *
     * @param array<string, mixed> $invoice
     */
    public function markPaid(array $invoice, string $method, string $reference, string $paidAt, ?int $adminId, ?string $today = null): void
    {
        if ($invoice['status'] !== 'pending') {
            throw new BusinessRuleException(lang('App.invoice.notPending'));
        }

        $errors = [];

        if (! isset(self::METHOD_LABELS[$method])) {
            $errors['payment_method'] = lang('App.invoice.methodInvalid');
        }

        if (! Period::isValidDate($paidAt)) {
            $errors['paid_at'] = lang('App.invoice.dateInvalid');
        }

        if (mb_strlen($reference) > 100) {
            $errors['payment_reference'] = lang('App.invoice.referenceTooLong');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $restaurantId = (int) $invoice['restaurant_id'];

        $this->atomic(static function () use ($invoice, $restaurantId, $method, $reference, $paidAt, $adminId, $today): void {
            (new InvoiceModel())->forRestaurant($restaurantId)->update((int) $invoice['id'], [
                'status'            => 'paid',
                'paid_at'           => $paidAt . ' ' . date('H:i:s'),
                'payment_method'    => $method,
                'payment_reference' => $reference === '' ? null : $reference,
                'paid_marked_by'    => $adminId,
            ]);

            (new SubscriptionService())->reconcile($restaurantId, $today);
        });
    }

    /**
     * Yalnızca açık (pending) fatura iptal edilir. Fatura silinmez.
     *
     * @param array<string, mixed> $invoice
     */
    public function void(array $invoice, ?string $today = null): void
    {
        if ($invoice['status'] !== 'pending') {
            throw new BusinessRuleException(lang('App.invoice.notPending'));
        }

        $restaurantId = (int) $invoice['restaurant_id'];

        $this->atomic(static function () use ($invoice, $restaurantId, $today): void {
            (new InvoiceModel())->forRestaurant($restaurantId)->update((int) $invoice['id'], ['status' => 'void']);
            (new SubscriptionService())->reconcile($restaurantId, $today);
        });
    }

    /**
     * Görünüm durumu: paid | void | overdue | pending
     *
     * @param array<string, mixed> $invoice
     */
    public static function displayStatus(array $invoice, ?string $today = null): string
    {
        if ($invoice['status'] === 'pending' && (string) $invoice['due_date'] < ($today ?? Period::today())) {
            return 'overdue';
        }

        return (string) $invoice['status'];
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $subscription
     */
    private function describe(array $subscription, string $start, string $end): string
    {
        $cycle = lang('App.subscription.cycle.' . $subscription['billing_cycle']);

        return sprintf(
            'PixMenu %s paketi — %s (%s – %s)',
            (new PlanService())->name((int) $subscription['plan_id']),
            $cycle,
            date('d.m.Y', (int) strtotime($start)),
            date('d.m.Y', (int) strtotime($end)),
        );
    }

    /**
     * Restoranın yüzdelik indirimi (kuruş). Dönem başı indirim bitiş tarihinden sonraysa 0.
     *
     * @param array<string, mixed> $restaurant
     */
    public static function percentDiscount(array $restaurant, string $periodStart, int $subtotal): int
    {
        $percent = (float) ($restaurant['discount_percent'] ?? 0);
        $until   = $restaurant['discount_until'] ?? null;

        if ($percent <= 0 || ($until !== null && $until !== '' && $periodStart > (string) $until)) {
            return 0;
        }

        return min($subtotal, (int) round($subtotal * min($percent, 100.0) / 100));
    }

    /**
     * @return array{taxable: int, tax: int}
     */
    private static function totals(int $subtotal, int $discount, float $rate): array
    {
        $taxable = $subtotal - $discount;

        return ['taxable' => $taxable, 'tax' => (int) round($taxable * $rate / 100)];
    }

    public static function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
