<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Billing\Period;
use App\Models\AllergenModel;
use App\Models\BranchModel;
use App\Models\InvoiceModel;
use App\Models\LanguageModel;
use App\Models\RestaurantModel;
use App\Models\RestaurantTranslationModel;
use App\Models\RestaurantUserModel;
use App\Models\SubscriptionModel;
use App\Services\InvoiceService;

final class DashboardController extends AdminBaseController
{
    /**
     * View: admin/dashboard — stats{restaurants, branches, users, languages, allergens},
     *   billing{
     *     overdue_count, overdue_sum{para birimi: tutar},
     *     overdue[{id, number, restaurant_id, restaurant_name, total, currency_code, due_date, days_late}] (en fazla 5),
     *     trials[{restaurant_id, restaurant_name, current_period_end, days_left}] (bu hafta bitenler),
     *     suspended[{id, name, suspension_reason, suspended_at}]
     *   }
     */
    public function index(): string
    {
        $today = Period::today();

        $overdue = (new InvoiceModel())->withoutTenantScope()
            ->where('status', 'pending')->where('due_date <', $today)
            ->orderBy('due_date', 'ASC')->findAll();

        $trials = (new SubscriptionModel())->withoutTenantScope()
            ->where('is_current', 1)->where('status', 'trialing')
            ->where('current_period_end >=', $today)->where('current_period_end <=', Period::addDays($today, 7))
            ->orderBy('current_period_end', 'ASC')->findAll();

        $suspended = (new RestaurantModel())->where('suspended_at IS NOT NULL', null, false)->orderBy('suspended_at', 'DESC')->findAll();

        $ids   = array_values(array_unique(array_merge(
            array_map('intval', array_column($overdue, 'restaurant_id')),
            array_map('intval', array_column($trials, 'restaurant_id')),
            array_map('intval', array_column($suspended, 'id')),
        )));
        $names = $this->names($ids);
        $sums  = [];

        foreach ($overdue as $invoice) {
            $sums[$invoice['currency_code']] = ($sums[$invoice['currency_code']] ?? 0) + InvoiceService::cents((string) $invoice['total']);
        }

        return $this->render('admin/dashboard', [
            'stats' => [
                'restaurants' => (new RestaurantModel())->countAllResults(),
                'branches'    => (new BranchModel())->withoutTenantScope()->countAllResults(),
                'users'       => (new RestaurantUserModel())->withoutTenantScope()->countAllResults(),
                'languages'   => (new LanguageModel())->countAllResults(),
                'allergens'   => (new AllergenModel())->countAllResults(),
            ],
            'billing' => [
                'overdue_count' => count($overdue),
                'overdue_sum'   => array_map(static fn (int $c): string => InvoiceService::money($c), $sums),
                'overdue'       => array_map(static fn (array $i): array => [
                    'id'              => (int) $i['id'],
                    'number'          => $i['number'],
                    'restaurant_id'   => (int) $i['restaurant_id'],
                    'restaurant_name' => $names[(int) $i['restaurant_id']] ?? '#' . $i['restaurant_id'],
                    'total'           => $i['total'],
                    'currency_code'   => $i['currency_code'],
                    'due_date'        => $i['due_date'],
                    'days_late'       => Period::daysBetween((string) $i['due_date'], $today),
                ], array_slice($overdue, 0, 5)),
                'trials' => array_map(static fn (array $s): array => [
                    'restaurant_id'      => (int) $s['restaurant_id'],
                    'restaurant_name'    => $names[(int) $s['restaurant_id']] ?? '#' . $s['restaurant_id'],
                    'current_period_end' => $s['current_period_end'],
                    'days_left'          => Period::daysBetween($today, (string) $s['current_period_end']),
                ], $trials),
                'suspended' => array_map(static fn (array $r): array => [
                    'id'                => (int) $r['id'],
                    'name'              => $names[(int) $r['id']] ?? $r['slug'],
                    'suspension_reason' => $r['suspension_reason'],
                    'suspended_at'      => $r['suspended_at'],
                ], $suspended),
            ],
        ]);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, string>
     */
    private function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $restaurants = (new RestaurantModel())->withDeleted()->whereIn('id', $ids)->findAll();
        $chains      = [];

        foreach ($restaurants as $r) {
            $chains[(int) $r['id']] = [(int) $r['default_language_id']];
        }

        $resolved = service('languageResolver')->resolveManyWithChains(RestaurantTranslationModel::class, $chains);
        $out      = [];

        foreach ($restaurants as $r) {
            $out[(int) $r['id']] = (string) ($resolved[(int) $r['id']]['name'] ?? $r['slug']);
        }

        return $out;
    }
}
