<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Exceptions\ValidationException;
use App\Models\InvoiceModel;
use App\Models\SubscriptionModel;
use App\Services\InvoiceService;
use App\Services\PlanService;
use App\Services\SubscriptionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restoranın aboneliği — docs/ABONELIK_PLANI.md §8 (admin/restaurants/{id}/subscription).
 */
final class SubscriptionController extends AdminBaseController
{
    /**
     * View: admin/subscriptions/show —
     *   restaurant (satır + name),
     *   summary{subscription{…, plan_name, cycle_label, status_label}|null, plan|null, usage{branches, max_branches}, days_left, grace_days, can_add_branch},
     *   history[{id, plan_name, billing_cycle, cycle_label, status, status_label, price, currency_code, started_at, current_period_start, current_period_end, ended_at}],
     *   invoices[{id, number, description, period_start, period_end, total, currency_code, status, display_status, issued_at, due_date, paid_at}],
     *   plans[{id, code, name, trial_days, price_monthly, price_yearly, currency_code, max_branches, allow_custom_domain}] (aktif),
     *   defaultGraceDays (int)
     */
    public function show(int $restaurantId): string
    {
        $restaurant = $this->requireRestaurant($restaurantId);
        $service    = new SubscriptionService();
        $plans      = new PlanService();
        $history    = (new SubscriptionModel())->forRestaurant($restaurantId)->history();
        $names      = $plans->texts(array_values(array_unique(array_map('intval', array_column($history, 'plan_id')))));

        return $this->render('admin/subscriptions/show', [
            'restaurant' => $restaurant + ['name' => $this->restaurantName($restaurant)],
            'summary'    => $service->summary($restaurantId),
            'history'    => array_map(static fn (array $s): array => $s + [
                'plan_name'    => $names[(int) $s['plan_id']]['name'] ?? '#' . $s['plan_id'],
                'cycle_label'  => lang('App.subscription.cycle.' . $s['billing_cycle']),
                'status_label' => lang('App.subscription.status.' . $s['status']),
            ], $history),
            'invoices' => array_map(static fn (array $i): array => $i + [
                'display_status' => InvoiceService::displayStatus($i),
            ], (new InvoiceModel())->forRestaurant($restaurantId)->latest()),
            'plans'            => $plans->listing(true),
            'defaultGraceDays' => config('Billing')->graceDays,
        ]);
    }

    /**
     * Paket değiştir / ata. Alanlar: plan_id, billing_cycle (monthly|yearly; deneme paketinde yok sayılır),
     * discount (ops., ilk faturaya uygulanır), notes (ops.)
     */
    public function change(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurantId): ResponseInterface {
            $discountRaw = trim((string) $this->request->getPost('discount'));
            $discount    = $discountRaw === '' ? '0.00' : (new PlanService())->money($discountRaw);

            if ($discount === null) {
                throw ValidationException::single('discount', lang('App.invoice.discountInvalid'));
            }

            $planId = (int) $this->request->getPost('plan_id');
            $cycle  = (string) $this->request->getPost('billing_cycle');

            $result = (new SubscriptionService())->changePlan(
                $restaurantId,
                $planId,
                $cycle,
                $discount,
                mb_substr(trim((string) $this->request->getPost('notes')), 0, 2000),
            );

            service('activity')->detail(['plan' => (new PlanService())->name($planId), 'cycle' => $cycle, 'discount' => $discount]);

            $message = $result['open_invoices'] > 0
                ? lang('App.subscription.changedOpenInvoices', [$result['open_invoices']])
                : lang('App.subscription.changed');

            return $this->done($this->base($restaurantId), $message, $result);
        });
    }

    /**
     * Alan: cancel (1 = dönem sonunda iptal et, 0 = iptali geri al)
     */
    public function cancel(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurantId): ResponseInterface {
            $cancel = $this->flag('cancel') === 1;
            (new SubscriptionService())->setCancelAtPeriodEnd($restaurantId, $cancel);
            service('activity')->detail(['cancel' => $cancel ? 'dönem sonunda' : 'geri alındı']);

            return $this->done($this->base($restaurantId), lang($cancel ? 'App.subscription.cancelScheduled' : 'App.subscription.cancelUndone'));
        });
    }

    public function suspend(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);
        (new SubscriptionService())->suspendManually($restaurantId);

        return $this->done($this->base($restaurantId), lang('App.subscription.suspended'));
    }

    public function resume(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);
        $state = (new SubscriptionService())->resume($restaurantId);

        if ($state !== null) {
            $message = lang('App.subscription.stillSuspended', [lang('App.subscription.reason.' . $state)]);

            return $this->wantsJson()
                ? $this->jsonSuccess(['suspension_reason' => $state], $message)
                : $this->redirectTo($this->base($restaurantId))->with('error', $message);
        }

        return $this->done($this->base($restaurantId), lang('App.subscription.resumed'));
    }

    /**
     * Alan: grace_days (boş = varsayılan eşik)
     */
    public function grace(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurantId): ResponseInterface {
            $raw = trim((string) $this->request->getPost('grace_days'));

            if ($raw !== '' && ! ctype_digit($raw)) {
                throw ValidationException::single('grace_days', lang('App.subscription.graceInvalid'));
            }

            (new SubscriptionService())->setGraceDays($restaurantId, $raw === '' ? null : (int) $raw);
            service('activity')->detail(['grace_days' => $raw === '' ? 'varsayılan' : (int) $raw]);

            return $this->done($this->base($restaurantId), lang('App.subscription.graceSaved'));
        });
    }

    /**
     * Restoran geneli yüzdelik indirim. Alanlar: discount_percent (boş = kaldır; "12,5" kabul edilir),
     * discount_until (Y-m-d, boş = süresiz)
     */
    public function discount(int $restaurantId): ResponseInterface
    {
        $this->requireRestaurant($restaurantId);

        return $this->handle(function () use ($restaurantId): ResponseInterface {
            $percent = str_replace(',', '.', trim((string) $this->request->getPost('discount_percent')));
            $until   = trim((string) $this->request->getPost('discount_until'));

            (new SubscriptionService())->setRestaurantDiscount(
                $restaurantId,
                $percent === '' ? null : $percent,
                $until === '' ? null : $until,
            );

            service('activity')->detail(['percent' => $percent === '' ? 'kaldırıldı' : $percent, 'until' => $until === '' ? 'süresiz' : $until]);

            return $this->done($this->base($restaurantId), lang($percent === '' ? 'App.subscription.discountRemoved' : 'App.subscription.discountSaved'));
        });
    }

    private function base(int $restaurantId): string
    {
        return 'admin/restaurants/' . $restaurantId . '/subscription';
    }
}
