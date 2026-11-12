<?= $this->extend('layouts/panel') ?>
<?php $this->setVar('title', 'Paketler') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Abonelik', 'url' => site_url('panel/subscription')],
    ['label' => 'Paketler'],
]) ?>
<?php
$startYearly = $currentCycle === 'yearly';
$colClass    = count($plans) >= 4 ? 'col-xxl-3 col-lg-6' : 'col-xl-4 col-lg-6';
$feature     = static function (bool $on, string $html): string {
    return '<li><div class="d-flex"><div class="flex-shrink-0 text-' . ($on ? 'success' : 'danger') . ' me-1">'
        . '<i class="ri-' . ($on ? 'checkbox' : 'close') . '-circle-fill fs-15 align-middle"></i></div>'
        . '<div class="flex-grow-1">' . $html . '</div></div></li>';
};
?>
<?= $this->section('content') ?>
<div class="row justify-content-center mt-2">
    <div class="col-lg-6">
        <div class="text-center mb-4">
            <h4 class="fw-semibold fs-22">Paketler ve fiyatlar</h4>
            <p class="text-muted mb-4 fs-15">Fiyatlara KDV dahil değildir (%<?= esc(pix_money($taxRate)) ?>). Paket değişikliği için bizimle iletişime geçin.</p>
            <div class="d-inline-flex">
                <ul class="nav nav-pills arrow-navtabs plan-nav rounded mb-3 p-1" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold<?= $startYearly ? '' : ' active' ?>" type="button" data-cycle="monthly" aria-selected="<?= $startYearly ? 'false' : 'true' ?>">Aylık</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold<?= $startYearly ? ' active' : '' ?>" type="button" data-cycle="yearly" aria-selected="<?= $startYearly ? 'true' : 'false' ?>">Yıllık</button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($plans === []): ?>
    <div class="card"><div class="card-body text-center text-muted py-5">Şu an listelenecek paket yok.</div></div>
<?php else: ?>
<div class="row justify-content-center">
    <?php foreach ($plans as $p): ?>
        <?php
        $isCurrent = $currentPlanId === (int) $p['id'];
        $isTrial   = $p['trial_days'] !== null;
        $featured  = (int) $p['is_featured'] === 1;
        $monthly   = (float) $p['price_monthly'];
        $yearly    = (float) $p['price_yearly'];
        $saving    = $monthly > 0 && $yearly > 0 && $yearly < $monthly * 12 ? (int) round((1 - $yearly / ($monthly * 12)) * 100) : 0;
        ?>
        <div class="<?= $colClass ?>">
            <div class="card pricing-box<?= $featured ? ' ribbon-box right' : '' ?><?= $isCurrent ? ' border border-primary' : '' ?>">
                <div class="card-body bg-light m-2 p-4">
                    <?php if ($featured): ?>
                        <div class="ribbon-two ribbon-two-danger"><span>Popüler</span></div>
                    <?php endif ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-grow-1">
                            <h5 class="mb-0 fw-semibold"><?= esc($p['name']) ?></h5>
                            <?php if ($isCurrent): ?><span class="badge bg-primary mt-1">Paketiniz</span><?php endif ?>
                        </div>
                        <div class="ms-auto text-end">
                            <?php if ($isTrial): ?>
                                <h2 class="mb-0">Ücretsiz <small class="fs-13 text-muted">/ <?= esc((string) $p['trial_days']) ?> gün</small></h2>
                            <?php else: ?>
                                <h2 class="price-monthly mb-0<?= $startYearly ? ' d-none' : '' ?>"><?= esc(pix_money($p['price_monthly'])) ?> <small class="fs-13 text-muted"><?= esc($p['currency_code']) ?> / ay</small></h2>
                                <h2 class="price-yearly mb-0<?= $startYearly ? '' : ' d-none' ?>">
                                    <?php if ($saving > 0): ?><small class="fs-16"><del><?= esc(pix_money($monthly * 12)) ?></del></small><?php endif ?>
                                    <?= esc(pix_money($p['price_yearly'])) ?> <small class="fs-13 text-muted"><?= esc($p['currency_code']) ?> / yıl</small>
                                </h2>
                                <?php if ($saving > 0): ?>
                                    <span class="price-yearly badge bg-success-subtle text-success<?= $startYearly ? '' : ' d-none' ?>">%<?= esc((string) $saving) ?> tasarruf</span>
                                <?php endif ?>
                            <?php endif ?>
                        </div>
                    </div>
                    <?php if ($p['description'] !== null && $p['description'] !== ''): ?>
                        <p class="text-muted"><?= esc($p['description']) ?></p>
                    <?php endif ?>
                    <ul class="list-unstyled vstack gap-3">
                        <?= $feature(true, $p['max_branches'] === null ? '<b>Sınırsız</b> şube' : '<b>' . esc((string) $p['max_branches']) . '</b> şube') ?>
                        <?= $feature(true, 'Çok dilli dijital menü ve QR kod') ?>
                        <?= $feature(true, 'Alerjen bilgisi') ?>
                        <?= $feature((int) $p['allow_custom_domain'] === 1, 'Özel alan adı') ?>
                        <?= $feature((int) $p['allow_custom_design'] === 1, 'Özel menü tasarımı') ?>
                        <?php if ($isTrial): ?>
                            <?= $feature(true, '<b>' . esc((string) $p['trial_days']) . ' gün</b> ücretsiz deneme') ?>
                        <?php endif ?>
                    </ul>
                    <div class="mt-3 pt-2">
                        <?php if ($isCurrent): ?>
                            <button type="button" class="btn btn-primary disabled w-100">Mevcut paketiniz</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-soft-secondary disabled w-100">Geçiş için bizimle iletişime geçin</button>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>
<p class="text-center text-muted">
    <i class="ri-customer-service-2-line align-bottom me-1"></i>
    Paketinizi yükseltmek veya değiştirmek için bizimle iletişime geçin; geçiş aynı gün yapılır ve yeni dönem bugünden başlar.
</p>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function () {
    var buttons = document.querySelectorAll('.plan-nav [data-cycle]');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var yearly = btn.dataset.cycle === 'yearly';
            buttons.forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            document.querySelectorAll('.price-monthly').forEach(function (el) { el.classList.toggle('d-none', yearly); });
            document.querySelectorAll('.price-yearly').forEach(function (el) { el.classList.toggle('d-none', !yearly); });
        });
    });
})();
</script>
<?= $this->endSection() ?>
