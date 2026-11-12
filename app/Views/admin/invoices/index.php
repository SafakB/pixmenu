<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('title', 'Faturalar') ?>
<?php $this->setVar('breadcrumbs', [['label' => 'Faturalar']]) ?>
<?php
$tabs = [
    ''        => ['Tümü', []],
    'pending' => ['Ödenmedi', ['status' => 'pending']],
    'overdue' => ['Gecikmiş', ['overdue' => 1]],
    'paid'    => ['Ödendi', ['status' => 'paid']],
    'void'    => ['İptal', ['status' => 'void']],
];
$activeTab = $filters['overdue'] ? 'overdue' : $filters['status'];
$tabUrl    = static function (array $query) use ($filters): string {
    if ($filters['restaurant'] !== null) {
        $query['restaurant'] = $filters['restaurant'];
    }

    return site_url('admin/invoices') . ($query === [] ? '' : '?' . http_build_query($query));
};
?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-4">
        <div class="card card-animate">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <p class="fw-medium text-muted mb-1">Ödenmemiş fatura</p>
                    <h3 class="mb-0 ff-secondary"><?= esc((string) $totals['pending_count']) ?></h3>
                </div>
                <div class="avatar-sm"><span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-3"><i class="ri-file-list-3-line"></i></span></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-animate">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <p class="fw-medium text-muted mb-1">Gecikmiş fatura</p>
                    <h3 class="mb-0 ff-secondary"><?= esc((string) $totals['overdue_count']) ?></h3>
                </div>
                <div class="avatar-sm"><span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-3"><i class="ri-error-warning-line"></i></span></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-animate">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <p class="fw-medium text-muted mb-1">Açık tutar (KDV dahil)</p>
                    <h3 class="mb-0 ff-secondary fs-20">
                        <?php if ($totals['pending_sum'] === []): ?>
                            0,00
                        <?php else: ?>
                            <?php foreach ($totals['pending_sum'] as $currency => $sum): ?>
                                <span class="d-block"><?= esc(pix_money($sum, (string) $currency)) ?></span>
                            <?php endforeach ?>
                        <?php endif ?>
                    </h3>
                </div>
                <div class="avatar-sm"><span class="avatar-title bg-info-subtle text-info rounded-circle fs-3"><i class="ri-money-dollar-circle-line"></i></span></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pb-0">
        <div class="row g-2 align-items-center">
            <div class="col-lg">
                <ul class="nav nav-tabs-custom card-header-tabs border-bottom-0" role="tablist">
                    <?php foreach ($tabs as $key => [$label, $query]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activeTab === $key ? ' active' : '' ?>" href="<?= esc($tabUrl($query), 'attr') ?>"><?= esc($label) ?></a>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
            <div class="col-lg-auto pb-2">
                <form method="get" action="<?= site_url('admin/invoices') ?>" class="d-flex gap-2">
                    <?php if ($filters['overdue']): ?><input type="hidden" name="overdue" value="1"><?php endif ?>
                    <?php if ($filters['status'] !== '' && ! $filters['overdue']): ?><input type="hidden" name="status" value="<?= esc($filters['status'], 'attr') ?>"><?php endif ?>
                    <select name="restaurant" class="form-select form-select-sm" aria-label="Restoran" onchange="this.form.submit()">
                        <option value="">Tüm restoranlar</option>
                        <?php foreach ($restaurants as $r): ?>
                            <option value="<?= esc((string) $r['id'], 'attr') ?>"<?= $filters['restaurant'] === $r['id'] ? ' selected' : '' ?>><?= esc($r['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-sm btn-primary">Filtrele</button></noscript>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if ($invoices === []): ?>
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-primary rounded-circle fs-24"><i class="ri-file-list-3-line"></i></div></div>
                <h5 class="mb-1">Fatura bulunamadı</h5>
                <p class="text-muted mb-0">Bu filtreyle eşleşen fatura yok.</p>
            </div>
        <?php else: ?>
            <?php if ($limited): ?>
                <div class="alert alert-info py-2">En yeni 500 fatura gösteriliyor; daha eskileri için restoran filtresi kullanın.</div>
            <?php endif ?>
            <div class="table-responsive table-card">
                <table class="table align-middle table-nowrap table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>No</th><th>Restoran</th><th>Kesim</th><th>Vade</th><th>Tutar</th><th>Durum</th><th class="text-end">İşlemler</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $i): ?>
                        <tr>
                            <td><a href="<?= site_url('admin/invoices/' . $i['id']) ?>" class="fw-medium"><?= esc((string) $i['number']) ?></a></td>
                            <td><a href="<?= site_url('admin/restaurants/' . $i['restaurant_id'] . '/subscription') ?>" class="text-reset"><?= esc($i['restaurant_name']) ?></a></td>
                            <td><?= esc(pix_date($i['issued_at'])) ?></td>
                            <td><?= esc(pix_date($i['due_date'])) ?></td>
                            <td><?= esc(pix_money($i['total'], $i['currency_code'])) ?></td>
                            <td>
                                <?= view('partials/billing/badge', ['kind' => 'invoice', 'status' => $i['display_status']]) ?>
                                <?php if ($i['status'] === 'paid'): ?><small class="text-muted ms-1"><?= esc(pix_date($i['paid_at'])) ?></small><?php endif ?>
                            </td>
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
<?= $this->endSection() ?>
