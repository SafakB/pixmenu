<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Hesap e-postaları (self-servis kayıt). Gönderim kaydın kendisini etkilemez; çağıran
 * transaction commit edildikten SONRA çağırır.
 */
final class AccountNotifier
{
    public function __construct(private readonly Mailer $mailer = new Mailer())
    {
    }

    /**
     * Kayıt sonrası hoş geldin e-postası: deneme bitiş tarihi veya ödenecek ilk fatura.
     *
     * @param array<string, mixed>      $user    restaurant_users satırı
     * @param array<string, mixed>|null $invoice ücretli pakette ilk fatura
     */
    public function welcome(array $user, string $restaurantName, string $planName, ?string $trialEndsAt, ?array $invoice, bool $viaGoogle): bool
    {
        return $this->mailer->send([(string) $user['email']], 'PixMenu\'ye hoş geldiniz', 'emails/account/welcome', [
            'userName'       => (string) $user['name'],
            'restaurantName' => $restaurantName,
            'planName'       => $planName,
            'trialEndsAt'    => $trialEndsAt,
            'invoice'        => $invoice,
            'payUrl'         => $invoice === null ? null : site_url('panel/subscription/invoices/' . (int) $invoice['id'] . '/pay'),
            'panelUrl'       => site_url('panel'),
            'loginUrl'       => site_url('panel/login'),
            'viaGoogle'      => $viaGoogle,
            'email'          => (string) $user['email'],
        ]);
    }
}
