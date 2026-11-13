<?= $this->extend('layouts/admin') ?>
<?php
$heading   = $template === null ? 'Yeni şablon' : 'Şablonu düzenle';
$indexUrl  = site_url('admin/templates');
$errors    = session('errors') ?? [];
$hasOld    = old('name', null, false) !== null;
$isActive  = $hasOld ? old('is_active', null, false) === '1' : (int) ($template['is_active'] ?? 1) === 1;
$isDefault = $template !== null && ! empty($template['is_default']);
$invalid   = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback  = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Menü şablonları', 'url' => $indexUrl],
    ['label' => $heading],
]) ?>
<?= $this->section('content') ?>
<?= form_open($action) ?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Genel bilgiler</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="code">Kod <span class="text-danger">*</span></label>
                        <?php if ($template === null): ?>
                            <input type="text" class="form-control font-monospace<?= $invalid('code') ?>" id="code" name="code" maxlength="30" required placeholder="ör. modern, klasik-koyu"
                                   value="<?= esc((string) old('code', '', false), 'attr') ?>">
                            <?= $feedback('code') ?>
                            <div class="form-text">Küçük harf, rakam ve tire. Oluşturulduktan sonra değiştirilemez.</div>
                        <?php else: ?>
                            <input type="text" class="form-control font-monospace" id="code" value="<?= esc((string) $template['code'], 'attr') ?>" disabled>
                            <div class="form-text">Kod değiştirilemez.</div>
                        <?php endif ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="name">Ad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="60" required
                               value="<?= esc((string) old('name', $template['name'] ?? '', false), 'attr') ?>">
                        <?= $feedback('name') ?>
                    </div>
                    <div class="col-12 mb-0">
                        <label class="form-label" for="description">Açıklama</label>
                        <textarea class="form-control<?= $invalid('description') ?>" id="description" name="description" rows="3" maxlength="255"><?= esc((string) old('description', $template['description'] ?? '', false)) ?></textarea>
                        <?= $feedback('description') ?>
                        <div class="form-text">Yalnızca yönetim panelinde görünür.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info border-0 d-flex gap-2" role="alert">
            <i class="ri-information-line fs-16 flex-shrink-0"></i>
            <div>
                Tasarımlar ön uç uygulamasındadır. Kod, ön ucun bu tasarımı tanıdığı anahtardır; ön uçta karşılığı olan bir kod girin.
                Menü API'si şubenin şablon kodunu <code>branch.template</code> alanında döner.
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Yayın</h5></div>
            <div class="card-body">
                <?php if ($isDefault): ?>
                    <input type="hidden" name="is_active" value="1">
                <?php endif ?>
                <div class="form-check form-switch form-switch-success mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?><?= $isDefault ? ' disabled' : '' ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
                <p class="text-muted small mb-0">
                    <?= $isDefault ? 'Standart şablon her zaman aktiftir.' : 'Pasif şablon şube formunda seçilemez.' ?>
                </p>

                <hr class="my-4">

                <label class="form-label" for="sort_order">Sıra</label>
                <input type="number" class="form-control<?= $invalid('sort_order') ?>" id="sort_order" name="sort_order" min="0" max="65535"
                       value="<?= esc((string) old('sort_order', $template['sort_order'] ?? 0, false), 'attr') ?>">
                <?= $feedback('sort_order') ?>
                <div class="form-text">Küçük sayı listede önce gelir.</div>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <a href="<?= esc($indexUrl, 'attr') ?>" class="btn btn-light w-100">Vazgeç</a>
                    <button type="submit" class="btn btn-success w-100"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?= form_close() ?>
<?= $this->endSection() ?>
