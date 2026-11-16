<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Google ile giriş/kayıt (OAuth 2.0 + OpenID Connect). Değerler .env'den:
 *
 *   google.clientId     = 'xxxx.apps.googleusercontent.com'
 *   google.clientSecret = '...'
 *   google.redirectUri  = ''   # boşsa {baseURL}panel/auth/google/callback
 *
 * Google Cloud Console'da "Yetkili yönlendirme URI'si" redirectUri() ile BİREBİR aynı olmalıdır.
 * clientId/clientSecret boşsa Google düğmeleri gizlenir ve Google rotaları 404 döner.
 */
final class Google extends BaseConfig
{
    public string $clientId = '';

    public string $clientSecret = '';

    public string $redirectUri = '';

    public function enabled(): bool
    {
        return trim($this->clientId) !== '' && trim($this->clientSecret) !== '';
    }

    public function redirectUri(): string
    {
        return trim($this->redirectUri) !== '' ? trim($this->redirectUri) : site_url('panel/auth/google/callback');
    }
}
