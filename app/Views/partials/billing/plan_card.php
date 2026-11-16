<?php
/**
 * Paket kartı (Velzon pages-pricing `.pricing-box`) — panel paketler, landing fiyatlar ve
 * kayıt sihirbazının paket adımı ortak kullanır. Sütun sarmalayıcısını çağıran verir.
 *
 * Aylık/yıllık geçişi `js/pix-plan-cycle.js` yapar (.price-monthly / .price-yearly).
 *
 * @var array<string, mixed> $plan        PlanService::listing()/publicListing() satırı
 * @var bool                 $startYearly ilk görünümde yıllık fiyat mı
 * @var bool                 $isCurrent   "Paketiniz" rozeti + çerçeve
 * @var string               $footer      kartın altına basılan hazır HTML (düğme/form/radyo); boş olabilir
 */
$isTrial  = $plan['trial_days'] !== null;
$featured = (int) $plan['is_featured'] === 1;
$monthly  = (float) $plan['price_monthly'];
$yearly   = (float) $plan['price_yearly'];
$saving   = $monthly > 0 && $yearly > 0 && $yearly < $monthly * 12 ? (int) round((1 - $yearly / ($monthly * 12)) * 100) : 0;
$feature  = static function (bool $on, string $html): string {
    return '<li><div class="d-flex"><div class="flex-shrink-0 text-' . ($on ? 'success' : 'danger') . ' me-1">'
        . '<i class="ri-' . ($on ? 'checkbox' : 'close') . '-circle-fill fs-15 align-middle"></i></div>'
        . '<div class="flex-grow-1">' . $html . '</div></div></li>';
};
?>
<div class="card pricing-box h-100<?= $featured ? ' ribbon-box right' : '' ?><?= $isCurrent ? ' border border-primary' : '' ?>" data-plan-card="<?= esc((string) $plan['code'], 'attr') ?>">
    <div class="card-body bg-light m-2 p-4 d-flex flex-column">
        <?php if ($featured): ?>
            <div class="ribbon-two ribbon-two-danger"><span>Popüler</span></div>
        <?php endif ?>
        <div class="d-flex align-items-center gap-2 mb-2">
            <h5 class="mb-0 fw-semibold"><?= esc((string) $plan['name']) ?></h5>
            <?php if ($isCurrent): ?><span class="badge bg-primary">Paketiniz</span><?php endif ?>
        </div>
        <div class="mb-3">
            <?php if ($isTrial): ?>
                <h2 class="mb-0">Ücretsiz <small class="fs-13 text-muted">/ <?= esc((string) $plan['trial_days']) ?> gün</small></h2>
                <div class="mt-1"><small class="text-muted">Kredi kartı gerekmez</small></div>
            <?php else: ?>
                <h2 class="price-monthly mb-0 text-nowrap<?= $startYearly ? ' d-none' : '' ?>"><?= esc(pix_money($plan['price_monthly'])) ?> <small class="fs-13 text-muted"><?= esc((string) $plan['currency_code']) ?> / ay</small></h2>
                <h2 class="price-yearly mb-0 text-nowrap<?= $startYearly ? '' : ' d-none' ?>"><?= esc(pix_money($plan['price_yearly'])) ?> <small class="fs-13 text-muted"><?= esc((string) $plan['currency_code']) ?> / yıl</small></h2>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    <small class="text-muted">+KDV</small>
                    <?php if ($saving > 0): ?>
                        <small class="price-yearly text-muted<?= $startYearly ? '' : ' d-none' ?>"><del><?= esc(pix_money($monthly * 12)) ?></del></small>
                        <span class="price-yearly badge bg-success-subtle text-success<?= $startYearly ? '' : ' d-none' ?>">%<?= esc((string) $saving) ?> tasarruf</span>
                    <?php endif ?>
                </div>
            <?php endif ?>
        </div>
        <?php if (($plan['description'] ?? null) !== null && $plan['description'] !== ''): ?>
            <p class="text-muted"><?= esc((string) $plan['description']) ?></p>
        <?php endif ?>
        <ul class="list-unstyled vstack gap-3 mb-0">
            <?= $feature(true, $plan['max_branches'] === null ? '<b>Sınırsız</b> şube' : '<b>' . esc((string) $plan['max_branches']) . '</b> şube') ?>
            <?= $feature(true, 'Çok dilli dijital menü ve QR kod') ?>
            <?= $feature(true, 'Alerjen bilgisi') ?>
            <?= $feature((int) $plan['allow_custom_domain'] === 1, 'Özel alan adı') ?>
            <?= $feature((int) $plan['allow_custom_design'] === 1, 'Özel menü tasarımı') ?>
            <?php if ($isTrial): ?>
                <?= $feature(true, '<b>' . esc((string) $plan['trial_days']) . ' gün</b> ücretsiz deneme') ?>
            <?php endif ?>
        </ul>
        <?php if ($footer !== ''): ?>
            <div class="mt-auto pt-4"><?= $footer ?></div>
        <?php endif ?>
    </div>
</div>
