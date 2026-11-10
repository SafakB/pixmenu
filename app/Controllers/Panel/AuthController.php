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
}
