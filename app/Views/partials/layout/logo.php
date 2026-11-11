<?php
/**
 * Geçici metin logo. Logo dosyaları geldiğinde yalnızca bu dosya değişir.
 *
 * @var string $homeUrl
 * @var string $extraClass navbar-brand-box'a eklenecek sınıf (ör. horizontal-logo)
 */
$extraClass ??= '';
?>
<div class="navbar-brand-box <?= esc($extraClass, 'attr') ?>">
    <?php foreach (['dark', 'light'] as $variant): ?>
        <?php $iconClass = $variant === 'light' ? 'text-white' : 'text-primary' ?>
        <a href="<?= esc($homeUrl, 'attr') ?>" class="logo logo-<?= $variant ?>">
            <span class="logo-sm">
                <i class="ri-restaurant-2-line fs-22 align-middle <?= $iconClass ?>"></i>
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2 fs-20 fw-semibold <?= $variant === 'light' ? 'text-white' : 'text-body' ?>">
                    <i class="ri-restaurant-2-line <?= $iconClass ?>"></i>PixMenu
                </span>
            </span>
        </a>
    <?php endforeach ?>
    <?php if ($extraClass === ''): ?>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover" aria-label="Menüyü sabitle">
            <i class="ri-record-circle-line"></i>
        </button>
    <?php endif ?>
</div>
