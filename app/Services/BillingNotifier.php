<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RestaurantModel;
use App\Models\RestaurantTranslationModel;
use App\Models\RestaurantUserModel;
use Throwable;

/**
 * Faturalama e-postaları — docs/ABONELIK_PLANI.md §6, §7.
 *
 * Alıcılar restoranın aktif owner'larıdır (+ config BCC). Gönderim başarılıysa
 * true döner; çağıran ilgili *_notified_at alanını işaretler ve aynı e-posta tekrar
 * gönderilmez. SMTP/gönderen ayarlı değilse hiçbir şey gönderilmez, log'a yazılır
 * ve yine true döner (cron her gün aynı uyarıyı tekrar denemesin).
 */
final class BillingNotifier
{
    public const TEMPLATES = ['invoice_issued', 'invoice_reminder', 'invoice_overdue', 'suspended', 'trial_reminder', 'trial_expired'];

    /**
     * @param array<string, mixed> $invoice
     */
    public function invoiceIssued(array $invoice): bool
    {
        return $this->send((int) $invoice['restaurant_id'], 'invoice_issued', 'Yeni faturanız: ' . $invoice['number'], ['invoice' => $invoice]);
    }

    /**
     * @param array<string, mixed> $invoice
     */
    public function invoiceReminder(array $invoice): bool
    {
        return $this->send((int) $invoice['restaurant_id'], 'invoice_reminder', 'Fatura vade hatırlatması: ' . $invoice['number'], ['invoice' => $invoice]);
    }

    /**
     * @param array<string, mixed> $invoice
     */
    public function invoiceOverdue(array $invoice, int $graceDays): bool
    {
        return $this->send((int) $invoice['restaurant_id'], 'invoice_overdue', 'Vadesi geçmiş fatura: ' . $invoice['number'], [
            'invoice'   => $invoice,
            'graceDays' => $graceDays,
        ]);
    }

    public function suspended(int $restaurantId, string $reason): bool
    {
        return $this->send($restaurantId, $reason === 'trial_expired' ? 'trial_expired' : 'suspended', $reason === 'trial_expired'
            ? 'Deneme süreniz sona erdi'
            : 'Aboneliğiniz askıya alındı', ['reason' => $reason]);
    }

    /**
     * @param array<string, mixed> $subscription
     */
    public function trialReminder(array $subscription, int $daysLeft): bool
    {
        return $this->send((int) $subscription['restaurant_id'], 'trial_reminder', 'Deneme sürenizin bitmesine ' . $daysLeft . ' gün kaldı', [
            'subscription' => $subscription,
            'daysLeft'     => $daysLeft,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function send(int $restaurantId, string $template, string $subject, array $data): bool
    {
        $emailConfig = config('Email');

        if (trim($emailConfig->fromEmail) === '') {
            log_message('notice', 'Billing e-postası gönderilmedi (Email.fromEmail tanımlı değil): {template} restoran #{id}', ['template' => $template, 'id' => $restaurantId]);

            return true;
        }

        $owners = array_values(array_filter(
            (new RestaurantUserModel())->forRestaurant($restaurantId)->ordered('owner'),
            static fn (array $user): bool => (int) $user['is_active'] === 1,
        ));
        $to = array_column($owners, 'email');

        if ($to === []) {
            log_message('notice', 'Billing e-postası için alıcı yok: {template} restoran #{id}', ['template' => $template, 'id' => $restaurantId]);

            return true;
        }

        $restaurant = (new RestaurantModel())->withDeleted()->find($restaurantId);
        $name       = $restaurant === null ? '' : (string) (service('languageResolver')->resolveOne(
            RestaurantTranslationModel::class,
            $restaurantId,
            [(int) $restaurant['default_language_id']],
        )['name'] ?? $restaurant['slug']);

        try {
            $email = service('email');
            $email->clear(true);
            $email->setFrom($emailConfig->fromEmail, $emailConfig->fromName !== '' ? $emailConfig->fromName : 'PixMenu');
            $email->setTo($to);

            $bcc = config('Billing')->billingEmailBcc;

            if ($bcc !== '') {
                $email->setBCC($bcc);
            }

            $email->setMailType('html');
            $email->setSubject($subject);
            $email->setMessage(view('emails/billing/' . $template, $data + [
                'subject'        => $subject,
                'restaurantName' => $name,
                'panelUrl'       => site_url('panel/subscription'),
                'billing'        => config('Billing'),
            ], ['saveData' => false]));

            if (! $email->send(false)) {
                log_message('error', 'Billing e-postası gönderilemedi: {template} restoran #{id}', ['template' => $template, 'id' => $restaurantId]);

                return false;
            }
        } catch (Throwable $e) {
            log_message('error', 'Billing e-postası hatası: ' . $e->getMessage());

            return false;
        }

        return true;
    }
}
