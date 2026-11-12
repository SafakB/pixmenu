<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Billing\Period;
use App\Models\InvoiceModel;
use App\Models\SubscriptionModel;
use Throwable;

/**
 * Günlük faturalama işi (`php spark billing:run`) — docs/ABONELIK_PLANI.md §7.
 *
 * Her restoran kendi transaction'ında işlenir; biri hata verirse loglanır, diğerleri
 * devam eder. Tüm adımlar idempotenttir (aynı gün iki kez çalıştırmak ek etki üretmez).
 * "Bugün" parametre olarak verilir; --dry-run'da transaction geri alınır ve e-posta gitmez.
 */
final class BillingRunner
{
    /** @var array<string, int> */
    private array $stats = [];

    public function __construct(private readonly BillingNotifier $notifier = new BillingNotifier())
    {
    }

    /**
     * @param (callable(string): void)|null $log
     *
     * @return array<string, int>
     */
    public function run(string $today, bool $dryRun = false, ?callable $log = null): array
    {
        $this->stats = [
            'restaurants'      => 0,
            'periods_advanced' => 0,
            'ended'            => 0,
            'invoices_issued'  => 0,
            'issued_emails'    => 0,
            'reminders'        => 0,
            'trial_reminders'  => 0,
            'overdue'          => 0,
            'suspended'        => 0,
            'resumed'          => 0,
            'errors'           => 0,
        ];

        $ids = array_map('intval', array_column(
            db_connect()->table('restaurants')->select('id')->where('deleted_at', null)->orderBy('id', 'ASC')->get()->getResultArray(),
            'id',
        ));

        foreach ($ids as $restaurantId) {
            $this->stats['restaurants']++;

            try {
                $this->processRestaurant($restaurantId, $today, $dryRun);
            } catch (Throwable $e) {
                $this->stats['errors']++;
                $message = sprintf('billing:run restoran #%d hata: %s', $restaurantId, $e->getMessage());
                log_message('error', $message);

                if ($log !== null) {
                    $log($message);
                }
            }
        }

        return $this->stats;
    }

    private function processRestaurant(int $restaurantId, string $today, bool $dryRun): void
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $events = $this->steps($restaurantId, $today);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction başarısız.');
            }

            $dryRun ? $db->transRollback() : $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        if (! $dryRun) {
            $this->deliver($restaurantId, $events);
        }
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>}> commit sonrası gönderilecek bildirimler
     */
    private function steps(int $restaurantId, string $today): array
    {
        $config        = config('Billing');
        $subscriptions = new SubscriptionService();
        $invoiceSvc    = new InvoiceService();
        $subModel      = (new SubscriptionModel())->forRestaurant($restaurantId);
        $events        = [];
        $sub           = $subModel->current();

        // 1) Dönem ilerletme (askıdaki ve deneme abonelikleri ilerlemez)
        while ($sub !== null && $this->isBillable($sub) && (string) $sub['current_period_end'] < $today) {
            if ((int) $sub['cancel_at_period_end'] === 1) {
                $subscriptions->endSubscription($sub);
                $this->stats['ended']++;
                $sub = null;

                break;
            }

            $nextStart = Period::addDays((string) $sub['current_period_end'], 1);

            // Önceden kesilmiş yenileme faturası yoksa (ör. askıdan dönüş) dönem bugünden başlar.
            if ($nextStart < $today && ! $this->invoices($restaurantId)->existsForPeriod((int) $sub['id'], $nextStart)) {
                $nextStart = $today;
            }

            $subModel->update((int) $sub['id'], [
                'current_period_start' => $nextStart,
                'current_period_end'   => Period::end($nextStart, (string) $sub['billing_cycle']),
            ]);
            $this->stats['periods_advanced']++;
            $sub = $subModel->current();
        }

        // 2) Faturalar: içinde bulunulan dönemin faturası yoksa kes; dönem sonuna yaklaşınca yenileme faturası
        if ($sub !== null && $this->isBillable($sub) && InvoiceService::cents((string) $sub['price']) > 0) {
            $start = (string) $sub['current_period_start'];
            $end   = (string) $sub['current_period_end'];

            if (! $this->invoices($restaurantId)->existsForPeriod((int) $sub['id'], $start)) {
                $invoiceSvc->issue($sub, $start, $end, $today, Period::addDays($today, $config->paymentTermDays));
                $this->stats['invoices_issued']++;
            }

            $nextStart = Period::addDays($end, 1);

            if ((int) $sub['cancel_at_period_end'] !== 1
                && Period::daysBetween($today, $end) <= $config->invoiceLeadDays
                && ! $this->invoices($restaurantId)->existsForPeriod((int) $sub['id'], $nextStart)) {
                $invoiceSvc->issue($sub, $nextStart, Period::end($nextStart, (string) $sub['billing_cycle']), $today, $end);
                $this->stats['invoices_issued']++;
            }
        }

        $pending = $this->invoices($restaurantId)->pending();

        // Kesim bildirimi (cron'un veya admin'in kestiği, henüz bildirilmemiş açık faturalar)
        foreach ($pending as $invoice) {
            if ($invoice['issued_notified_at'] === null) {
                $events[] = ['invoice_issued', $invoice];
                $this->stats['issued_emails']++;
            }
        }

        // 3) Hatırlatmalar
        foreach ($pending as $invoice) {
            $daysLeft = Period::daysBetween($today, (string) $invoice['due_date']);

            if ($invoice['reminder_notified_at'] === null && $daysLeft >= 0 && $daysLeft <= $config->reminderDaysBeforeDue
                && (string) $invoice['issued_at'] < $today) {
                $events[] = ['invoice_reminder', $invoice];
                $this->stats['reminders']++;
            }
        }

        if ($sub !== null && $sub['status'] === 'trialing' && $sub['trial_reminder_sent_at'] === null) {
            $daysLeft = Period::daysBetween($today, (string) $sub['current_period_end']);

            if ($daysLeft >= 0 && $daysLeft <= $config->trialReminderDays) {
                $events[] = ['trial_reminder', $sub + ['days_left' => $daysLeft]];
                $this->stats['trial_reminders']++;
            }
        }

        // 4) Gecikme bildirimi
        foreach ($pending as $invoice) {
            if ((string) $invoice['due_date'] < $today && $invoice['overdue_notified_at'] === null) {
                $events[] = ['invoice_overdue', $invoice + ['grace_days' => $subscriptions->graceDays($sub)]];
                $this->stats['overdue']++;
            }
        }

        // 5-6) Durum: past_due / askıya alma (gecikme, deneme bitişi, abonelik yok) / yeniden açma
        $state = $subscriptions->reconcile($restaurantId, $today);

        if ($state['after'] !== null && $state['after'] !== $state['before']) {
            $events[] = ['suspended', ['reason' => $state['after']]];
            $this->stats['suspended']++;
        } elseif ($state['before'] !== null && $state['after'] === null) {
            $this->stats['resumed']++;
        }

        return $events;
    }

    /**
     * @param list<array{0: string, 1: array<string, mixed>}> $events
     */
    private function deliver(int $restaurantId, array $events): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($events as [$type, $payload]) {
            try {
                switch ($type) {
                    case 'invoice_issued':
                        if ($this->notifier->invoiceIssued($payload)) {
                            $this->invoices($restaurantId)->update((int) $payload['id'], ['issued_notified_at' => $now]);
                        }
                        break;

                    case 'invoice_reminder':
                        if ($this->notifier->invoiceReminder($payload)) {
                            $this->invoices($restaurantId)->update((int) $payload['id'], ['reminder_notified_at' => $now]);
                        }
                        break;

                    case 'invoice_overdue':
                        if ($this->notifier->invoiceOverdue($payload, (int) $payload['grace_days'])) {
                            $this->invoices($restaurantId)->update((int) $payload['id'], ['overdue_notified_at' => $now]);
                        }
                        break;

                    case 'trial_reminder':
                        if ($this->notifier->trialReminder($payload, (int) $payload['days_left'])) {
                            (new SubscriptionModel())->forRestaurant($restaurantId)->update((int) $payload['id'], ['trial_reminder_sent_at' => $now]);
                        }
                        break;

                    case 'suspended':
                        $this->notifier->suspended($restaurantId, (string) $payload['reason']);
                        break;
                }
            } catch (Throwable $e) {
                log_message('error', sprintf('billing:run bildirim hatası (%s, restoran #%d): %s', $type, $restaurantId, $e->getMessage()));
            }
        }
    }

    /**
     * Ücretli ve askıda olmayan abonelik mi?
     *
     * @param array<string, mixed> $sub
     */
    private function isBillable(array $sub): bool
    {
        return $sub['billing_cycle'] !== 'trial' && in_array($sub['status'], ['active', 'past_due'], true);
    }

    private function invoices(int $restaurantId): InvoiceModel
    {
        return (new InvoiceModel())->forRestaurant($restaurantId);
    }
}
