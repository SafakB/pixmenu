<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth\BaseAuth;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin ve panel login/logout akışının ortak kısmı (spec §7.1).
 * Her alt sınıf KENDİ auth servisini ve KENDİ alanını (admin|panel) kullanır.
 */
abstract class AbstractAuthController extends BaseController
{
    abstract protected function auth(): BaseAuth;

    /** 'admin' veya 'panel' — URL öneki ve view klasörü. */
    abstract protected function area(): string;

    /**
     * GET login — oturumlu kullanıcı dashboard'a yönlendirilir.
     */
    public function login(): ResponseInterface|string
    {
        $this->auth()->forget();

        if ($this->auth()->check()) {
            return $this->redirectTo($this->area());
        }

        return view($this->area() . '/auth/login', ['error' => null]);
    }

    /**
     * POST login.
     */
    public function attempt(): ResponseInterface
    {
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');

        // Brute force: ip + email başına dakikada N deneme.
        $key = 'login_' . $this->area() . '_' . sha1($this->request->getIPAddress() . '|' . mb_strtolower($email));

        if (! service('throttler')->check($key, config('Pixmenu')->loginAttemptsPerMinute, MINUTE)) {
            return $this->response
                ->setStatusCode(429)
                ->setBody(view($this->area() . '/auth/login', ['error' => lang('App.auth.throttled')]));
        }

        if ($email === '' || $password === '' || ! $this->auth()->attempt($email, $password)) {
            // Mesaj her zaman aynıdır; kullanıcı varlığı sızdırılmaz.
            return redirect()->back()->withInput()->with('error', lang('App.auth.invalid'));
        }

        service('activity')->succeed();

        return $this->redirectTo($this->area());
    }

    /**
     * POST logout (CSRF korumalı) — yalnızca kendi oturum anahtarını siler.
     */
    public function logout(): ResponseInterface
    {
        // İşlem kaydı için aktör, oturum silinmeden önce alınır.
        $activity = service('activity');
        $activity->actor($this->auth()->user());
        $activity->succeed();

        $this->auth()->logout();

        return $this->redirectTo($this->area() . '/login')->with('success', lang('App.auth.loggedOut'));
    }
}
