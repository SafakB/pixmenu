<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Genel HTML e-posta gönderici (hesap e-postaları). Gönderim hatası çağıranın akışını
 * ASLA bozmaz: false döner ve günlüğe yazılır. `Email.fromEmail` boşsa gönderim atlanır
 * (yerel geliştirme / test) ve true döner.
 *
 * Faturalama e-postaları kendi alıcı/BCC kurallarıyla BillingNotifier'dadır.
 */
final class Mailer
{
    /**
     * @param list<string>         $to
     * @param array<string, mixed> $data view değişkenleri (view saveData'sız render edilir)
     */
    public function send(array $to, string $subject, string $view, array $data): bool
    {
        $config = config('Email');

        if (trim($config->fromEmail) === '') {
            log_message('notice', 'E-posta gönderilmedi (Email.fromEmail tanımlı değil): {view}', ['view' => $view]);

            return true;
        }

        $to = array_values(array_filter($to, static fn (string $address): bool => $address !== ''));

        if ($to === []) {
            return true;
        }

        try {
            $email = service('email');
            $email->clear(true);
            $email->setFrom($config->fromEmail, $config->fromName !== '' ? $config->fromName : 'PixMenu');
            $email->setTo($to);
            $email->setMailType('html');
            $email->setSubject($subject);
            $email->setMessage(view($view, $data + ['subject' => $subject], ['saveData' => false]));

            if (! $email->send(false)) {
                log_message('error', 'E-posta gönderilemedi: {view}', ['view' => $view]);

                return false;
            }
        } catch (Throwable $e) {
            log_message('error', 'E-posta hatası ({view}): {message}', ['view' => $view, 'message' => $e->getMessage()]);

            return false;
        }

        return true;
    }
}
