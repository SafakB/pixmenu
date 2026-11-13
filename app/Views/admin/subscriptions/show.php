<?= $this->extend('layouts/admin') ?>
<?php
$rid       = (int) $restaurant['id'];
$base      = 'admin/restaurants/' . $rid . '/subscription';
$sub       = $summary['subscription'];
$suspended = $restaurant['suspended_at'] !== null;
$reason    = $restaurant['suspension_reason'];
$errors    = session('errors') ?? [];
$invalid   = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback  = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
$oldPlan   = (string) old('plan_id', '', false);
$oldCycle  = (string) old('billing_cycle', $sub !== null && $sub['billing_cycle'] !== 'trial' ? $sub['billing_cycle'] : 'monthly', false);
$openOld   = array_filter($invoices, static fn (array $i): bool => $i['status'] === 'pending');

$canControl   = admin_can('subscriptions.control'); // iptal / askıya al / askıdan çıkar
$canManageSub = admin_can('subscriptions.manage');  // paket değiştir / eşik / restoran indirimi
$canDomain    = admin_can('restaurants.manage');

// Kart başlığı aksiyonları
ob_start();
?>
<?php if ($canControl): ?>
<?php if ($sub !== null && $sub['billing_cycle'] !== 'trial'): ?>
    <?php $cancelOn = (int) $sub['cancel_at_period_end'] === 1 ?>
    <?= form_open($base . '/cancel', ['class' => 'd-inline'] + ($cancelOn ? [] : [
        'data-confirm'         => 'Abonelik dönem sonunda (' . pix_date($sub['current_period_end']) . ') yenilenmeyecek ve restoran askıya alınacak.',
        'data-confirm-button'  => 'Evet, dönem sonunda iptal et',
        'data-confirm-variant' => 'warning',
    ])) ?>
        <input type="hidden" name="cancel" value="<?= $cancelOn ? '0' : '1' ?>">
        <button type="submit" class="btn btn-sm btn-soft-<?= $cancelOn ? 'success' : 'warning' ?>">
            <i class="ri-calendar-close-line align-bottom me-1"></i><?= $cancelOn ? 'İptali geri al' : 'Dönem sonunda iptal et' ?>
        </button>
    <?= form_close() ?>
<?php endif ?>
<?php if ($reason === 'manual'): ?>
    <?= form_open($base . '/resume', ['class' => 'd-inline']) ?>
        <button type="submit" class="btn btn-sm btn-soft-success"><i class="ri-play-circle-line align-bottom me-1"></i>Askıyı kaldır</button>
    <?= form_close() ?>
<?php else: ?>
    <?= form_open($base . '/suspend', [
        'class'                => 'd-inline',
        'data-confirm'         => $restaurant['name'] . ' elle askıya alınacak. Menüleri yayından kalkar, panelde yalnızca abonelik sayfaları açık kalır.',
        'data-confirm-button'  => 'Evet, askıya al',
        'data-confirm-variant' => 'warning',
    ]) ?>
        <button type="submit" class="btn btn-sm btn-soft-danger"><i class="ri-pause-circle-line align-bottom me-1"></i>Elle askıya al</button>
    <?= form_close() ?>
<?php endif ?>
<?php endif ?>
<?php $actions = (string) ob_get_clean(); ?>
<?php $this->setVar('title', 'Abonelik') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Restoranlar', 'url' => site_url('admin/restaurants')],
    ['label' => $restaurant['name'], 'url' => site_url('admin/restaurants/' . $rid)],
    ['label' => 'Abonelik'],
]) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-8">
        <?= view('partials/billing/subscription_card', [
            'summary'           => $summary,
            'suspended'         => $suspended,
            'suspension_reason' => $reason,
            'actions'           => $actions,
        ]) ?>

        <?php /* ---------- Faturalar ---------- */ ?>
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h5 class="card-title mb-0 flex-grow-1"><i class="ri-file-list-3-line text-info align-bottom me-1"></i> Faturalar <span class="badge bg-info-subtle text-info align-middle ms-1"><?= count($invoices) ?></span></h5>
                <a href="<?= site_url('admin/invoices?restaurant=' . $rid) ?>" class="btn btn-soft-primary btn-sm">Fatura listesinde aç <i class="ri-arrow-right-line align-bottom"></i></a>
            </div>
            <div class="card-body">
                <?php if ($invoices === []): ?>
                    <p class="text-muted text-center py-4 mb-0">Henüz fatura yok.</p>
                <?php else: ?>
                    <div class="table-responsive table-card">
                        <table class="table align-middle table-nowrap mb-0">
                            <thead class="table-light"><tr><th>No</th><th>Dönem</th><th>Vade</th><th>Tutar</th><th>Durum</th><th class="text-end"></th></tr></thead>
                            <tbody>
                            <?php foreach ($invoices as $i): ?>
                                <tr>
                                    <td><a href="<?= site_url('admin/invoices/' . $i['id']) ?>" class="fw-medium"><?= esc((string) $i['number']) ?></a></td>
                                    <td class="text-muted"><?= esc(pix_date($i['period_start'])) ?> – <?= esc(pix_date($i['period_end'])) ?></td>
                                    <td><?= esc(pix_date($i['due_date'])) ?></td>
                                    <td><?= esc(pix_money($i['total'], $i['currency_code'])) ?></td>
                                    <td><?= view('partials/billing/badge', ['kind' => 'invoice', 'status' => $i['display_status']]) ?></td>
                                    <td class="text-end">
                                        <a href="<?= site_url('admin/invoices/' . $i['id']) ?>" class="btn btn-sm btn-icon btn-soft-info"
                                           data-bs-toggle="tooltip" title="Detay" aria-label="Detay"><i class="ri-eye-line"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <?php /* ---------- Geçmiş ---------- */ ?>
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="ri-history-line text-secondary align-bottom me-1"></i> Abonelik geçmişi</h5></div>
            <div class="card-body">
                <?php if ($history === []): ?>
                    <p class="text-muted text-center py-4 mb-0">Kayıt yok.</p>
                <?php else: ?>
                    <div class="table-responsive table-card">
                        <table class="table align-middle table-nowrap mb-0">
                            <thead class="table-light"><tr><th>Paket</th><th>Dönem tipi</th><th>Fiyat</th><th>Başlangıç</th><th>Son dönem</th><th>Durum</th></tr></thead>
                            <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td class="fw-medium"><?= esc($h['plan_name']) ?></td>
                                    <td><?= esc($h['cycle_label']) ?></td>
                                    <td><?= $h['billing_cycle'] === 'trial' ? '—' : esc(pix_money($h['price'], $h['currency_code'])) ?></td>
                                    <td><?= esc(pix_date($h['started_at'])) ?></td>
                                    <td class="text-muted"><?= esc(pix_date($h['current_period_start'])) ?> – <?= esc(pix_date($h['current_period_end'])) ?></td>
                                    <td>
                                        <?= view('partials/billing/badge', ['kind' => 'subscription', 'status' => $h['status']]) ?>
                                        <?php if ($h['ended_at'] !== null): ?><small class="text-muted ms-1"><?= esc(pix_date($h['ended_at'])) ?></small><?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <?php
        $discPct     = $restaurant['discount_percent'] !== null && (float) $restaurant['discount_percent'] > 0
            ? rtrim(rtrim(number_format((float) $restaurant['discount_percent'], 2, ',', ''), '0'), ',')
            : null;
        $discUntil   = $discPct !== null ? $restaurant['discount_until'] : null;
        $discExpired = $discUntil !== null && $discUntil < date('Y-m-d');
        ?>
        <?php if (! $canManageSub): ?>
            <?php /* ---------- Salt okunur ayarlar ---------- */ ?>
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><i class="ri-settings-3-line text-secondary align-bottom me-1"></i> Abonelik ayarları</h5></div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex px-0"><span class="flex-grow-1 text-muted">Gecikme eşiği</span><span><?= esc((string) $summary['grace_days']) ?> gün</span></li>
                        <li class="list-group-item d-flex px-0">
                            <span class="flex-grow-1 text-muted">Restoran indirimi</span>
                            <span>
                                <?php if ($discPct === null): ?>
                                    yok
                                <?php else: ?>
                                    %<?= esc($discPct) ?> — <?= $discUntil === null ? 'süresiz' : esc(pix_date($discUntil)) ?>
                                    <?php if ($discExpired): ?><span class="badge bg-warning-subtle text-warning ms-1">Süresi doldu</span><?php endif ?>
                                <?php endif ?>
                            </span>
                        </li>
                    </ul>
                    <p class="text-muted small mb-0 mt-2"><i class="ri-lock-2-line align-bottom me-1"></i>Abonelik değişiklikleri için yetkiniz yok.</p>
                </div>
            </div>
        <?php else: ?>
        <?php /* ---------- Paket değiştir ---------- */ ?>
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="ri-exchange-line text-success align-bottom me-1"></i> <?= $sub === null ? 'Paket ata' : 'Paket değiştir' ?></h5></div>
            <?= form_open($base . '/change', [
                'data-confirm'         => 'Yeni abonelik bugün başlar; ücretli pakette tam dönem faturası hemen kesilir. Güncel abonelik sonlandırılır.',
                'data-confirm-title'   => 'Paket değiştirilsin mi?',
                'data-confirm-button'  => 'Evet, değiştir',
                'data-confirm-variant' => 'primary',
            ]) ?>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="plan_id">Paket <span class="text-danger">*</span></label>
                    <select class="form-select<?= $invalid('plan_id') ?>" id="plan_id" name="plan_id" required>
                        <option value="">Seçin…</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= esc((string) $p['id'], 'attr') ?>" data-trial="<?= $p['trial_days'] !== null ? '1' : '0' ?>"<?= $oldPlan === (string) $p['id'] ? ' selected' : '' ?>>
                                <?= esc($p['name']) ?><?= $p['trial_days'] !== null
                                    ? ' — ' . esc((string) $p['trial_days']) . ' gün deneme'
                                    : ' — ' . esc(pix_money($p['price_monthly'], $p['currency_code'])) . ' / ay, ' . esc(pix_money($p['price_yearly'], $p['currency_code'])) . ' / yıl' ?>
                                <?= $sub !== null && (int) $sub['plan_id'] === (int) $p['id'] ? ' (mevcut)' : '' ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?= $feedback('plan_id') ?>
                </div>
                <div class="mb-3" id="billing-cycle-group">
                    <label class="form-label d-block">Ödeme dönemi</label>
                    <div class="btn-group w-100" role="group" aria-label="Ödeme dönemi">
                        <input type="radio" class="btn-check" name="billing_cycle" id="cycle-monthly" value="monthly"<?= $oldCycle !== 'yearly' ? ' checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="cycle-monthly">Aylık</label>
                        <input type="radio" class="btn-check" name="billing_cycle" id="cycle-yearly" value="yearly"<?= $oldCycle === 'yearly' ? ' checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="cycle-yearly">Yıllık</label>
                    </div>
                    <?php if (isset($errors['billing_cycle'])): ?><div class="text-danger small mt-1"><?= esc($errors['billing_cycle']) ?></div><?php endif ?>
                </div>
                <div class="mb-3" id="discount-group">
                    <label class="form-label" for="discount">İndirim tutarı</label>
                    <input type="text" inputmode="decimal" class="form-control<?= $invalid('discount') ?>" id="discount" name="discount" placeholder="0,00"
                           value="<?= esc((string) old('discount', '', false), 'attr') ?>">
                    <?= $feedback('discount') ?>
                    <div class="form-text">İlk faturaya ek TL indirim (KDV hariç). Restoran yüzdelik indirimi ayrıca uygulanır.</div>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="notes">Not</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000"><?= esc((string) old('notes', '', false)) ?></textarea>
                </div>
                <?php if ($openOld !== []): ?>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="ri-error-warning-line align-bottom me-1"></i>
                        <?= count($openOld) ?> açık fatura var. Paket değişiminde otomatik iptal edilmez; gerekiyorsa fatura detayından iptal edin.
                    </div>
                <?php endif ?>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success w-100"><i class="ri-exchange-line align-bottom me-1"></i> <?= $sub === null ? 'Paketi ata' : 'Paketi değiştir' ?></button>
            </div>
            <?= form_close() ?>
        </div>

        <?php /* ---------- Gecikme eşiği ---------- */ ?>
        <?php if ($sub !== null): ?>
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><i class="ri-timer-line text-warning align-bottom me-1"></i> Gecikme eşiği</h5></div>
                <?= form_open($base . '/grace') ?>
                <div class="card-body">
                    <p class="text-muted small">Vadesi geçen faturada restoran kaç gün sonra askıya alınsın? Boş = varsayılan (<?= esc((string) $defaultGraceDays) ?> gün).</p>
                    <div class="input-group">
                        <input type="number" min="0" max="365" class="form-control<?= $invalid('grace_days') ?>" id="grace_days" name="grace_days" placeholder="<?= esc((string) $defaultGraceDays, 'attr') ?>" aria-label="Gecikme eşiği (gün)"
                               value="<?= esc((string) old('grace_days', $sub['grace_days'] ?? '', false), 'attr') ?>">
                        <span class="input-group-text">gün</span>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <?= $feedback('grace_days') ?>
                    </div>
                    <p class="text-muted small mb-0 mt-2">Geçerli eşik: <strong><?= esc((string) $summary['grace_days']) ?> gün</strong></p>
                </div>
                <?= form_close() ?>
            </div>
        <?php endif ?>

        <?php /* ---------- Restoran indirimi ---------- */ ?>
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="ri-percent-line text-primary align-bottom me-1"></i> Restoran indirimi</h5></div>
            <?= form_open($base . '/discount') ?>
            <div class="card-body">
                <p class="text-muted small">Bu restoran için kesilen tüm abonelik faturalarına (yenilemeler ve paket değişimleri dahil) uygulanır. Kaldırmak için oranı boş bırakın.</p>
                <div class="mb-3">
                    <label class="form-label" for="discount_percent">İndirim oranı</label>
                    <div class="input-group">
                        <span class="input-group-text">%</span>
                        <input type="text" inputmode="decimal" class="form-control<?= $invalid('discount_percent') ?>" id="discount_percent" name="discount_percent" placeholder="ör. 20"
                               value="<?= esc((string) old('discount_percent', $discPct ?? '', false), 'attr') ?>">
                        <?= $feedback('discount_percent') ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="discount_until">Bitiş tarihi</label>
                    <input type="date" class="form-control<?= $invalid('discount_until') ?>" id="discount_until" name="discount_until"
                           value="<?= esc((string) old('discount_until', $discUntil ?? '', false), 'attr') ?>">
                    <?= $feedback('discount_until') ?>
                    <div class="form-text">Boş = süresiz. Dönem başı bu tarihten sonra olan faturalara uygulanmaz.</div>
                </div>
                <p class="text-muted small mb-0">
                    Geçerli:
                    <?php if ($discPct === null): ?>
                        <strong>yok</strong>
                    <?php else: ?>
                        <strong>%<?= esc($discPct) ?></strong> — <?= $discUntil === null ? 'süresiz' : esc(pix_date($discUntil)) . ' tarihine kadar' ?>
                        <?php if ($discExpired): ?><span class="badge bg-warning-subtle text-warning ms-1">Süresi doldu</span><?php endif ?>
                    <?php endif ?>
                </p>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary w-100"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
            </div>
            <?= form_close() ?>
        </div>
        <?php endif ?>

        <?php if ($canDomain): ?>
        <div class="card">
            <div class="card-body d-flex align-items-center gap-2">
                <i class="ri-global-line fs-20 text-primary"></i>
                <span class="flex-grow-1">Özel alan adı</span>
                <a href="<?= site_url('admin/restaurants/' . $rid . '/domain') ?>" class="btn btn-soft-primary btn-sm">Yönet <i class="ri-arrow-right-line align-bottom"></i></a>
            </div>
        </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function () {
    var select = document.getElementById('plan_id');
    var groups = [document.getElementById('billing-cycle-group'), document.getElementById('discount-group')];
    if (!select) return;
    var sync = function () {
        var opt = select.options[select.selectedIndex];
        var trial = !!opt && opt.dataset.trial === '1';
        groups.forEach(function (g) { if (g) g.classList.toggle('d-none', trial); });
    };
    select.addEventListener('change', sync);
    sync();
})();
</script>
<?= $this->endSection() ?>
