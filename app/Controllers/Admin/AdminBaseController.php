<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\RestaurantModel;
use App\Models\RestaurantTranslationModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * /admin altındaki (login hariç) tüm controller'ların tabanı.
 * `adminauth` filtresi çalışmış olmalıdır; TenantContext burada KULLANILMAZ.
 */
abstract class AdminBaseController extends BaseController
{
    /** @var array<string, mixed> */
    protected array $admin;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        // Filtre atlanmışsa (yanlış rota tanımı) sessizce devam etme.
        $this->admin = service('adminAuth')->user() ?? $this->notFound();
    }

    /**
     * URL'deki restoran ID'si — silinmemiş restoran yoksa 404.
     *
     * @return array<string, mixed>
     */
    protected function requireRestaurant(int $restaurantId): array
    {
        return (new RestaurantModel())->find($restaurantId) ?? $this->notFound();
    }

    /**
     * Restoranın varsayılan dildeki adı (yoksa slug) — breadcrumb/başlık için.
     *
     * @param array<string, mixed> $restaurant
     */
    protected function restaurantName(array $restaurant): string
    {
        return (string) (service('languageResolver')->resolveOne(
            RestaurantTranslationModel::class,
            (int) $restaurant['id'],
            [(int) $restaurant['default_language_id']],
        )['name'] ?? $restaurant['slug']);
    }

    /**
     * Silinmişler dahil tüm restoranların adı (id => ad) — fatura ve işlem kaydı listeleri.
     *
     * @return array<int, string>
     */
    protected function restaurantNames(): array
    {
        $restaurants = (new RestaurantModel())->withDeleted()->orderBy('id', 'ASC')->findAll();
        $chains      = [];

        foreach ($restaurants as $r) {
            $chains[(int) $r['id']] = [(int) $r['default_language_id']];
        }

        $names = service('languageResolver')->resolveManyWithChains(RestaurantTranslationModel::class, $chains);
        $out   = [];

        foreach ($restaurants as $r) {
            $out[(int) $r['id']] = (string) ($names[(int) $r['id']]['name'] ?? $r['slug']);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        return view($view, $data + ['currentAdmin' => $this->admin]);
    }
}
