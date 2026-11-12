<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Models\InvoiceModel;
use App\Services\InvoiceService;
use App\Services\PlanService;
use App\Services\SubscriptionService;

/**
 * Owner'ın abonelik, fatura ve paket karşılaştırma sayfaları (salt okunur) —
 * docs/ABONELIK_PLANI.md §8. Restoran askıdayken de erişilebilir.
 */
final class SubscriptionController extends PanelBaseController
{
    /**
     * View: panel/subscription/index —
     *   summary (SubscriptionService::summary), suspended (bool), suspension_reason|null,
     *   invoices[{id, number, description, period_start, period_end, total, currency_code, status, display_status, issued_at, due_date, paid_at}],
     *   paymentInstructions (string)
     */
    public function index(): string
    {
        $this->requireOwner();

        return $this->render('panel/subscription/index', [
            'summary'             => (new SubscriptionService())->summary($this->tenant->restaurantId()),
            'suspended'           => $this->restaurant['suspended_at'] !== null,
            'suspension_reason'   => $this->restaurant['suspension_reason'],
            'invoices'            => array_map(static fn (array $i): array => $i + [
                'display_status' => InvoiceService::displayStatus($i),
            ], (new InvoiceModel())->latest()),
            'paymentInstructions' => config('Billing')->paymentInstructions,
        ]);
    }

    /**
     * Yazdırılabilir fatura. Başka restoranın faturası → 404 (TenantModel).
     *
     * View: panel/subscription/invoice — invoice (satır + display_status, method_label), restaurantName,
     *       seller{name, address, tax_info}, paymentInstructions
     */
    public function invoice(int $invoiceId): string
    {
        $this->requireOwner();
        $invoice = (new InvoiceModel())->find($invoiceId) ?? $this->notFound();
        $config  = config('Billing');

        return $this->render('panel/subscription/invoice', [
            'invoice' => $invoice + [
                'display_status' => InvoiceService::displayStatus($invoice),
                'method_label'   => InvoiceService::METHOD_LABELS[$invoice['payment_method'] ?? ''] ?? null,
            ],
            'restaurantName'      => $this->restaurantDisplayName(),
            'seller'              => ['name' => $config->sellerName, 'address' => $config->sellerAddress, 'tax_info' => $config->sellerTaxInfo],
            'paymentInstructions' => $config->paymentInstructions,
        ]);
    }

    /**
     * Paket karşılaştırma (bilgi amaçlı; satın alma yok).
     *
     * View: panel/subscription/plans — plans[{id, code, name, description, max_branches, allow_custom_domain, allow_custom_design,
     *       trial_days, price_monthly, price_yearly, currency_code, is_featured}] (aktif), currentPlanId|null, currentCycle|null, taxRate
     */
    public function plans(): string
    {
        $this->requireOwner();
        $current = (new SubscriptionService())->current($this->tenant->restaurantId());

        return $this->render('panel/subscription/plans', [
            'plans'         => (new PlanService())->listing(true),
            'currentPlanId' => $current === null ? null : (int) $current['plan_id'],
            'currentCycle'  => $current['billing_cycle'] ?? null,
            'taxRate'       => config('Billing')->taxRate,
        ]);
    }
}
