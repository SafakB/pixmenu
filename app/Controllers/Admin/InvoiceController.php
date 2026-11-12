<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Billing\Period;
use App\Models\InvoiceModel;
use App\Models\RestaurantModel;
use App\Models\RestaurantTranslationModel;
use App\Services\InvoiceService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Tüm faturalar — docs/ABONELIK_PLANI.md §8.
 */
final class InvoiceController extends AdminBaseController
{
    /** Listede gösterilen en fazla kayıt. */
    private const LIMIT = 500;

    /**
     * View: admin/invoices/index —
     *   filters{status, overdue, restaurant},
     *   restaurants[{id, name}] (filtre seçimi),
     *   invoices[{id, number, restaurant_id, restaurant_name, description, total, currency_code, status, display_status, issued_at, due_date, paid_at}],
     *   totals{pending_count, overdue_count, pending_sum (para birimi => tutar)}, limited (bool)
     */
    public function index(): string
    {
        $status     = (string) $this->request->getGet('status');
        $overdue    = (string) $this->request->getGet('overdue') === '1';
        $restaurant = (int) $this->request->getGet('restaurant');
        $status     = in_array($status, ['pending', 'paid', 'void'], true) ? $status : '';
        $today      = Period::today();

        $model = (new InvoiceModel())->withoutTenantScope();

        if ($overdue) {
            $model->where('status', 'pending')->where('due_date <', $today);
        } elseif ($status !== '') {
            $model->where('status', $status);
        }

        if ($restaurant > 0) {
            $model->where('restaurant_id', $restaurant);
        }

        $invoices = $model->orderBy('issued_at', 'DESC')->orderBy('id', 'DESC')->findAll(self::LIMIT + 1);
        $limited  = count($invoices) > self::LIMIT;
        $invoices = array_slice($invoices, 0, self::LIMIT);

        $names = $this->restaurantNames();

        $pending   = (new InvoiceModel())->withoutTenantScope()->where('status', 'pending')->findAll();
        $sums      = [];
        $overdueCt = 0;

        foreach ($pending as $row) {
            $sums[$row['currency_code']] = ($sums[$row['currency_code']] ?? 0) + InvoiceService::cents((string) $row['total']);
            $overdueCt += (string) $row['due_date'] < $today ? 1 : 0;
        }

        return $this->render('admin/invoices/index', [
            'filters'     => ['status' => $status, 'overdue' => $overdue, 'restaurant' => $restaurant > 0 ? $restaurant : null],
            'restaurants' => array_map(static fn (int $id, string $name): array => ['id' => $id, 'name' => $name], array_keys($names), $names),
            'invoices'    => array_map(static fn (array $i): array => $i + [
                'restaurant_name' => $names[(int) $i['restaurant_id']] ?? '#' . $i['restaurant_id'],
                'display_status'  => InvoiceService::displayStatus($i, $today),
            ], $invoices),
            'totals' => [
                'pending_count' => count($pending),
                'overdue_count' => $overdueCt,
                'pending_sum'   => array_map(static fn (int $c): string => InvoiceService::money($c), $sums),
            ],
            'limited' => $limited,
        ]);
    }

    /**
     * Yazdırılabilir fatura + ödeme / iptal formları.
     *
     * View: admin/invoices/show — invoice (satır + display_status, method_label), restaurant (satır + name),
     *       methods{kod: etiket}, today (Y-m-d), seller{name, address, tax_info}, paymentInstructions
     */
    public function show(int $id): string
    {
        $invoice    = $this->requireInvoice($id);
        $restaurant = (new RestaurantModel())->withDeleted()->find((int) $invoice['restaurant_id']) ?? $this->notFound();
        $config     = config('Billing');

        return $this->render('admin/invoices/show', [
            'invoice' => $invoice + [
                'display_status' => InvoiceService::displayStatus($invoice),
                'method_label'   => InvoiceService::METHOD_LABELS[$invoice['payment_method'] ?? ''] ?? null,
            ],
            'restaurant'          => $restaurant + ['name' => $this->restaurantName($restaurant)],
            'methods'             => InvoiceService::METHOD_LABELS,
            'today'               => Period::today(),
            'seller'              => ['name' => $config->sellerName, 'address' => $config->sellerAddress, 'tax_info' => $config->sellerTaxInfo],
            'paymentInstructions' => $config->paymentInstructions,
        ]);
    }

    /**
     * Alanlar: payment_method, payment_reference (ops.), paid_at (Y-m-d)
     */
    public function pay(int $id): ResponseInterface
    {
        $invoice = $this->requireInvoice($id);

        return $this->handle(function () use ($invoice): ResponseInterface {
            $paidAt = trim((string) $this->request->getPost('paid_at'));

            (new InvoiceService())->markPaid(
                $invoice,
                (string) $this->request->getPost('payment_method'),
                trim((string) $this->request->getPost('payment_reference')),
                $paidAt === '' ? Period::today() : $paidAt,
                (int) $this->admin['id'],
            );

            return $this->done('admin/invoices/' . $invoice['id'], lang('App.invoice.paid'));
        });
    }

    public function void(int $id): ResponseInterface
    {
        $invoice = $this->requireInvoice($id);

        return $this->handle(function () use ($invoice): ResponseInterface {
            (new InvoiceService())->void($invoice);

            return $this->done('admin/invoices/' . $invoice['id'], lang('App.invoice.voided'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function requireInvoice(int $id): array
    {
        return (new InvoiceModel())->withoutTenantScope()->find($id) ?? $this->notFound();
    }

    /**
     * Silinmişler dahil tüm restoranların adı (id => ad).
     *
     * @return array<int, string>
     */
    private function restaurantNames(): array
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
}
