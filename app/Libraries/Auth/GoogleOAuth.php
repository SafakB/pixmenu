<?php

declare(strict_types=1);

namespace App\Libraries\Auth;

use Config\Google;
use Throwable;

/**
 * Google OAuth 2.0 authorization code akışı (PKCE S256) + OpenID Connect userinfo.
 * Harici paket kullanılmaz; HTTP için CI4 CURLRequest.
 *
 * Kimlik, token uç noktasından alınan access token ile TLS üzerinden Google'ın userinfo
 * uç noktasından okunur (id_token imza doğrulaması gerekmez).
 */
final class GoogleOAuth implements GoogleIdentityProvider
{
    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(private readonly Google $config)
    {
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled();
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'             => $this->config->clientId,
            'redirect_uri'          => $this->config->redirectUri(),
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt'                => 'select_account',
            'access_type'           => 'online',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function identity(string $code, string $codeVerifier): array
    {
        $token = $this->request('POST', self::TOKEN_URL, [
            'form_params' => [
                'code'          => $code,
                'client_id'     => $this->config->clientId,
                'client_secret' => $this->config->clientSecret,
                'redirect_uri'  => $this->config->redirectUri(),
                'grant_type'    => 'authorization_code',
                'code_verifier' => $codeVerifier,
            ],
        ]);

        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new GoogleOAuthException('Google token yanıtında access_token yok.');
        }

        $info = $this->request('GET', self::USERINFO_URL, [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]);

        $sub   = $info['sub'] ?? null;
        $email = $info['email'] ?? null;

        if (! is_string($sub) || $sub === '' || ! is_string($email) || $email === '') {
            throw new GoogleOAuthException('Google userinfo yanıtında sub/email yok.');
        }

        $verified = $info['email_verified'] ?? false;

        return [
            'sub'            => $sub,
            'email'          => mb_strtolower(trim($email)),
            'email_verified' => $verified === true || $verified === 'true',
            'name'           => trim((string) ($info['name'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $options): array
    {
        try {
            $client   = single_service('curlrequest', ['timeout' => 10, 'http_errors' => false]);
            // CURLRequest metodu büyük harfe çevirmez; HTTP metotları harfe duyarlıdır ('post' → 405).
            $response = $client->request(strtoupper($method), $url, $options + ['headers' => []]);
            $status   = $response->getStatusCode();
            $body     = json_decode((string) $response->getBody(), true);
        } catch (Throwable $e) {
            throw new GoogleOAuthException('Google isteği başarısız: ' . $e->getMessage(), 0, $e);
        }

        if ($status !== 200 || ! is_array($body)) {
            $detail = '';

            if (is_array($body) && is_string($body['error'] ?? null)) {
                $detail = ' ' . $body['error'];

                if (is_string($body['error_description'] ?? null)) {
                    $detail .= ': ' . $body['error_description'];
                }
            }

            throw new GoogleOAuthException('Google beklenmeyen yanıt: HTTP ' . $status . $detail);
        }

        return $body;
    }
}
