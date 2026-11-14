<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Tüm rotalar (spec §9). autoRoute KAPALI: yalnızca burada tanımlı rotalar çalışır.
 *
 * - /admin, /panel, /m, /api önekleri ayrılmıştır.
 * - Statik segmentli rotalar (new, reorder) (:num) rotalarından ÖNCE tanımlanır.
 * - (:num) ile kısıtlanmayan ID parametresi yoktur.
 *
 * @var RouteCollection $routes
 */
$routes->set404Override('App\Controllers\ErrorController::show404');

// ---------------------------------------------------------------------
// Özel alan adı (docs/ABONELIK_PLANI.md §9.1)
// İstek host'u uygulamanın kendi host'u değilse YALNIZCA menü rotaları tanımlanır;
// /admin, /panel, /api bu host'ta hiç var olmaz (oturum/çerez yüzeyi açılmaz).
// ---------------------------------------------------------------------
$requestHost = (string) (service('superglobals')->server('HTTP_HOST') ?? '');

if ($requestHost !== '' && ! (new \App\Services\DomainService())->isAppHost($requestHost)) {
    $routes->get('/', 'Menu\DomainMenuController::index');
    $routes->get('(:segment)', 'Menu\DomainMenuController::show/$1');

    return;
}

// ---------------------------------------------------------------------
// Admin — /admin
// ---------------------------------------------------------------------
$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::attempt');

    $routes->group('', ['filter' => ['adminauth', 'nocache']], static function ($routes) {
        $routes->post('logout', 'AuthController::logout');
    });

    // Rol bazlı yetki (Config\AdminRoles): her alt grup `adminperm:<izin>` taşır.
    // Yeni bir admin rotası mutlaka bir izin grubuna eklenmelidir (AdminRouteCoverageTest).
    $perm = static fn (string $permission): array => ['filter' => ['adminauth', 'adminperm:' . $permission, 'nocache']];

    $routes->group('', $perm('dashboard.view'), static function ($routes) {
        $routes->get('/', 'DashboardController::index');
    });

    // Tanımlar: diller, alerjenler, menü şablonları
    $routes->group('', $perm('definitions.manage'), static function ($routes) {
        $routes->get('languages', 'LanguageController::index');
        $routes->get('languages/new', 'LanguageController::new');
        $routes->post('languages', 'LanguageController::create');
        $routes->post('languages/reorder', 'LanguageController::reorder');      // JSON
        $routes->get('languages/(:num)/edit', 'LanguageController::edit/$1');
        $routes->post('languages/(:num)', 'LanguageController::update/$1');
        $routes->post('languages/(:num)/toggle', 'LanguageController::toggle/$1');
        $routes->post('languages/(:num)/delete', 'LanguageController::delete/$1');

        $routes->get('allergens', 'AllergenController::index');
        $routes->get('allergens/new', 'AllergenController::new');
        $routes->post('allergens', 'AllergenController::create');
        $routes->post('allergens/reorder', 'AllergenController::reorder');      // JSON
        $routes->get('allergens/(:num)/edit', 'AllergenController::edit/$1');
        $routes->post('allergens/(:num)', 'AllergenController::update/$1');
        $routes->post('allergens/(:num)/toggle', 'AllergenController::toggle/$1');
        $routes->post('allergens/(:num)/icon/delete', 'AllergenController::deleteIcon/$1');
        $routes->post('allergens/(:num)/delete', 'AllergenController::delete/$1');

        // Menü şablonları (anahtar: kod — `reorder`/`new` statik segmentleri önce)
        $routes->get('templates', 'MenuTemplateController::index');
        $routes->get('templates/new', 'MenuTemplateController::new');
        $routes->post('templates', 'MenuTemplateController::create');
        $routes->post('templates/reorder', 'MenuTemplateController::reorder');  // JSON
        $routes->get('templates/(:segment)/edit', 'MenuTemplateController::edit/$1');
        $routes->post('templates/(:segment)', 'MenuTemplateController::update/$1');
        $routes->post('templates/(:segment)/toggle', 'MenuTemplateController::toggle/$1');
        $routes->post('templates/(:segment)/delete', 'MenuTemplateController::delete/$1');
    });

    // Paketler — değişiklik (`new`/`reorder` statik segmentleri (:num)'dan önce)
    $routes->group('', $perm('plans.manage'), static function ($routes) {
        $routes->get('plans/new', 'PlanController::new');
        $routes->post('plans', 'PlanController::create');
        $routes->post('plans/reorder', 'PlanController::reorder');              // JSON
        $routes->get('plans/(:num)/edit', 'PlanController::edit/$1');
        $routes->post('plans/(:num)', 'PlanController::update/$1');
        $routes->post('plans/(:num)/toggle', 'PlanController::toggle/$1');
        $routes->post('plans/(:num)/delete', 'PlanController::delete/$1');
    });

    // Paketler, faturalar, abonelik — görüntüleme
    $routes->group('', $perm('billing.view'), static function ($routes) {
        $routes->get('plans', 'PlanController::index');
        $routes->get('invoices', 'InvoiceController::index');                   // ?status=&overdue=1&restaurant=
        $routes->get('invoices/(:num)', 'InvoiceController::show/$1');
        $routes->get('restaurants/(:num)/subscription', 'SubscriptionController::show/$1');
    });

    $routes->group('', $perm('invoices.manage'), static function ($routes) {
        $routes->post('invoices/(:num)/pay', 'InvoiceController::pay/$1');
        $routes->post('invoices/(:num)/discount', 'InvoiceController::discount/$1');
    });

    $routes->group('', $perm('invoices.void'), static function ($routes) {
        $routes->post('invoices/(:num)/void', 'InvoiceController::void/$1');
    });

    $routes->group('', $perm('subscriptions.manage'), static function ($routes) {
        $routes->post('restaurants/(:num)/subscription/change', 'SubscriptionController::change/$1');
        $routes->post('restaurants/(:num)/subscription/grace', 'SubscriptionController::grace/$1');
        $routes->post('restaurants/(:num)/subscription/discount', 'SubscriptionController::discount/$1');
    });

    $routes->group('', $perm('subscriptions.control'), static function ($routes) {
        $routes->post('restaurants/(:num)/subscription/cancel', 'SubscriptionController::cancel/$1');
        $routes->post('restaurants/(:num)/subscription/suspend', 'SubscriptionController::suspend/$1');
        $routes->post('restaurants/(:num)/subscription/resume', 'SubscriptionController::resume/$1');
    });

    // Restoranlar — değişiklik (`new` statik segmenti (:num)'dan önce)
    $routes->group('', $perm('restaurants.manage'), static function ($routes) {
        $routes->get('restaurants/new', 'RestaurantController::new');
        $routes->post('restaurants', 'RestaurantController::create');
        $routes->get('restaurants/(:num)/edit', 'RestaurantController::edit/$1');
        $routes->post('restaurants/(:num)', 'RestaurantController::update/$1');
        $routes->post('restaurants/(:num)/toggle', 'RestaurantController::toggle/$1');

        // Özel alan adı
        $routes->get('restaurants/(:num)/domain', 'DomainController::show/$1');
        $routes->post('restaurants/(:num)/domain', 'DomainController::save/$1');
        $routes->post('restaurants/(:num)/domain/verify', 'DomainController::verify/$1');
        $routes->post('restaurants/(:num)/domain/delete', 'DomainController::delete/$1');

        // Restoran dilleri
        $routes->get('restaurants/(:num)/languages', 'RestaurantLanguageController::index/$1');
        $routes->post('restaurants/(:num)/languages', 'RestaurantLanguageController::sync/$1');

        // Restoran kullanıcıları
        $routes->get('restaurants/(:num)/users', 'RestaurantUserController::index/$1');
        $routes->get('restaurants/(:num)/users/new', 'RestaurantUserController::new/$1');
        $routes->post('restaurants/(:num)/users', 'RestaurantUserController::create/$1');
        $routes->get('restaurants/(:num)/users/(:num)/edit', 'RestaurantUserController::edit/$1/$2');
        $routes->post('restaurants/(:num)/users/(:num)', 'RestaurantUserController::update/$1/$2');
        $routes->post('restaurants/(:num)/users/(:num)/toggle', 'RestaurantUserController::toggle/$1/$2');
        $routes->post('restaurants/(:num)/users/(:num)/delete', 'RestaurantUserController::delete/$1/$2');

        // Şubeler
        $routes->get('restaurants/(:num)/branches', 'BranchController::index/$1');
        $routes->get('restaurants/(:num)/branches/new', 'BranchController::new/$1');
        $routes->post('restaurants/(:num)/branches', 'BranchController::create/$1');
        $routes->get('restaurants/(:num)/branches/(:num)/edit', 'BranchController::edit/$1/$2');
        $routes->post('restaurants/(:num)/branches/(:num)', 'BranchController::update/$1/$2');
        $routes->post('restaurants/(:num)/branches/(:num)/toggle', 'BranchController::toggle/$1/$2');
        $routes->post('restaurants/(:num)/branches/(:num)/delete', 'BranchController::delete/$1/$2');
        $routes->post('restaurants/(:num)/branches/(:num)/regenerate-code', 'BranchController::regenerateCode/$1/$2');
    });

    $routes->group('', $perm('restaurants.delete'), static function ($routes) {
        $routes->post('restaurants/(:num)/delete', 'RestaurantController::delete/$1');
    });

    // İşlem kayıtları (salt okunur)
    $routes->group('', $perm('logs.view'), static function ($routes) {
        $routes->get('logs', 'ActivityLogController::index');                   // ?area=&restaurant=&actor=&action=&from=&to=&page=
    });

    $routes->group('', $perm('restaurants.view'), static function ($routes) {
        $routes->get('restaurants', 'RestaurantController::index');
        $routes->get('restaurants/(:num)', 'RestaurantController::show/$1');
    });
});

// ---------------------------------------------------------------------
// Restoran paneli — /panel
// ---------------------------------------------------------------------
$routes->group('panel', ['namespace' => 'App\Controllers\Panel'], static function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::attempt');

    $routes->group('', ['filter' => ['panelauth', 'nocache']], static function ($routes) {
        $routes->post('logout', 'AuthController::logout');
        $routes->get('/', 'DashboardController::index');

        $routes->get('profile', 'ProfileController::edit');
        $routes->post('profile', 'ProfileController::update');
        $routes->post('profile/password', 'ProfileController::password');

        // Abonelik ve faturalar — yalnızca owner; askıdayken de AÇIK
        $routes->group('', ['filter' => ['panelauth', 'panelowner', 'nocache']], static function ($routes) {
            $routes->get('subscription', 'SubscriptionController::index');
            $routes->get('subscription/plans', 'SubscriptionController::plans');
            $routes->get('subscription/invoices/(:num)', 'SubscriptionController::invoice/$1');
        });

        // Yalnızca owner (manager → 404); askıdaysa abonelik sayfasına yönlendirilir
        $routes->group('', ['filter' => ['panelauth', 'panelowner', 'panelsubscription', 'nocache']], static function ($routes) {
            $routes->get('restaurant', 'RestaurantController::edit');
            $routes->post('restaurant', 'RestaurantController::update');
            $routes->post('restaurant/logo/delete', 'RestaurantController::deleteLogo');

            $routes->get('branches/new', 'BranchController::new');
            $routes->post('branches', 'BranchController::create');
            $routes->get('branches/(:num)/edit', 'BranchController::edit/$1');
            $routes->post('branches/(:num)', 'BranchController::update/$1');
            $routes->post('branches/(:num)/toggle', 'BranchController::toggle/$1');
            $routes->post('branches/(:num)/delete', 'BranchController::delete/$1');

            $routes->get('users', 'UserController::index');
            $routes->get('users/new', 'UserController::new');
            $routes->post('users', 'UserController::create');
            $routes->get('users/(:num)/edit', 'UserController::edit/$1');
            $routes->post('users/(:num)', 'UserController::update/$1');
            $routes->post('users/(:num)/toggle', 'UserController::toggle/$1');
            $routes->post('users/(:num)/delete', 'UserController::delete/$1');
        });

        // Owner + manager (manager yalnızca atanmış şubeler); askıdaysa kapalı
        $routes->group('', ['filter' => ['panelauth', 'panelsubscription', 'nocache']], static function ($routes) {
            $routes->get('branches', 'BranchController::index');

            $routes->get('branches/(:num)/categories', 'CategoryController::index/$1');
            $routes->get('branches/(:num)/categories/new', 'CategoryController::new/$1');
            $routes->post('branches/(:num)/categories', 'CategoryController::create/$1');
            $routes->post('branches/(:num)/categories/reorder', 'CategoryController::reorder/$1');   // JSON
            $routes->get('branches/(:num)/categories/(:num)/edit', 'CategoryController::edit/$1/$2');
            $routes->post('branches/(:num)/categories/(:num)', 'CategoryController::update/$1/$2');
            $routes->post('branches/(:num)/categories/(:num)/toggle', 'CategoryController::toggle/$1/$2');
            $routes->post('branches/(:num)/categories/(:num)/delete', 'CategoryController::delete/$1/$2');

            $routes->get('branches/(:num)/products', 'ProductController::index/$1');      // ?category=ID&q=
            $routes->get('branches/(:num)/products/new', 'ProductController::new/$1');
            $routes->post('branches/(:num)/products', 'ProductController::create/$1');
            $routes->post('branches/(:num)/products/reorder', 'ProductController::reorder/$1');    // JSON
            $routes->get('branches/(:num)/products/(:num)/edit', 'ProductController::edit/$1/$2');
            $routes->post('branches/(:num)/products/(:num)', 'ProductController::update/$1/$2');
            $routes->post('branches/(:num)/products/(:num)/toggle', 'ProductController::toggle/$1/$2');
            $routes->post('branches/(:num)/products/(:num)/image/delete', 'ProductController::deleteImage/$1/$2');
            $routes->post('branches/(:num)/products/(:num)/delete', 'ProductController::delete/$1/$2');
        });
    });
});

// ---------------------------------------------------------------------
// Herkese açık menü
// ---------------------------------------------------------------------
$routes->get('m/(:alphanum)', 'Menu\MenuController::show/$1');                 // HTML menü, ?lang=en

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function ($routes) {
    $routes->get('menu/(:alphanum)', 'MenuController::show/$1');               // JSON menü, ?lang=en
});
