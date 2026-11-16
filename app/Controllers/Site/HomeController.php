<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use App\Models\LanguageModel;
use App\Models\MenuTemplateModel;
use App\Services\PlanService;

/**
 * Kök alan adı: tanıtım (landing) sayfası. Oturum bilgisine bakmaz (herkese aynı içerik).
 */
final class HomeController extends BaseController
{
    /**
     * View: site/home — plans (PlanService::publicListing), taxRate, stats{languages, templates, trialDays|null},
     *       faq[{q, a}], contact{email, phone, address}, signupUrl, loginUrl, seo{title, description, canonical, ogImage},
     *       jsonLd (list<array>)
     */
    public function index(): string
    {
        $site  = config('Site');
        $plans = (new PlanService())->publicListing();

        $trialDays = null;

        foreach ($plans as $p) {
            if ($p['trial_days'] !== null && $p['code'] === config('Billing')->defaultPlanCode) {
                $trialDays = $p['trial_days'];
            }
        }

        $canonical = base_url();
        $ogImage   = base_url('assets/images/landing/og-image.png');

        return view('site/home', [
            'plans'   => $plans,
            'taxRate' => config('Billing')->taxRate,
            'stats'   => [
                'languages' => count((new LanguageModel())->ordered(true)),
                'templates' => (new MenuTemplateModel())->where('is_active', 1)->countAllResults(),
                'trialDays' => $trialDays,
            ],
            'faq'       => $site->faq,
            'contact'   => ['email' => $site->contactEmail, 'phone' => $site->contactPhone, 'address' => $site->contactAddress],
            'signupUrl' => site_url('kayit'),
            'loginUrl'  => site_url('panel/login'),
            'seo'       => [
                'title'       => $site->title,
                'description' => $site->description,
                'canonical'   => $canonical,
                'ogImage'     => $ogImage,
            ],
            'jsonLd' => $this->jsonLd($site, $plans, $canonical, $ogImage),
        ]);
    }

    /**
     * Yapılandırılmış veri: Organization, SoftwareApplication (+ paket teklifleri), FAQPage.
     * Uydurma puan/yorum EKLENMEZ.
     *
     * @param list<array<string, mixed>> $plans
     *
     * @return list<array<string, mixed>>
     */
    private function jsonLd(\Config\Site $site, array $plans, string $url, string $image): array
    {
        $offers = [];

        foreach ($plans as $p) {
            $offers[] = [
                '@type'         => 'Offer',
                'name'          => $p['name'],
                'price'         => $p['trial_days'] !== null ? '0' : number_format((float) $p['price_monthly'], 2, '.', ''),
                'priceCurrency' => $p['currency_code'],
                'url'           => site_url('kayit') . '?plan=' . rawurlencode($p['code']),
            ];
        }

        return [
            [
                '@context' => 'https://schema.org',
                '@type'    => 'Organization',
                'name'     => $site->name,
                'url'      => $url,
                'logo'     => $image,
                'email'    => $site->contactEmail,
            ],
            [
                '@context'            => 'https://schema.org',
                '@type'               => 'SoftwareApplication',
                'name'                => $site->name,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem'     => 'Web',
                'description'         => $site->description,
                'url'                 => $url,
                'image'               => $image,
                'inLanguage'          => 'tr',
                'offers'              => $offers,
            ],
            [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => array_map(static fn (array $f): array => [
                    '@type'          => 'Question',
                    'name'           => $f['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                ], $site->faq),
            ],
        ];
    }
}
