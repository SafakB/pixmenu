<?php
/**
 * Ortak <head> içeriği.
 *
 * @var string $pageTitle
 * @var bool   $withCsrfMeta
 */
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
<?php if (! empty($withCsrfMeta)): ?>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
<?php endif ?>
    <title><?= esc($pageTitle) ?></title>
    <link rel="shortcut icon" href="<?= asset_url('images/favicon.ico') ?>">
    <script>window.PIX_ASSET_BASE = <?= json_encode(base_url('assets') . '/', JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= asset_url('js/layout.js') ?>"></script>
    <link href="<?= asset_url('css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset_url('css/icons.min.css') ?>" rel="stylesheet">
    <link href="<?= asset_url('css/app.min.css') ?>" rel="stylesheet">
    <link href="<?= asset_url('css/custom.min.css') ?>" rel="stylesheet">
