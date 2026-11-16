<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\CurrencyModel;
use App\Models\InvoiceModel;
use App\Models\LanguageModel;
use App\Models\PlanModel;
use App\Models\RestaurantUserModel;
use App\Services\Concerns\Transactional;
use App\Validation\PixmenuRules;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * Self-servis kayıt (/kayit): restoran + owner + ilk şube + abonelik tek transaction'da.
 *
 * Mevcut servisler yeniden kullanılır: RestaurantService::create (restoran, diller, owner,
 * abonelik — ücretli pakette ilk dönem faturası `pending` kesilir), BranchService::create (şube).
 * Hata anahtarları form alanlarına çevrilir (ör. restoran `translations.tr.name` → `restaurant_name`).
 *
 * Google ile kayıtta kimlik ($google) YALNIZCA oturumdan gelir; e-posta Google'ınkidir ve
 * doğrulanmış sayılır, şifre alınmaz.
 */
final class SignupService
{
    use Transactional;

    public const DEFAULT_BRANCH_NAME = 'Merkez';

    /** Restoranın varsayılan (menü) dili; aktif değilse ilk aktif dil. */
    private const DEFAULT_LANGUAGE = 'tr';

    /**
     * Formdan gelen ham değerleri normalize eder.
     *
     * @param array<string, mixed>                                $raw
     * @param array{sub: string, email: string, name: string}|null $google
     *
     * @return array{name: string, email: string, password: string, password_confirm: string, terms: bool,
     *               restaurant_name: string, branch_name: string, phone: string, address: string,
     *               currency_code: string, language_ids: list<int>, plan_id: int, billing_cycle: string}
     */
    public function normalize(array $raw, ?array $google): array
    {
        $str = static fn (string $key): string => trim((string) (is_scalar($raw[$key] ?? null) ? $raw[$key] : ''));

        $languageIds = [];

        foreach ((array) ($raw['language_ids'] ?? []) as $id) {
            $languageIds[] = (is_string($id) && ctype_digit($id)) || is_int($id) ? (int) $id : -1;
        }

        $name = $str('name');

        return [
            'name'             => $name === '' && $google !== null ? trim($google['name']) : $name,
            'email'            => $google !== null ? mb_strtolower(trim($google['email'])) : mb_strtolower($str('email')),
            'password'         => $google !== null ? '' : (string) ($raw['password'] ?? ''),
            'password_confirm' => $google !== null ? '' : (string) ($raw['password_confirm'] ?? ''),
            'terms'            => in_array((string) ($raw['terms'] ?? ''), ['1', 'on', 'true'], true),
            'restaurant_name'  => $str('restaurant_name'),
            'branch_name'      => $str('branch_name') === '' ? self::DEFAULT_BRANCH_NAME : $str('branch_name'),
            'phone'            => $str('phone'),
            'address'          => $str('address'),
            'currency_code'    => strtoupper($str('currency_code')),
            'language_ids'     => array_values(array_unique($languageIds)),
            'plan_id'          => (int) $str('plan_id'),
            'billing_cycle'    => $str('billing_cycle'),
        ];
    }

    /**
     * Ön doğrulama (DB'ye yazmadan): form alanı => mesaj. Boş dizi = geçerli.
     *
     * @param array<string, mixed>                                $input normalize() çıktısı
     * @param array{sub: string, email: string, name: string}|null $google
     *
     * @return array<string, string>
     */
    public function validate(array $input, ?array $google): array
    {
        $errors = [];

        if ($input['name'] === '' || mb_strlen($input['name']) > 100) {
            $errors['name'] = lang('App.user.nameRequired');
        }

        if ($input['email'] === '' || mb_strlen($input['email']) > 190 || filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = lang('App.user.emailInvalid');
        } elseif ((new RestaurantUserModel())->emailExistsGlobally($input['email'])) {
            $errors['email'] = lang('App.user.emailTaken');
        }

        if ($google === null) {
            if (! PixmenuRules::isStrongPassword($input['password'])) {
                $errors['password'] = lang('App.user.passwordWeak');
            } elseif ($input['password'] !== $input['password_confirm']) {
                $errors['password_confirm'] = lang('App.user.passwordConfirm');
            }
        }

        if (! $input['terms']) {
            $errors['terms'] = lang('App.signup.termsRequired');
        }

        if ($input['restaurant_name'] === '' || mb_strlen($input['restaurant_name']) > 150) {
            $errors['restaurant_name'] = lang('App.signup.restaurantName');
        }

        if (mb_strlen($input['branch_name']) > 150) {
            $errors['branch_name'] = lang('App.signup.branchName');
        }

        if (mb_strlen($input['phone']) > 30) {
            $errors['phone'] = lang('App.field.phone');
        }

        if (mb_strlen($input['address']) > 500) {
            $errors['address'] = lang('App.signup.addressTooLong');
        }

        if ($input['currency_code'] === '' || ! (new CurrencyModel())->isSelectable($input['currency_code'])) {
            $errors['currency_code'] = lang('App.signup.currencyInvalid');
        }

        $active = array_map('intval', array_column((new LanguageModel())->ordered(true), 'id'));

        if (array_diff($input['language_ids'], $active) !== []) {
            $errors['language_ids'] = lang('App.language.invalidSelection');
        }

        $plan = $input['plan_id'] > 0 ? (new PlanModel())->find($input['plan_id']) : null;

        if ($plan === null || (int) $plan['is_active'] !== 1) {
            $errors['plan_id'] = lang('App.subscription.planInvalid');
        } elseif ($plan['trial_days'] === null && ! in_array($input['billing_cycle'], ['monthly', 'yearly'], true)) {
            $errors['billing_cycle'] = lang('App.subscription.cycleInvalid');
        }

        return $errors;
    }

    /**
     * Hesabı açar. Herhangi bir aşama başarısız olursa hiçbir şey kalmaz (rollback).
     *
     * @param array<string, mixed>                                $input normalize() çıktısı
     * @param array{sub: string, email: string, name: string}|null $google
     *
     * @return array{restaurant_id: int, user: array<string, mixed>, invoice_id: int|null, plan: array<string, mixed>, trial_ends_at: string|null}
     *
     * @throws ValidationException
     */
    public function register(array $input, ?array $google): array
    {
        $errors = $this->validate($input, $google);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        [$defaultId, $defaultCode] = $this->defaultLanguage();
        $languageIds               = array_values(array_unique(array_merge([$defaultId], $input['language_ids'])));
        $stage                     = 'restaurant';

        try {
            return $this->atomic(function () use ($input, $google, $defaultId, $defaultCode, $languageIds, &$stage): array {
                $restaurantId = (new RestaurantService())->create(
                    [
                        'slug'                => '',
                        'default_language_id' => $defaultId,
                        'currency_code'       => $input['currency_code'],
                        'is_active'           => 1,
                        'language_ids'        => $languageIds,
                        'plan_id'             => $input['plan_id'],
                        'billing_cycle'       => $input['billing_cycle'],
                    ],
                    [$defaultCode => ['name' => $input['restaurant_name']]],
                    null,
                    [
                        'name'           => $input['name'],
                        'email'          => $input['email'],
                        'password'       => $input['password'],
                        'google_id'      => $google['sub'] ?? null,
                        'email_verified' => $google !== null,
                    ],
                );

                $stage = 'branch';
                (new BranchService(Scope::admin($restaurantId)))->create(
                    ['slug' => '', 'default_language_id' => $defaultId, 'phone' => $input['phone'], 'is_active' => 1, 'sort_order' => 0],
                    [$defaultCode => ['name' => $input['branch_name'], 'address' => $input['address']]],
                );

                $stage        = 'done';
                $user         = (new RestaurantUserModel())->forRestaurant($restaurantId)->where('email', $input['email'])->first();
                $subscription = (new SubscriptionService())->current($restaurantId);
                $invoice      = $subscription === null ? null
                    : (new InvoiceModel())->forRestaurant($restaurantId)->openForSubscription((int) $subscription['id']);

                return [
                    'restaurant_id' => $restaurantId,
                    'user'          => $user,
                    'invoice_id'    => $invoice === null ? null : (int) $invoice['id'],
                    'plan'          => (new PlanModel())->find((int) $subscription['plan_id']),
                    'trial_ends_at' => ($subscription['billing_cycle'] ?? null) === 'trial' ? (string) $subscription['current_period_end'] : null,
                ];
            });
        } catch (ValidationException $e) {
            throw new ValidationException($this->remap($e->errors(), $stage, $defaultCode));
        } catch (DatabaseException $e) {
            // Aynı e-postayla eşzamanlı iki kayıt: unique anahtar (MySQL 1062).
            if ((int) $e->getCode() === 1062) {
                throw ValidationException::single('email', lang('App.user.emailTaken'));
            }

            throw $e;
        }
    }

    /**
     * @return array{0: int, 1: string} [id, code]
     */
    public function defaultLanguage(): array
    {
        $languages = new LanguageModel();
        $row       = $languages->where('code', self::DEFAULT_LANGUAGE)->where('is_active', 1)->first()
            ?? $languages->ordered(true)[0] ?? null;

        if ($row === null) {
            throw ValidationException::single('language_ids', lang('App.language.atLeastOne'));
        }

        return [(int) $row['id'], (string) $row['code']];
    }

    /**
     * Servis hata anahtarlarını kayıt formunun alanlarına çevirir.
     *
     * @param array<string, string> $errors
     *
     * @return array<string, string>
     */
    private function remap(array $errors, string $stage, string $code): array
    {
        $map = $stage === 'branch'
            ? ["translations.{$code}.name" => 'branch_name', "translations.{$code}.address" => 'address', 'slug' => 'branch_name']
            : ["translations.{$code}.name" => 'restaurant_name', 'slug' => 'restaurant_name', 'default_language_id' => 'language_ids'];

        $out = [];

        foreach ($errors as $key => $message) {
            $out[$map[$key] ?? $key] ??= $message;
        }

        return $out;
    }
}
