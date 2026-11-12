<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'Gösterge Paneli') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Gösterge Paneli']]) ?>
<?= $this->section('content') ?>
<?php
$cards = [
    'restaurants' => ['label' => 'Restoranlar', 'icon' => 'ri-store-2-line', 'color' => 'primary', 'url' => site_url('admin/restaurants')],
    'branches'    => ['label' => 'Şubeler', 'icon' => 'ri-map-pin-2-line', 'color' => 'success', 'url' => null],
    'users'       => ['label' => 'Kullanıcılar', 'icon' => 'ri-team-line', 'color' => 'info', 'url' => null],
    'languages'   => ['label' => 'Diller', 'icon' => 'ri-translate-2', 'color' => 'warning', 'url' => site_url('admin/languages')],
    'allergens'   => ['label' => 'Alerjenler', 'icon' => 'ri-error-warning-line', 'color' => 'danger', 'url' => site_url('admin/allergens')],
];
?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-5">
    <?php foreach ($cards as $key => $card): ?>
        <?php $count = (int) ($stats[$key] ?? 0) ?>
        <div class="col">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="fw-medium text-muted mb-0"><?= esc($card['label']) ?></p>
                            <h2 class="mt-4 ff-secondary fw-semibold">
                                <span class="counter-value" data-target="<?= esc((string) $count, 'attr') ?>"><?= esc((string) $count) ?></span>
                            </h2>
                            <?php if ($card['url'] !== null): ?>
                                <a href="<?= esc($card['url'], 'attr') ?>" class="text-decoration-underline">Tümünü gör</a>
                            <?php else: ?>
                                <p class="mb-0 text-muted">Toplam kayıt</p>
                            <?php endif ?>
                        </div>
                        <div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-<?= esc($card['color'], 'attr') ?>-subtle rounded-circle fs-2">
                                    <i class="<?= esc($card['icon'], 'attr') ?> text-<?= esc($card['color'], 'attr') ?>"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
