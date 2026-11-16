<?php
/**
 * Flash mesajları: success, info, warning, error, errors (dizi: alan => mesaj).
 */
$flashErrors = session('errors');
?>
<?php if (session('success')): ?>
    <div class="alert alert-success alert-dismissible alert-label-icon label-arrow fade show" role="status">
        <i class="ri-checkbox-circle-line label-icon"></i><?= esc(session('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif ?>
<?php if (session('info')): ?>
    <div class="alert alert-info alert-dismissible alert-label-icon label-arrow fade show" role="status">
        <i class="ri-information-line label-icon"></i><?= esc(session('info')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif ?>
<?php if (session('warning')): ?>
    <div class="alert alert-warning alert-dismissible alert-label-icon label-arrow fade show" role="alert">
        <i class="ri-alert-line label-icon"></i><?= esc(session('warning')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif ?>
<?php if (session('error')): ?>
    <div class="alert alert-danger alert-dismissible alert-label-icon label-arrow fade show" role="alert">
        <i class="ri-error-warning-line label-icon"></i><?= esc(session('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif ?>
<?php if (is_array($flashErrors) && $flashErrors !== []): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($flashErrors as $field => $message): ?>
                <li data-field="<?= esc((string) $field, 'attr') ?>"><?= esc((string) $message) ?></li>
            <?php endforeach ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif ?>
