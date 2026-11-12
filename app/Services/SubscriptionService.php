<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Libraries\Billing\Period;
use App\Models\BranchModel;
use App\Models\InvoiceModel;
use App\Models\PlanModel;
use App\Models\RestaurantDomainModel;
use App\Models\RestaurantModel;
use App\Models\SubscriptionModel;
use App\Services\Concerns\Transactional;

/**
 * Abonelik yaşam döngüsü ve paket hakları — docs/ABONELIK_PLANI.md §5, §6.
 *
 * restaurants.suspended_at / suspension_reason alanlarının TEK yazanı bu sınıftır.
 * Askı durumu her zaman reconcile() ile verilerden yeniden hesaplanır; böylece cron,
 * ödeme, iptal ve paket değişimi aynı kuralları paylaşır ve işlem idempotent kalır.
 *
 * Admin ve cron tarafından restoran kimliğiyle çağrılır; panel yalnızca okuma
 * metodlarını (entitlements, canAddBranch, notice) kendi tenant kimliğiyle kullanır.
 */
final class SubscriptionService
{
    use Transactional;

    public const REASONS = ['overdue', 'trial_expired', 'no_subscription', 'manual'];

    // ------------------------------------------------------------------
    // Okuma
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    public function current(int $restaurantId): ?array
    {
        return (new SubscriptionModel())->forRestaurant($restaurantId)->current();
    }

    /**
     * Paket hakları. Güncel abonelik yoksa hiçbir hak yoktur (şube eklenemez).
     *
     * @return array{has_subscription: bool, plan_id: int|null, max_branches: int|null, allow_custom_domain: bool, allow_custom_design: bool}
     */
    public function entitlements(int $restaurantId): array
    {
        $subscription = $this->current($restaurantId);
        $plan         = $subscription === null ? null : (new PlanModel())->find((int) $subscription['plan_id']);

        if ($plan === null) {
            return ['has_subscription' => false, 'plan_id' => null, 'max_branches' => 0, 'allow_custom_domain' => false, 'allow_custom_design' => false];
        }

        return [
            'has_subscription'    => true,
            'plan_id'             => (int) $plan['id'],
            'max_branches'        => $plan['max_branches'] === null ? null : (int) $plan['max_branches'],
            'allow_custom_domain' => (int) $plan['allow_custom_domain'] === 1,
            'allow_custom_design' => (int) $plan['allow_custom_design'] === 1,
        ];
    }

    /**
     * Aktif (silinmemiş, is_active = 1) şube sayısı.
     */
    public function activeBranchCount(int $restaurantId, ?int $exceptBranchId = null): int
    {
        $model = (new BranchModel())->forRestaurant($restaurantId)->where('is_active', 1);

        if ($exceptBranchId !== null) {
            $model->where('id !=', $exceptBranchId);
        }

        return (int) $model->countAllResults();
    }

    public function canAddBranch(int $restaurantId): bool
    {
        $rights = $this->entitlements($restaurantId);

        return $rights['has_subscription']
            && ($rights['max_branches'] === null || $this->activeBranchCount($restaurantId) < $rights['max_branches']);
    }

    /**
     * Bir şube aktif hale gelecekse (yeni, güncelleme veya toggle) çağrılır.
     * $exceptBranchId: güncellenen şubenin kendisi sayılmaz.
     */
    public function assertBranchLimit(int $restaurantId, ?int $exceptBranchId = null): void
    {
        $rights = $this->entitlements($restaurantId);

        if (! $rights['has_subscription']) {
            throw new BusinessRuleException(lang('App.subscription.none'));
        }

        if ($rights['max_branches'] !== null && $this->activeBranchCount($restaurantId, $exceptBranchId) + 1 > $rights['max_branches']) {
            throw new BusinessRuleException(lang('App.subscription.branchLimit', [$rights['max_branches']]));
        }
    }

    /**
     * Hedef paket restoranın mevcut kullanımını karşılıyor mu? (paket düşürme engeli)
     *
     * @param array<string, mixed> $plan
     */
    public function assertPlanFits(int $restaurantId, array $plan): void
    {
        $branches = $this->activeBranchCount($restaurantId);

        if ($plan['max_branches'] !== null && $branches > (int) $plan['max_branches']) {
            throw new BusinessRuleException(lang('App.subscription.planBranchesExceed', [$branches, (int) $plan['max_branches']]));
        }

        if ((int) $plan['allow_custom_domain'] !== 1 && (new RestaurantDomainModel())->forRestaurant($restaurantId)->current() !== null) {
            throw new BusinessRuleException(lang('App.subscription.planNoDomain'));
        }
    }

    /**
     * Abonelik kartı için görünüm verisi (admin + panel ortak).
     *
     * @return array{subscription: array<string, mixed>|null, plan: array<string, mixed>|null, usage: array{branches: int, max_branches: int|null}, days_left: int|null, grace_days: int, can_add_branch: bool}
     */
    public function summary(int $restaurantId, ?string $today = null): array
    {
        $today        = $today ?? Period::today();
        $subscription = $this->current($restaurantId);
        $plan         = $subscription === null ? null : (new PlanModel())->find((int) $subscription['plan_id']);
        $planService  = new PlanService();

        if ($plan !== null) {
            $plan += $planService->texts([(int) $plan['id']])[(int) $plan['id']] ?? ['name' => $plan['code'], 'description' => null];
        }

        if ($subscription !== null) {
            $subscription['plan_name']    = $plan['name'] ?? '';
            $subscription['cycle_label']  = lang('App.subscription.cycle.' . $subscription['billing_cycle']);
            $subscription['status_label'] = lang('App.subscription.status.' . $subscription['status']);
        }

        $branches = $this->activeBranchCount($restaurantId);
        $max      = $plan === null ? 0 : ($plan['max_branches'] === null ? null : (int) $plan['max_branches']);

        return [
            'subscription'   => $subscription,
            'plan'           => $plan,
            'usage'          => ['branches' => $branches, 'max_branches' => $max],
            'days_left'      => $subscription === null ? null : Period::daysBetween($today, (string) $subscription['current_period_end']),
            'grace_days'     => $this->graceDays($subscription),
            'can_add_branch' => $plan !== null && ($max === null || $branches < $max),
        ];
    }

    public function graceDays(?array $subscription): int
    {
        return ($subscription !== null && $subscription['grace_days'] !== null)
            ? (int) $subscription['grace_days']
            : config('Billing')->graceDays;
    }

    /**
     * Panel uyarı bandı. null = gösterilecek bir şey yok.
     *
     * @param array<string, mixed> $restaurant
     *
     * @return array{type: string, level: string, days?: int, reason?: string, count?: int}|null
     */
    public function notice(array $restaurant, ?string $today = null): ?array
    {
        $today = $today ?? Period::today();

        if (! empty($restaurant['suspended_at'])) {
            return ['type' => 'suspended', 'level' => 'danger', 'reason' => (string) $restaurant['suspension_reason']];
        }

        $restaurantId = (int) $restaurant['id'];
        $overdue      = (new InvoiceModel())->forRestaurant($restaurantId)->pendingDueBefore($today);

        if ($overdue !== []) {
            return ['type' => 'overdue', 'level' => 'warning', 'count' => count($overdue)];
        }

        $subscription = $this->current($restaurantId);

        if ($subscription !== null && $subscription['billing_cycle'] === 'trial') {
            $days = Period::daysBetween($today, (string) $subscription['current_period_end']);

            return ['type' => 'trial', 'level' => $days <= config('Billing')->trialReminderDays ? 'warning' : 'info', 'days' => max(0, $days)];
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Yazma (admin + cron)
    // ------------------------------------------------------------------

    /**
     * Restoran için ilk (veya sonlanmış abonelikten sonra yeni) aboneliği başlatır.
     * Ücretli pakette ilk dönemin faturası hemen kesilir (vade = bugün + paymentTermDays).
     */
    public function start(int $restaurantId, int $planId, string $cycle, ?string $today = null, string $discount = '0.00', ?string $notes = null): int
    {
        $today = $today ?? Period::today();
        $plan  = $this->requirePlan($planId);
        $cycle = $this->resolveCycle($plan, $cycle);

        if ($this->current($restaurantId) !== null) {
            throw new BusinessRuleException(lang('App.subscription.alreadyCurrent'));
        }

        return $this->atomic(function () use ($restaurantId, $plan, $cycle, $today, $discount, $notes): int {
            $price = match ($cycle) {
                'monthly' => (string) $plan['price_monthly'],
                'yearly'  => (string) $plan['price_yearly'],
                default   => '0.00',
            };

            $model = (new SubscriptionModel())->forRestaurant($restaurantId);
            $id    = (int) $model->insert([
                'restaurant_id'        => $restaurantId,
                'plan_id'              => (int) $plan['id'],
                'billing_cycle'        => $cycle,
                'status'               => $cycle === 'trial' ? 'trialing' : 'active',
                'is_current'           => 1,
                'price'                => $price,
                'currency_code'        => (string) $plan['currency_code'],
                'started_at'           => $today,
                'current_period_start' => $today,
                'current_period_end'   => Period::end($today, $cycle, $plan['trial_days'] === null ? null : (int) $plan['trial_days']),
                'notes'                => $notes === '' ? null : $notes,
            ]);

            $subscription = $model->find($id);

            if ($cycle !== 'trial' && InvoiceService::cents($price) > 0) {
                (new InvoiceService())->issue(
                    $subscription,
                    (string) $subscription['current_period_start'],
                    (string) $subscription['current_period_end'],
                    $today,
                    Period::addDays($today, config('Billing')->paymentTermDays),
                    $discount,
                );
            }

            $this->reconcile($restaurantId, $today);

            return $id;
        });
    }

    /**
     * Paket değişimi: eski abonelik `ended`; yeni abonelik bugün başlar ve tam dönem
     * faturası kesilir. Eski aboneliğin açık faturaları OTOMATİK iptal edilmez.
     *
     * @return array{subscription_id: int, open_invoices: int}
     */
    public function changePlan(int $restaurantId, int $planId, string $cycle, string $discount = '0.00', ?string $notes = null, ?string $today = null): array
    {
        $today = $today ?? Period::today();
        $plan  = $this->requirePlan($planId);
        $this->resolveCycle($plan, $cycle);
        $this->assertPlanFits($restaurantId, $plan);

        $id = $this->atomic(function () use ($restaurantId, $plan, $cycle, $discount, $notes, $today): int {
            $current = $this->current($restaurantId);

            if ($current !== null) {
                $this->endSubscription($current);
            }

            return $this->start($restaurantId, (int) $plan['id'], $cycle, $today, $discount, $notes);
        });

        $open = (new InvoiceModel())->forRestaurant($restaurantId)
            ->where('subscription_id !=', $id)
            ->where('status', 'pending')
            ->countAllResults();

        return ['subscription_id' => $id, 'open_invoices' => (int) $open];
    }

    /**
     * Dönem sonunda iptal işaretini açar/kapatır.
     */
    public function setCancelAtPeriodEnd(int $restaurantId, bool $cancel): void
    {
        $current = $this->current($restaurantId) ?? throw new BusinessRuleException(lang('App.subscription.none'));

        (new SubscriptionModel())->forRestaurant($restaurantId)->update((int) $current['id'], [
            'cancel_at_period_end' => $cancel ? 1 : 0,
        ]);
    }

    /**
     * Restorana özel gecikme eşiği (null = config varsayılanı).
     */
    public function setGraceDays(int $restaurantId, ?int $days, ?string $today = null): void
    {
        $current = $this->current($restaurantId) ?? throw new BusinessRuleException(lang('App.subscription.none'));

        if ($days !== null && ($days < 0 || $days > 365)) {
            throw ValidationException::single('grace_days', lang('App.subscription.graceInvalid'));
        }

        $this->atomic(function () use ($restaurantId, $current, $days, $today): void {
            (new SubscriptionModel())->forRestaurant($restaurantId)->update((int) $current['id'], ['grace_days' => $days]);
            $this->reconcile($restaurantId, $today);
        });
    }

    /**
     * Admin'in elle askıya alması; ödeme durumundan bağımsızdır ve yalnızca resume() ile kalkar.
     */
    public function suspendManually(int $restaurantId, ?string $today = null): void
    {
        $this->atomic(function () use ($restaurantId, $today): void {
            (new RestaurantModel())->update($restaurantId, [
                'suspended_at'      => date('Y-m-d H:i:s'),
                'suspension_reason' => 'manual',
            ]);
            $this->reconcile($restaurantId, $today);
        });

        $this->afterSuspensionChange($restaurantId);
    }

    /**
     * Elle askıyı kaldırır; restoran başka bir nedenle (gecikme, deneme bitişi) hâlâ
     * askıda kalması gerekiyorsa o nedenle askıda kalır. Yeni durum döner (null = açık).
     */
    public function resume(int $restaurantId, ?string $today = null): ?string
    {
        $state = $this->atomic(function () use ($restaurantId, $today): ?string {
            $restaurant = (new RestaurantModel())->find($restaurantId);

            if ($restaurant !== null && $restaurant['suspension_reason'] === 'manual') {
                (new RestaurantModel())->update($restaurantId, ['suspended_at' => null, 'suspension_reason' => null]);
            }

            return $this->reconcile($restaurantId, $today)['after'];
        });

        $this->afterSuspensionChange($restaurantId);

        return $state;
    }

    /**
     * Restoranın askı durumunu ve güncel aboneliğin statüsünü verilerden yeniden hesaplar.
     *
     *  manual (admin)           → dokunulmaz
     *  güncel abonelik yok      → no_subscription
     *  deneme süresi dolmuş     → trial_expired
     *  vade + eşik geçmiş fatura → overdue  (eski aboneliklerden kalanlar dahil)
     *
     * @return array{before: string|null, after: string|null}
     */
    public function reconcile(int $restaurantId, ?string $today = null): array
    {
        $today      = $today ?? Period::today();
        $restaurant = (new RestaurantModel())->withDeleted()->find($restaurantId);

        if ($restaurant === null) {
            return ['before' => null, 'after' => null];
        }

        $before       = $restaurant['suspended_at'] === null ? null : (string) ($restaurant['suspension_reason'] ?? 'manual');
        $subscription = $this->current($restaurantId);
        $invoices     = (new InvoiceModel())->forRestaurant($restaurantId);
        $grace        = $this->graceDays($subscription);

        if ($before === 'manual') {
            $after = 'manual';
        } elseif ($subscription === null) {
            $after = 'no_subscription';
        } elseif ($subscription['billing_cycle'] === 'trial' && (string) $subscription['current_period_end'] < $today) {
            $after = 'trial_expired';
        } elseif ($invoices->pendingDueBefore(Period::addDays($today, -$grace)) !== []) {
            $after = 'overdue';
        } else {
            $after = null;
        }

        if ($after !== $before) {
            (new RestaurantModel())->withDeleted()->update($restaurantId, [
                'suspended_at'      => $after === null ? null : ($restaurant['suspended_at'] ?? date('Y-m-d H:i:s')),
                'suspension_reason' => $after,
            ]);
        }

        if ($subscription !== null) {
            if ($after !== null) {
                $status = 'suspended';
            } elseif ($subscription['billing_cycle'] === 'trial') {
                $status = 'trialing';
            } elseif ((new InvoiceModel())->forRestaurant($restaurantId)->pendingDueBefore($today) !== []) {
                $status = 'past_due';
            } else {
                $status = 'active';
            }

            if ($status !== $subscription['status']) {
                (new SubscriptionModel())->forRestaurant($restaurantId)->update((int) $subscription['id'], [
                    'status'       => $status,
                    'suspended_at' => $status === 'suspended' ? ($subscription['suspended_at'] ?? date('Y-m-d H:i:s')) : null,
                ]);
            }
        }

        if (($after === null) !== ($before === null)) {
            $this->afterSuspensionChange($restaurantId);
        }

        return ['before' => $before, 'after' => $after];
    }

    /**
     * Aboneliği sonlandırır (paket değişimi veya dönem sonu iptali).
     *
     * @param array<string, mixed> $subscription
     */
    public function endSubscription(array $subscription): void
    {
        (new SubscriptionModel())->forRestaurant((int) $subscription['restaurant_id'])->update((int) $subscription['id'], [
            'status'     => 'ended',
            'is_current' => null,
            'ended_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function requirePlan(int $planId): array
    {
        $plan = (new PlanModel())->find($planId);

        if ($plan === null || (int) $plan['is_active'] !== 1) {
            throw ValidationException::single('plan_id', lang('App.subscription.planInvalid'));
        }

        return $plan;
    }

    /**
     * Deneme paketinde dönem her zaman `trial`; ücretli pakette monthly|yearly zorunlu.
     *
     * @param array<string, mixed> $plan
     */
    private function resolveCycle(array $plan, string $cycle): string
    {
        if ($plan['trial_days'] !== null) {
            return 'trial';
        }

        if (! in_array($cycle, ['monthly', 'yearly'], true)) {
            throw ValidationException::single('billing_cycle', lang('App.subscription.cycleInvalid'));
        }

        return $cycle;
    }

    private function afterSuspensionChange(int $restaurantId): void
    {
        (new MenuService())->invalidateRestaurant($restaurantId);
        (new DomainService())->forgetRestaurant($restaurantId);
    }
}
