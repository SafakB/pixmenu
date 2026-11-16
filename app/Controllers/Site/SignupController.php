<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use App\Controllers\Panel\GoogleAuthController;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\CurrencyModel;
use App\Models\InvoiceModel;
use App\Models\LanguageModel;
use App\Services\AccountNotifier;
use App\Services\PlanService;
use App\Services\SignupService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Self-servis kayıt sihirbazı (/kayit): Hesap → İşletme → Paket, tek POST.
 *
 * Başarılı kayıtta kullanıcı otomatik giriş yapar; deneme paketinde panele, ücretli pakette
 * ilk dönem faturasının ödeme ekranına yönlendirilir. Spam önlemi: honeypot (rota filtresi),
 * IP başına dakikalık deneme ve günlük hesap sınırı.
 */
final class SignupController extends BaseController
{
    /**
     * View: site/signup — plans (PlanService::publicListing), taxRate, currencies[{code, name, symbol}],
     *       languages[{id, code, name, native_name}], defaultLanguageId, google{email, name}|null,
     *       googleUrl|null, preselect{plan_id, billing_cycle}, error|null, loginUrl
     */
    public function new(): ResponseInterface|string
    {
        if (service('panelAuth')->check()) {
            return $this->redirectTo('panel');
        }

        if ($this->request->getGet('google') === '0') {
            session()->remove(GoogleAuthController::SIGNUP_KEY);
        }

        return $this->form(null);
    }

    /**
     * POST /kayit
     */
    public function create(): ResponseInterface
    {
        if (service('panelAuth')->check()) {
            return $this->redirectTo('panel');
        }

        $config = config('Pixmenu');
        $ipKey  = sha1($this->request->getIPAddress());

        if (! service('throttler')->check('signup_try_' . $ipKey, $config->signupAttemptsPerMinute, MINUTE)) {
            return $this->response->setStatusCode(429)->setBody($this->form(lang('App.signup.throttled')));
        }

        $google = $this->pendingGoogle();

        if ($google === null && $this->request->getPost('via') === 'google') {
            return redirect()->to(site_url('kayit'))->with('error', lang('App.signup.googleExpired'));
        }

        $service = new SignupService();
        $input   = $service->normalize((array) $this->request->getPost(), $google);
        $errors  = $service->validate($input, $google);

        if ($errors !== []) {
            return $this->failValidation($errors);
        }

        // Günlük hesap kotası yalnızca geçerli başvurularda tüketilir (yazım hataları saymaz).
        if (! service('throttler')->check('signup_ok_' . $ipKey, $config->signupsPerIpPerDay, DAY)) {
            return $this->response->setStatusCode(429)->setBody($this->form(lang('App.signup.throttled')));
        }

        try {
            $result = $service->register($input, $google);
        } catch (ValidationException $e) {
            return $this->failValidation($e->errors());
        } catch (BusinessRuleException $e) {
            return $this->failRule($e->getMessage());
        }

        session()->remove(GoogleAuthController::SIGNUP_KEY);
        service('panelAuth')->login($result['user']);

        $plan     = $result['plan'];
        $activity = service('activity');
        $activity->record('signup.register');
        $activity->detail([
            'plan'  => $plan['code'] ?? null,
            'cycle' => $plan['trial_days'] === null ? $input['billing_cycle'] : 'trial',
            'via'   => $google === null ? 'email' : 'google',
        ]);

        $invoice = $result['invoice_id'] === null ? null
            : (new InvoiceModel())->forRestaurant($result['restaurant_id'])->find($result['invoice_id']);

        (new AccountNotifier())->welcome(
            $result['user'],
            $input['restaurant_name'],
            (new PlanService())->name((int) $plan['id']),
            $result['trial_ends_at'],
            $invoice,
            $google !== null,
        );

        if ($result['invoice_id'] !== null) {
            return $this->redirectTo('panel/subscription/invoices/' . $result['invoice_id'] . '/pay')
                ->with('success', lang('App.signup.welcomePaid'));
        }

        return $this->redirectTo('panel')->with('success', $plan['trial_days'] === null
            ? lang('App.signup.welcomePaid')
            : lang('App.signup.welcomeTrial', [(int) $plan['trial_days']]));
    }

    // ------------------------------------------------------------------

    private function form(?string $error): string
    {
        $plans       = (new PlanService())->publicListing();
        $google      = $this->pendingGoogle();
        $googleOAuth = service('googleOAuth');

        // Ön seçim: ?plan=kod&cycle=… (landing fiyat kartlarından), yoksa varsayılan deneme paketi.
        $wanted   = trim((string) $this->request->getGet('plan'));
        $default  = config('Billing')->defaultPlanCode;
        $selected = null;

        foreach ($plans as $p) {
            if ($p['code'] === $wanted) {
                $selected = $p['id'];
            }
        }

        foreach ($plans as $p) {
            if ($selected === null && $p['code'] === $default) {
                $selected = $p['id'];
            }
        }

        $cycle = $this->request->getGet('cycle') === 'yearly' ? 'yearly' : 'monthly';
        $query = array_filter(['intent' => 'register', 'plan' => $wanted, 'cycle' => $this->request->getGet('cycle') === 'yearly' ? 'yearly' : '']);

        [$defaultLanguageId] = (new SignupService())->defaultLanguage();

        return view('site/signup', [
            'plans'             => $plans,
            'taxRate'           => config('Billing')->taxRate,
            'currencies'        => (new CurrencyModel())->options(),
            'languages'         => (new LanguageModel())->ordered(true),
            'defaultLanguageId' => $defaultLanguageId,
            'google'            => $google === null ? null : ['email' => $google['email'], 'name' => $google['name']],
            'googleUrl'         => $googleOAuth->isEnabled() ? site_url('panel/auth/google') . '?' . http_build_query($query) : null,
            'preselect'         => ['plan_id' => $selected ?? ($plans[0]['id'] ?? null), 'billing_cycle' => $cycle],
            'error'             => $error,
            'loginUrl'          => site_url('panel/login'),
        ]);
    }

    /**
     * Oturumdaki (süresi dolmamış) Google kaydı kimliği.
     *
     * @return array{sub: string, email: string, name: string}|null
     */
    private function pendingGoogle(): ?array
    {
        $data = session()->get(GoogleAuthController::SIGNUP_KEY);

        if (! is_array($data) || ! isset($data['sub'], $data['email'], $data['at'])) {
            return null;
        }

        if (time() - (int) $data['at'] > GoogleAuthController::SIGNUP_TTL) {
            session()->remove(GoogleAuthController::SIGNUP_KEY);

            return null;
        }

        return ['sub' => (string) $data['sub'], 'email' => (string) $data['email'], 'name' => (string) ($data['name'] ?? '')];
    }
}
