<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Libraries\Auth\GoogleOAuthException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Google ile giriş / kayıt (panel kullanıcıları). Login gibi oturum açmadan önce çalışır;
 * PanelBaseController'dan türemez.
 *
 * Akış: redirect() → Google (state + PKCE oturumda, tek kullanımlık, 10 dk) → callback():
 *   - kayıtlı Google kimliği / aynı e-postalı hesap → giriş (PanelAuth::attemptGoogle)
 *   - hesap yok → kimlik oturuma (signup_google, 30 dk) → /kayit sihirbazı (şifresiz)
 * Kayıt formu Google kimliğini YALNIZCA oturumdan okur.
 */
final class GoogleAuthController extends BaseController
{
    /** Bekleyen Google kaydı (SignupController okur). */
    public const SIGNUP_KEY = 'signup_google';

    public const SIGNUP_TTL = 1800;

    private const FLOW_KEY = 'google_oauth';

    private const FLOW_TTL = 600;

    /**
     * GET panel/auth/google?intent=login|register&plan=kod&cycle=monthly|yearly
     */
    public function redirect(): RedirectResponse
    {
        $google = service('googleOAuth');

        if (! $google->isEnabled()) {
            $this->notFound();
        }

        $state    = bin2hex(random_bytes(16));
        $verifier = self::base64url(random_bytes(48));

        session()->set(self::FLOW_KEY, [
            'state'    => $state,
            'verifier' => $verifier,
            'intent'   => $this->request->getGet('intent') === 'register' ? 'register' : 'login',
            'plan'     => mb_substr(trim((string) $this->request->getGet('plan')), 0, 30),
            'cycle'    => $this->request->getGet('cycle') === 'yearly' ? 'yearly' : 'monthly',
            'at'       => time(),
        ]);

        return redirect()->to($google->authorizationUrl($state, self::base64url(hash('sha256', $verifier, true))));
    }

    /**
     * GET panel/auth/google/callback?code=…&state=… (veya ?error=access_denied)
     */
    public function callback(): RedirectResponse
    {
        $google = service('googleOAuth');

        if (! $google->isEnabled()) {
            $this->notFound();
        }

        $flow = session()->get(self::FLOW_KEY);
        session()->remove(self::FLOW_KEY); // tek kullanımlık

        $state = (string) $this->request->getGet('state');

        if (! is_array($flow) || ! isset($flow['state'], $flow['verifier'], $flow['at'])
            || time() - (int) $flow['at'] > self::FLOW_TTL
            || $state === '' || ! hash_equals((string) $flow['state'], $state)) {
            return $this->redirectTo('panel/login')->with('error', lang('App.auth.google.failed'));
        }

        $origin = $flow['intent'] === 'register' ? 'kayit' : 'panel/login';

        if ($this->request->getGet('error') !== null) {
            return $this->redirectTo($origin)->with('info', lang('App.auth.google.cancelled'));
        }

        $code = (string) $this->request->getGet('code');

        try {
            if ($code === '') {
                throw new GoogleOAuthException('Callback içinde code yok.');
            }

            $identity = $google->identity($code, (string) $flow['verifier']);
        } catch (GoogleOAuthException $e) {
            log_message('warning', 'Google ile giriş başarısız: {message}', ['message' => $e->getMessage()]);

            return $this->redirectTo($origin)->with('error', lang('App.auth.google.failed'));
        }

        if (! $identity['email_verified']) {
            return $this->redirectTo($origin)->with('error', lang('App.auth.google.unverified'));
        }

        $result = service('panelAuth')->attemptGoogle($identity['sub'], $identity['email']);

        switch ($result) {
            case 'ok':
            case 'linked':
            case 'linked_password_cleared':
                session()->remove(self::SIGNUP_KEY);

                $activity = service('activity');
                $activity->record('auth.login');
                $activity->detail(['via' => 'google'] + ($result === 'ok' ? [] : ['linked' => true]));

                $response = $this->redirectTo('panel');

                return match ($result) {
                    'linked'                  => $response->with('success', lang('App.auth.google.linked')),
                    'linked_password_cleared' => $response->with('warning', lang('App.auth.google.passwordCleared')),
                    default                   => $response,
                };

            case 'not_found':
                session()->set(self::SIGNUP_KEY, [
                    'sub'   => $identity['sub'],
                    'email' => $identity['email'],
                    'name'  => $identity['name'],
                    'at'    => time(),
                ]);

                $query = array_filter(['plan' => $flow['plan'] ?? '', 'cycle' => $flow['cycle'] ?? '']);

                return redirect()->to(site_url('kayit') . ($query === [] ? '' : '?' . http_build_query($query)))
                    ->with('info', lang('App.auth.google.continueSignup'));

            case 'conflict':
                return $this->redirectTo($origin)->with('error', lang('App.auth.google.conflict'));

            default:
                return $this->redirectTo($origin)->with('error', lang('App.auth.google.unusable'));
        }
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
