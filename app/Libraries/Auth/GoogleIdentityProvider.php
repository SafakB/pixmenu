<?php

declare(strict_types=1);

namespace App\Libraries\Auth;

/**
 * Google kimlik sağlayıcısı sözleşmesi — `service('googleOAuth')`.
 * Testlerde sahte bir uygulama `Services::injectMock('googleOAuth', ...)` ile verilir.
 */
interface GoogleIdentityProvider
{
    /** clientId + clientSecret tanımlı mı? */
    public function isEnabled(): bool;

    /**
     * Kullanıcının yönlendirileceği Google yetkilendirme adresi (authorization code + PKCE S256).
     */
    public function authorizationUrl(string $state, string $codeChallenge): string;

    /**
     * Yetkilendirme kodunu kimliğe çevirir.
     *
     * @return array{sub: string, email: string, email_verified: bool, name: string}
     *
     * @throws GoogleOAuthException
     */
    public function identity(string $code, string $codeVerifier): array;
}
