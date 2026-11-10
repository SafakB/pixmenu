<?php

declare(strict_types=1);

namespace Config;

use App\Libraries\Auth\AdminAuth;
use App\Libraries\Auth\PanelAuth;
use App\Libraries\Tenant\TenantContext;
use App\Services\ImageService;
use App\Services\LanguageResolver;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Proje servisleri: tenant(), adminAuth(), panelAuth(), languageResolver(), images().
 */
class Services extends BaseService
{
    /**
     * İstek başına tek TenantContext. set() yalnızca PanelAuthFilter'dan çağrılır.
     */
    public static function tenant(bool $getShared = true): TenantContext
    {
        if ($getShared) {
            return static::getSharedInstance('tenant');
        }

        return new TenantContext();
    }

    public static function adminAuth(bool $getShared = true): AdminAuth
    {
        if ($getShared) {
            return static::getSharedInstance('adminAuth');
        }

        return new AdminAuth();
    }

    public static function panelAuth(bool $getShared = true): PanelAuth
    {
        if ($getShared) {
            return static::getSharedInstance('panelAuth');
        }

        return new PanelAuth();
    }

    public static function languageResolver(bool $getShared = true): LanguageResolver
    {
        if ($getShared) {
            return static::getSharedInstance('languageResolver');
        }

        return new LanguageResolver();
    }

    public static function images(bool $getShared = true): ImageService
    {
        if ($getShared) {
            return static::getSharedInstance('images');
        }

        return new ImageService();
    }
}
