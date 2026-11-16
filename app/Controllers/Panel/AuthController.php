<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\AbstractAuthController;
use App\Libraries\Auth\BaseAuth;

final class AuthController extends AbstractAuthController
{
    protected function auth(): BaseAuth
    {
        return service('panelAuth');
    }

    protected function area(): string
    {
        return 'panel';
    }

    /**
     * View: panel/auth/login — error|null, googleUrl|null (Google yapılandırılmışsa), signupUrl
     */
    protected function loginViewData(): array
    {
        return [
            'googleUrl' => service('googleOAuth')->isEnabled() ? site_url('panel/auth/google') . '?intent=login' : null,
            'signupUrl' => site_url('kayit'),
        ];
    }
}
