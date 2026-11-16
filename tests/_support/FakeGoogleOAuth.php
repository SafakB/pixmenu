<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Libraries\Auth\GoogleIdentityProvider;
use App\Libraries\Auth\GoogleOAuthException;

/**
 * Testler için Google kimlik sağlayıcısı: ağ çağrısı yapmaz.
 * `Services::injectMock('googleOAuth', $fake)` ile verilir; tearDown'da `Services::resetSingle('googleOAuth')`.
 */
final class FakeGoogleOAuth implements GoogleIdentityProvider
{
    /** @var array{sub: string, email: string, email_verified: bool, name: string}|null */
    public ?array $identity = null;

    public bool $enabled = true;

    public ?string $lastState = null;

    public ?string $lastChallenge = null;

    /** @var list<array{code: string, verifier: string}> */
    public array $exchanges = [];

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $this->lastState     = $state;
        $this->lastChallenge = $codeChallenge;

        return 'https://accounts.google.test/auth?state=' . rawurlencode($state);
    }

    public function identity(string $code, string $codeVerifier): array
    {
        $this->exchanges[] = ['code' => $code, 'verifier' => $codeVerifier];

        if ($this->identity === null) {
            throw new GoogleOAuthException('Sahte: kimlik tanımlı değil.');
        }

        return $this->identity;
    }
}
