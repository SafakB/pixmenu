<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Her /admin rotası (login/logout hariç) `adminauth` + geçerli bir `adminperm:<izin>` filtresi taşımalı.
 * Yeni rota eklenip izin grubu unutulursa bu test kırılır.
 *
 * @internal
 */
final class AdminRouteCoverageTest extends CIUnitTestCase
{
    private const PUBLIC_ROUTES = ['admin/login'];
    private const AUTH_ONLY     = ['admin/logout'];

    public function testEveryAdminRouteRequiresAPermission(): void
    {
        $routes      = $this->loadRoutes();
        $known       = $this->knownPermissions();
        $checked     = 0;
        $permissions = [];

        foreach (['GET', 'POST'] as $verb) {
            foreach (array_keys($routes->getRoutes($verb)) as $from) {
                $from = (string) $from;

                if ($from !== 'admin' && ! str_starts_with($from, 'admin/')) {
                    continue;
                }

                if (in_array($from, self::PUBLIC_ROUTES, true)) {
                    continue;
                }

                $filters = $routes->getFiltersForRoute($from, $verb);
                $this->assertContains('adminauth', $filters, "{$verb} {$from}: adminauth yok");

                if (in_array($from, self::AUTH_ONLY, true)) {
                    continue;
                }

                $perm = array_values(array_filter($filters, static fn (string $f): bool => str_starts_with($f, 'adminperm:')));
                $this->assertCount(1, $perm, "{$verb} {$from}: tam olarak bir adminperm filtresi olmalı");

                $permission = substr($perm[0], strlen('adminperm:'));
                $this->assertContains($permission, $known, "{$verb} {$from}: bilinmeyen izin {$permission}");

                $permissions[$permission] = true;
                $checked++;
            }
        }

        $this->assertGreaterThan(70, $checked);
        // Config'te tanımlı her izin en az bir rotada kullanılıyor (ölü izin yok).
        $this->assertEqualsCanonicalizing($known, array_keys($permissions));
    }

    /**
     * @return list<string>
     */
    private function knownPermissions(): array
    {
        $all = [];

        foreach (config('AdminRoles')->roles as $role) {
            foreach ($role['permissions'] as $p) {
                if ($p !== '*') {
                    $all[$p] = true;
                }
            }
        }

        // Yalnızca süper adminin ('*') sahip olduğu izinler config'te listelenmez; rotalardan toplanır.
        return array_values(array_unique(array_merge(array_keys($all), [
            'restaurants.delete', 'invoices.void', 'subscriptions.control', 'plans.manage',
        ])));
    }

    private function loadRoutes(): RouteCollection
    {
        $routes = Services::routes(false);
        $routes->loadRoutes();

        return $routes;
    }
}
