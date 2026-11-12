<?= $this->extend('layouts/admin') ?>
<?php
$rid      = (int) $restaurant['id'];
$base     = 'admin/restaurants/' . $rid . '/domain';
$errors   = session('errors') ?? [];
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$hasOld   = old('domain', null, false) !== null;
$isActive = $hasOld ? old('is_active', null, false) === '1' : (int) ($domain['is_active'] ?? 1) === 1;
?>
<?php $this->setVar('title', 'Özel alan adı') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')],
    ['label' => $restaurant['name'], 'url' => site_url('admin/restaurants/' . $rid)],
    ['label' => 'Özel alan adı'],
]) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-7">
        <?php if (! $allowed): ?>
            <div class="alert alert-warning">
                <i class="ri-error-warning-line align-bottom me-1"></i>
                Restoranın paketi özel alan adını kapsamıyor.
                <a href="<?= site_url('admin/restaurants/' . $rid . '/subscription') ?>" class="alert-link">Paketi değiştir</a>
            </div>
        <?php endif ?>

        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h5 class="card-title mb-0 flex-grow-1"><i class="ri-global-line text-primary align-bottom me-1"></i> Alan adı</h5>
                <?php if ($domain !== null): ?>
                    <?php if ($domain['verified_at'] !== null): ?>
                        <span class="badge bg-success-subtle text-success"><i class="ri-shield-check-line align-bottom"></i> Doğrulandı <?= esc(pix_date($domain['verified_at'])) ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning">Doğrulanmadı</span>
                    <?php endif ?>
                    <?php if ((int) $domain['is_active'] !== 1): ?>
                        <span class="badge bg-danger-subtle text-danger">Pasif</span>
                    <?php endif ?>
                <?php endif ?>
            </div>
            <?= form_open($base) ?>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="domain">Alan adı <span class="text-danger">*</span></label>
                    <div class="input-group has-validation">
                        <span class="input-group-text">https://</span>
                        <input type="text" class="form-control<?= $invalid('domain') ?>" id="domain" name="domain" maxlength="190" required placeholder="menu.ornekrestoran.com"
                               value="<?= esc((string) old('domain', $domain['domain'] ?? '', false), 'attr') ?>"<?= $allowed ? '' : ' disabled' ?>>
                        <?php if (isset($errors['domain'])): ?><div class="invalid-feedback"><?= esc($errors['domain']) ?></div><?php endif ?>
                    </div>
                    <div class="form-text">Şemasız ve "www." olmadan saklanır; "www." ile gelen istekler de aynı restorana gider. Restoran başına bir alan adı.</div>
                </div>
                <div class="form-check form-switch form-switch-success mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?><?= $allowed ? '' : ' disabled' ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-success"<?= $allowed ? '' : ' disabled' ?>><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
                <?php if ($menuUrl !== null): ?>
                    <a href="<?= esc($menuUrl, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-light ms-auto"><i class="ri-external-link-line align-bottom me-1"></i> Aç</a>
                <?php endif ?>
            </div>
            <?= form_close() ?>
        </div>

        <?php if ($domain !== null): ?>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <?= form_open($base . '/verify', ['class' => 'd-inline']) ?>
                    <input type="hidden" name="verified" value="<?= $domain['verified_at'] === null ? '1' : '0' ?>">
                    <?php if ($domain['verified_at'] === null): ?>
                        <button type="submit" class="btn btn-soft-success"><i class="ri-shield-check-line align-bottom me-1"></i> Doğrulandı işaretle</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-soft-warning"><i class="ri-shield-line align-bottom me-1"></i> Doğrulamayı kaldır</button>
                    <?php endif ?>
                <?= form_close() ?>
                <?= form_open($base . '/delete', [
                    'class'               => 'd-inline',
                    'data-confirm'        => $domain['domain'] . ' alan adı kaldırılacak; bu adresteki menü çalışmaz.',
                    'data-confirm-button' => 'Evet, kaldır',
                ]) ?>
                    <button type="submit" class="btn btn-soft-danger"><i class="ri-delete-bin-line align-bottom me-1"></i> Alan adını kaldır</button>
                <?= form_close() ?>
            </div>
        <?php endif ?>
    </div>

    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="ri-information-line text-info align-bottom me-1"></i> Kurulum adımları</h5></div>
            <div class="card-body">
                <ol class="vstack gap-2 mb-0 ps-3">
                    <li>Müşteri, alan adının DNS'inde <strong>CNAME</strong> kaydını <code><?= esc($appHost) ?></code> adresine (kök alan adıysa <strong>A</strong> kaydını sunucu IP'sine) yönlendirir.</li>
                    <li>Sunucuda varsayılan (catch-all) vhost uygulamaya bakar; SSL sertifikası otomatik alınır.</li>
                    <li>Adres açıldığında burada "Doğrulandı" olarak işaretleyin.</li>
                </ol>
                <hr>
                <p class="text-muted small mb-0">
                    Tek aktif şubede menü doğrudan kök adreste açılır; birden fazla şubede kökte şube seçimi gösterilir ve menüler
                    <code>alanadi.com/{şube-kısa-adı}</code> adresindedir. <code>/m/{kod}</code> adresleri (basılı QR'lar) çalışmaya devam eder.
                </p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
