<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AbstractAuthController;
use App\Libraries\Auth\BaseAuth;

final class AuthController extends AbstractAuthController
{
    protected function auth(): BaseAuth
    {
        return service('adminAuth');
    }

    protected function area(): string
    {
        return 'admin';
    }
}
