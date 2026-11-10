<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Libraries\Tenant\TenantContext;
use App\Models\BranchModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * /panel altındaki (login hariç) tüm controller'ların tabanı.
 *
 * KURALLAR (spec §2.1):
 *  - restaurant_id ASLA request'ten okunmaz; yalnızca $this->tenant üzerinden gelir.
 *  - Burada ve alt sınıflarda db_connect() / $db->query() / $db->table() /
 *    withoutTenantScope() / forRestaurant() kullanmak YASAKTIR.
 *  - Her action ilk iş olarak require* yardımcılarından uygun olanı çağırır.
 */
abstract class PanelBaseController extends BaseController
{
    protected TenantContext $tenant;

    /** @var array<string, mixed> */
    protected array $currentUser;

    /** @var array<string, mixed> */
    protected array $restaurant;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $this->tenant = service('tenant');

        // `panelauth` filtresi çalışmadıysa restaurantId() TenantNotSetException fırlatır (500 + log).
        $this->tenant->restaurantId();

        $auth              = service('panelAuth');
        $this->currentUser = $auth->user() ?? $this->notFound();
        $this->restaurant  = $auth->restaurant() ?? $this->notFound();
    }

    /**
     * Şube bu tenant'a ait VE kullanıcı erişebiliyor olmalı; aksi halde 404.
     *
     * @return array<string, mixed>
     */
    protected function requireBranch(int $branchId): array
    {
        // TenantModel: başka tenant'ın şubesi burada zaten null döner.
        $branch = (new BranchModel())->find($branchId);

        if ($branch === null || ! $this->tenant->canAccessBranch((int) $branch['id'])) {
            $this->notFound();
        }

        return $branch;
    }

    /**
     * Kategori hem bu tenant'a hem verilen şubeye ait olmalı.
     *
     * @return array<string, mixed>
     */
    protected function requireCategory(int $branchId, int $categoryId): array
    {
        $this->requireBranch($branchId);

        return (new CategoryModel())->findInBranch($branchId, $categoryId) ?? $this->notFound();
    }

    /**
     * Ürün hem bu tenant'a hem verilen şubeye ait olmalı.
     *
     * @return array<string, mixed>
     */
    protected function requireProduct(int $branchId, int $productId): array
    {
        $this->requireBranch($branchId);

        return (new ProductModel())->findInBranch($branchId, $productId) ?? $this->notFound();
    }

    /**
     * Manager ise 404.
     */
    protected function requireOwner(): void
    {
        if (! $this->tenant->isOwner()) {
            $this->notFound();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        return view($view, $data + [
            'currentUser' => $this->currentUser,
            'isOwner'     => $this->tenant->isOwner(),
        ]);
    }
}
