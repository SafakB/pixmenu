<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\LanguageModel;
use App\Models\PlanModel;
use App\Models\PlanTranslationModel;
use App\Services\Concerns\Transactional;

/**
 * Paket yönetimi (yalnızca admin) — docs/ABONELIK_PLANI.md §2, §6.
 * Abonelikte (geçmiş dahil) kullanılan paket silinemez, yalnızca pasifleştirilir.
 */
final class PlanService
{
    use Transactional;

    /** Paket çevirilerinde zorunlu dil. */
    public const REQUIRED_LANG = 'tr';

    public function __construct(private readonly TranslationService $translations = new TranslationService())
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function languages(): array
    {
        return (new LanguageModel())->ordered(true);
    }

    /**
     * Paket adları (tr → en → mevcut ilk çeviri).
     *
     * @param list<int> $planIds
     *
     * @return array<int, array{name: string, description: string|null}>
     */
    public function texts(array $planIds): array
    {
        $resolver = service('languageResolver');
        $rows     = $resolver->resolveMany(PlanTranslationModel::class, $planIds, $resolver->allergenChain(null, null));
        $out      = [];

        foreach ($rows as $id => $row) {
            $out[(int) $id] = ['name' => (string) $row['name'], 'description' => $row['description'] ?? null];
        }

        return $out;
    }

    public function name(int $planId): string
    {
        return $this->texts([$planId])[$planId]['name'] ?? '#' . $planId;
    }

    /**
     * Paket listesi + adlar + kullanım sayısı (görünüm için).
     *
     * @return list<array<string, mixed>>
     */
    public function listing(bool $activeOnly = false): array
    {
        $plans = (new PlanModel())->ordered($activeOnly);
        $texts = $this->texts(array_map('intval', array_column($plans, 'id')));
        $usage = [];

        if ($plans !== []) {
            $rows = db_connect()->table('subscriptions')
                ->select('plan_id, COUNT(*) AS c')
                ->where('is_current', 1)
                ->groupBy('plan_id')
                ->get()
                ->getResultArray();
            $usage = array_column($rows, 'c', 'plan_id');
        }

        return array_map(static fn (array $plan): array => $plan + [
            'name'          => $texts[(int) $plan['id']]['name'] ?? $plan['code'],
            'description'   => $texts[(int) $plan['id']]['description'] ?? null,
            'current_count' => (int) ($usage[$plan['id']] ?? 0),
        ], $plans);
    }

    /**
     * @param array<string, mixed>                 $data
     * @param array<string, array<string, string>> $translations
     */
    public function create(array $data, array $translations): int
    {
        $data  = $this->validate($data, null);
        $codes = $this->languageCodes();
        $this->assertTranslations($translations, $codes);

        return $this->atomic(function () use ($data, $translations, $codes): int {
            $id = (int) (new PlanModel())->insert($data);
            $this->translations->sync(PlanTranslationModel::class, 'plan_id', $id, $translations, $codes, self::REQUIRED_LANG);

            return $id;
        });
    }

    /**
     * Fiyat değişikliği mevcut abonelikleri etkilemez (abonelikteki `price` atama anında sabitlenir).
     *
     * @param array<string, mixed>                 $plan
     * @param array<string, mixed>                 $data
     * @param array<string, array<string, string>> $translations
     */
    public function update(array $plan, array $data, array $translations): void
    {
        $id    = (int) $plan['id'];
        $data  = $this->validate($data, $id);
        $codes = $this->languageCodes();
        $this->assertTranslations($translations, $codes);

        $this->atomic(function () use ($id, $data, $translations, $codes): void {
            (new PlanModel())->update($id, $data);
            $this->translations->sync(PlanTranslationModel::class, 'plan_id', $id, $translations, $codes, self::REQUIRED_LANG);
        });
    }

    /**
     * @param array<string, mixed> $plan
     */
    public function toggle(array $plan): void
    {
        $active = (int) $plan['is_active'] === 1;

        if ($active && $plan['code'] === config('Billing')->defaultPlanCode) {
            throw new BusinessRuleException(lang('App.plan.defaultCannotDeactivate'));
        }

        (new PlanModel())->update((int) $plan['id'], ['is_active' => $active ? 0 : 1]);
    }

    /**
     * @param array<string, mixed> $plan
     */
    public function delete(array $plan): void
    {
        $id    = (int) $plan['id'];
        $usage = db_connect()->table('subscriptions')->where('plan_id', $id)->countAllResults()
            + db_connect()->table('invoices')->where('plan_id', $id)->countAllResults();

        if ($usage > 0) {
            throw new BusinessRuleException(lang('App.plan.inUse'));
        }

        if ($plan['code'] === config('Billing')->defaultPlanCode) {
            throw new BusinessRuleException(lang('App.plan.defaultCannotDeactivate'));
        }

        (new PlanModel())->delete($id);
    }

    /**
     * @param list<int> $ids
     */
    public function reorder(array $ids): void
    {
        $model = new PlanModel();

        if ($model->whereIn('id', $ids)->countAllResults() !== count($ids)) {
            throw ValidationException::single('ids', lang('App.reorder.invalid'));
        }

        $this->atomic(static function () use ($ids, $model): void {
            foreach ($ids as $index => $id) {
                $model->update($id, ['sort_order' => $index]);
            }
        });
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed> veritabanına yazılacak alanlar
     */
    private function validate(array $data, ?int $exceptId): array
    {
        $errors = [];
        $code   = (string) ($data['code'] ?? '');

        if (preg_match('/^[a-z][a-z0-9_]{1,29}$/', $code) !== 1) {
            $errors['code'] = lang('App.plan.codeInvalid');
        } else {
            $model = (new PlanModel())->where('code', $code);

            if ($exceptId !== null) {
                $model->where('id !=', $exceptId);
            }

            if ($model->countAllResults() > 0) {
                $errors['code'] = lang('App.plan.codeTaken');
            }
        }

        foreach (['price_monthly', 'price_yearly'] as $field) {
            $value = $this->money((string) ($data[$field] ?? ''));

            if ($value === null) {
                $errors[$field] = lang('App.plan.priceInvalid');
            } else {
                $data[$field] = $value;
            }
        }

        foreach (['max_branches', 'trial_days'] as $field) {
            $raw = trim((string) ($data[$field] ?? ''));

            if ($raw === '') {
                $data[$field] = null;
            } elseif (! ctype_digit($raw) || (int) $raw < 1 || (int) $raw > 9999) {
                $errors[$field] = lang('App.plan.' . ($field === 'max_branches' ? 'maxBranchesInvalid' : 'trialDaysInvalid'));
            } else {
                $data[$field] = (int) $raw;
            }
        }

        $badge = preg_replace('/\s+/', ' ', trim((string) ($data['badge_class'] ?? '')));

        if ($badge !== '' && preg_match('/^[A-Za-z0-9 _:-]{1,100}$/', $badge) !== 1) {
            $errors['badge_class'] = lang('App.plan.badgeClassInvalid');
        }

        $currency = strtoupper(trim((string) ($data['currency_code'] ?? '')));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors['currency_code'] = lang('App.restaurant.currencyInvalid');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'code'                => $code,
            'badge_class'         => $badge === '' ? null : $badge,
            'max_branches'        => $data['max_branches'],
            'allow_custom_domain' => (int) ! empty($data['allow_custom_domain']),
            'allow_custom_design' => (int) ! empty($data['allow_custom_design']),
            'trial_days'          => $data['trial_days'],
            'price_monthly'       => $data['price_monthly'],
            'price_yearly'        => $data['price_yearly'],
            'currency_code'       => $currency,
            'is_featured'         => (int) ! empty($data['is_featured']),
            'is_active'           => (int) ! empty($data['is_active']),
            'sort_order'          => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * "1.499,90" / "1499.90" / "1499,9" → "1499.90"; geçersizse null.
     */
    public function money(string $raw): ?string
    {
        $raw = str_replace(' ', '', trim($raw));

        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace(['.', ','], ['', '.'], $raw);
        }

        if (preg_match('/^\d{1,8}(\.\d{1,2})?$/', $raw) !== 1) {
            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }

    /**
     * @return list<string>
     */
    private function languageCodes(): array
    {
        return array_map('strval', array_column($this->languages(), 'code'));
    }

    /**
     * @param array<string, array<string, string>> $translations
     * @param list<string>                         $codes
     */
    private function assertTranslations(array $translations, array $codes): void
    {
        $errors = $this->translations->validate(PlanTranslationModel::class, $translations, $codes, self::REQUIRED_LANG);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
