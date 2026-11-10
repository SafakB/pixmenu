<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\RestaurantModel;
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
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        return view($view, $data + ['currentAdmin' => $this->admin]);
    }
}
