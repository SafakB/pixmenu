<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\RestaurantDomainModel;

/**
 * Restoranın özel alan adı — docs/ABONELIK_PLANI.md §9.
 *
 * Yalnızca admin yazar. resolve() herkese açık istekte (tenant bağlamı yok) çalışır
 * ve sonucu kısa süreli cache'ler.
 */
final class DomainService
{
    /**
     * @return array<string, mixed>|null
     */
    public function current(int $restaurantId): ?array
    {
        return (new RestaurantDomainModel())->forRestaurant($restaurantId)->current();
    }

    /**
     * Alan adını tanımlar veya değiştirir (restoran başına 1). Alan adı değişirse doğrulama sıfırlanır.
     */
    public function save(int $restaurantId, string $input, bool $isActive = true): void
    {
        $domain = self::normalize($input);

        if ($domain === null) {
            throw ValidationException::single('domain', lang('App.domain.invalid'));
        }

        if ($this->isReserved($domain)) {
            throw ValidationException::single('domain', lang('App.domain.reserved'));
        }

        if (! (new SubscriptionService())->entitlements($restaurantId)['allow_custom_domain']) {
            throw new BusinessRuleException(lang('App.domain.notAllowed'));
        }

        $model   = (new RestaurantDomainModel())->forRestaurant($restaurantId);
        $current = $model->current();

        if ($model->domainTakenGlobally($domain, $current === null ? null : (int) $current['id'])) {
            throw ValidationException::single('domain', lang('App.domain.taken'));
        }

        if ($current === null) {
            $model->insert(['restaurant_id' => $restaurantId, 'domain' => $domain, 'is_active' => $isActive ? 1 : 0]);
        } else {
            $model->update((int) $current['id'], [
                'domain'      => $domain,
                'is_active'   => $isActive ? 1 : 0,
                'verified_at' => $current['domain'] === $domain ? $current['verified_at'] : null,
            ]);
            $this->forget((string) $current['domain']);
        }

        $this->forget($domain);
    }

    public function remove(int $restaurantId): void
    {
        $model   = (new RestaurantDomainModel())->forRestaurant($restaurantId);
        $current = $model->current() ?? throw new BusinessRuleException(lang('App.domain.none'));

        $model->delete((int) $current['id']);
        $this->forget((string) $current['domain']);
    }

    public function setVerified(int $restaurantId, bool $verified): void
    {
        $model   = (new RestaurantDomainModel())->forRestaurant($restaurantId);
        $current = $model->current() ?? throw new BusinessRuleException(lang('App.domain.none'));

        $model->update((int) $current['id'], ['verified_at' => $verified ? date('Y-m-d H:i:s') : null]);
        $this->forget((string) $current['domain']);
    }

    /**
     * İstek host'u bir restoranın özel alan adı mı? Restoran aktif, askıda değil, paketi
     * özel alan adına izin veriyor ve alan adı aktif olmalı.
     *
     * @return array{restaurant_id: int, domain: string, default_language_id: int}|null
     */
    public function resolve(string $host): ?array
    {
        $domain = self::normalize($host);

        if ($domain === null) {
            return null;
        }

        $key    = self::cacheKey($domain);
        $cached = cache($key);

        if (is_array($cached)) {
            return $cached['hit'] ? $cached['data'] : null;
        }

        $row = db_connect()->table('restaurant_domains d')
            ->select('d.restaurant_id, d.domain, r.default_language_id')
            ->join('restaurants r', 'r.id = d.restaurant_id')
            ->join('subscriptions s', 's.restaurant_id = r.id AND s.is_current = 1')
            ->join('plans p', 'p.id = s.plan_id')
            ->where('d.domain', $domain)
            ->where('d.is_active', 1)
            ->where('r.is_active', 1)
            ->where('r.deleted_at', null)
            ->where('r.suspended_at', null)
            ->where('p.allow_custom_domain', 1)
            ->get()
            ->getRowArray();

        $data = $row === null ? null : [
            'restaurant_id'       => (int) $row['restaurant_id'],
            'domain'              => (string) $row['domain'],
            'default_language_id' => (int) $row['default_language_id'],
        ];

        cache()->save($key, ['hit' => $data !== null, 'data' => $data], config('Billing')->domainCacheTtl);

        return $data;
    }

    public function forget(string $domain): void
    {
        $normalized = self::normalize($domain);

        if ($normalized !== null) {
            cache()->delete(self::cacheKey($normalized));
        }
    }

    public function forgetRestaurant(int $restaurantId): void
    {
        $current = $this->current($restaurantId);

        if ($current !== null) {
            $this->forget((string) $current['domain']);
        }
    }

    /**
     * Kullanıcı girdisini saklama biçimine çevirir: küçük harf, şemasız, yolsuz, portsuz, `www.`'siz.
     * Geçerli bir alan adı değilse null.
     */
    public static function normalize(string $input): ?string
    {
        $value = strtolower(trim($input));
        $value = (string) preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $value);
        $value = (string) preg_replace('#[/?\#].*$#', '', $value);
        $value = (string) preg_replace('#:\d+$#', '', $value);
        $value = rtrim($value, '.');

        if (str_starts_with($value, 'www.')) {
            $value = substr($value, 4);
        }

        if ($value === '' || strlen($value) > 190) {
            return null;
        }

        $label = '[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?';

        if (preg_match('/^(?:' . $label . '\.)+[a-z][a-z0-9\-]{0,61}[a-z0-9]$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * Uygulamanın kendi host'ları (baseURL + allowedHostnames), bunların alt alan adları
     * ve config'teki ayrılmış adlar reddedilir.
     */
    public function isReserved(string $domain): bool
    {
        foreach ($this->appHosts() as $host) {
            if ($domain === $host || str_ends_with($domain, '.' . $host)) {
                return true;
            }
        }

        // Menü alt alan adı kökü (sirketadi.pixmenu.com) özel alan adı olarak tanımlanamaz.
        $menuRoot = (new SiteService())->rootDomain();

        if ($menuRoot !== '' && ($domain === $menuRoot || str_ends_with($domain, '.' . $menuRoot))) {
            return true;
        }

        foreach (config('Billing')->reservedDomains as $reserved) {
            $reserved = strtolower($reserved);

            if ($domain === $reserved || str_ends_with($domain, '.' . $reserved)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function appHosts(): array
    {
        $config = config('App');
        $hosts  = [];

        foreach (array_merge([(string) parse_url($config->baseURL, PHP_URL_HOST)], $config->allowedHostnames) as $host) {
            $normalized = self::normalize((string) $host) ?? strtolower((string) $host);

            if ($normalized !== '') {
                $hosts[] = $normalized;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * İstek host'u uygulamanın kendi host'u mu? (IP adresleri ve localhost da kendi host sayılır.)
     */
    public function isAppHost(string $host): bool
    {
        $host = strtolower((string) preg_replace('#:\d+$#', '', trim($host)));

        if ($host === '' || $host === 'localhost' || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        $bare = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return in_array($host, $this->appHosts(), true) || in_array($bare, $this->appHosts(), true);
    }

    private static function cacheKey(string $domain): string
    {
        return 'domain_' . md5($domain);
    }
}
