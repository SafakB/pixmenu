<?php

declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Models\InvoiceModel;
use App\Models\PlanModel;
use App\Services\InvoiceService;
use App\Services\PlanService;
use App\Services\SubscriptionService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Owner'ın abonelik, fatura, paket karşılaştırma ve ödeme sayfaları — docs/ABONELIK_PLANI.md §8.
 * Restoran askıdayken de erişilebilir (deneme süresi dolan restoran buradan ücretli pakete geçer).
 *
 * Self-servis yazma yalnızca deneme → ücretli geçiştir (upgrade); ücretli → ücretli admin'dedir.
 * Kart ile ödeme henüz yok: ödeme ekranı arayüzdür, POST'u durum değiştirmez (TODO(PayTR)).
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
     *       seller{name, address, tax_info}, paymentInstructions, payUrl|null (ödenmemişse ödeme ekranı)
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
            'payUrl'              => $invoice['status'] === 'pending' ? site_url('panel/subscription/invoices/' . $invoiceId . '/pay') : null,
        ]);
    }

    /**
     * Paket karşılaştırma. Deneme paketindeki owner ücretli pakete buradan geçer.
     *
     * View: panel/subscription/plans — plans[{id, code, name, description, max_branches, allow_custom_domain, allow_custom_design,
     *       trial_days, price_monthly, price_yearly, currency_code, is_featured}] (aktif), currentPlanId|null, currentCycle|null, taxRate,
     *       canSelfUpgrade (bool)
     */
    public function plans(): string
    {
        $this->requireOwner();
        $current = (new SubscriptionService())->current($this->tenant->restaurantId());

        return $this->render('panel/subscription/plans', [
            'plans'          => (new PlanService())->publicListing(),
            'currentPlanId'  => $current === null ? null : (int) $current['plan_id'],
            'currentCycle'   => $current['billing_cycle'] ?? null,
            'taxRate'        => config('Billing')->taxRate,
            'canSelfUpgrade' => $this->canSelfUpgrade($current),
        ]);
    }

    /**
     * POST deneme → ücretli paket (plan_id, billing_cycle). Fatura kesildiyse ödeme ekranına gider.
     */
    public function upgrade(): ResponseInterface
    {
        $this->requireOwner();

        return $this->handle(function (): ResponseInterface {
            $planId = (int) $this->request->getPost('plan_id');
            $cycle  = (string) $this->request->getPost('billing_cycle');
            $result = (new SubscriptionService())->upgradeFromTrial($this->tenant->restaurantId(), $planId, $cycle);

            service('activity')->detail([
                'plan'       => (new PlanModel())->find($planId)['code'] ?? $planId,
                'cycle'      => $cycle,
                'invoice_id' => $result['invoice_id'],
            ]);

            return $result['invoice_id'] === null
                ? $this->done('panel/subscription', lang('App.subscription.upgradedNoInvoice'))
                : $this->done('panel/subscription/invoices/' . $result['invoice_id'] . '/pay', lang('App.subscription.upgraded'));
        });
    }

    /**
     * Ödeme ekranı: yalnızca ödenmemiş fatura. Ödenmiş/iptal → fatura sayfası. Başka tenant → 404.
     *
     * View: panel/subscription/checkout — invoice (satır + display_status), planName, cycleLabel,
     *       restaurantName, paymentInstructions, ownerName
     */
    public function checkout(int $invoiceId): ResponseInterface|string
    {
        $this->requireOwner();
        $invoice = (new InvoiceModel())->find($invoiceId) ?? $this->notFound();

        if ($invoice['status'] !== 'pending') {
            return $this->toInvoice($invoiceId)->with('info', lang('App.invoice.notPayable'));
        }

        $subscription = (new SubscriptionService())->current($this->tenant->restaurantId());
        $cycle        = ($subscription !== null && (int) $subscription['id'] === (int) $invoice['subscription_id'])
            ? (string) $subscription['billing_cycle']
            : null;

        return $this->render('panel/subscription/checkout', [
            'invoice'             => $invoice + ['display_status' => InvoiceService::displayStatus($invoice)],
            'planName'            => (new PlanService())->name((int) $invoice['plan_id']),
            'cycleLabel'          => $cycle === null ? null : lang('App.subscription.cycle.' . $cycle),
            'restaurantName'      => $this->restaurantDisplayName(),
            'paymentInstructions' => config('Billing')->paymentInstructions,
            'ownerName'           => (string) $this->currentUser['name'],
        ]);
    }

    /**
     * POST ödeme — ŞİMDİLİK STUB: kart verisi bu uca hiç gelmez (formdaki kart alanlarının `name`'i yok),
     * fatura durumu değişmez ve işlem kaydı yazılmaz.
     *
     * TODO(PayTR): Direct API ile kart formu doğrudan PayTR'ye post edilecek (form action + PayTR alan adları
     *   + sunucuda üretilen paytr_token/merchant_oid gizli alanları). merchant_oid alfanümerik ve deneme başına
     *   benzersiz olmalı (fatura numarasında tire var → ör. fatura id + deneme soneki). Başarılı/başarısız dönüş
     *   sayfaları + CSRF dışı callback rotası; callback hash'i doğrulanınca:
     *   (new InvoiceService())->markPaid($invoice, 'card', $merchantOid, date('Y-m-d H:i:s'), null)
     */
    public function pay(int $invoiceId): RedirectResponse
    {
        $this->requireOwner();
        $invoice = (new InvoiceModel())->find($invoiceId) ?? $this->notFound();

        if ($invoice['status'] !== 'pending') {
            return $this->toInvoice($invoiceId)->with('info', lang('App.invoice.notPayable'));
        }

        return $this->redirectTo('panel/subscription')->with('info', lang('App.invoice.onlineSoon'));
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $current
     */
    private function canSelfUpgrade(?array $current): bool
    {
        return ($this->restaurant['suspension_reason'] ?? null) !== 'manual'
            && ($current === null || $current['billing_cycle'] === 'trial');
    }

    private function toInvoice(int $invoiceId): RedirectResponse
    {
        return $this->redirectTo('panel/subscription/invoices/' . $invoiceId);
    }
}
