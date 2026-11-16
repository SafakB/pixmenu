<?php

declare(strict_types=1);

namespace Config;

use App\Libraries\Auth\AdminAuth;
use App\Libraries\Auth\GoogleIdentityProvider;
use App\Libraries\Auth\GoogleOAuth;
use App\Libraries\Auth\PanelAuth;
use App\Libraries\Tenant\TenantContext;
use App\Services\ActivityLogger;
use App\Services\ImageService;
use App\Services\LanguageResolver;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Proje servisleri: tenant(), adminAuth(), panelAuth(), googleOAuth(), languageResolver(), activity(), images().
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

    /**
     * Google ile giriş/kayıt istemcisi. Testlerde Services::injectMock('googleOAuth', $sahte).
     */
    public static function googleOAuth(bool $getShared = true): GoogleIdentityProvider
    {
        if ($getShared) {
            return static::getSharedInstance('googleOAuth');
        }

        return new GoogleOAuth(config('Google'));
    }

    public static function languageResolver(bool $getShared = true): LanguageResolver
    {
        if ($getShared) {
            return static::getSharedInstance('languageResolver');
        }

        return new LanguageResolver();
    }

    /**
     * İstek başına tek işlem kaydedici; ActivityLogFilter yanıt sonrası flush() eder.
     */
    public static function activity(bool $getShared = true): ActivityLogger
    {
        if ($getShared) {
            return static::getSharedInstance('activity');
        }

        return new ActivityLogger();
    }

    public static function images(bool $getShared = true): ImageService
    {
        if ($getShared) {
            return static::getSharedInstance('images');
        }

        return new ImageService();
    }
}
