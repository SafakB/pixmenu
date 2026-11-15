<?= $this->extend('layouts/panel') ?>
<?php
$base       = 'panel/branches/' . $branch['id'];
$indexUrl   = site_url($base . '/products');
$hasPrice   = $filters['min_price'] !== null || $filters['max_price'] !== null;
$isFiltered = $filters['category'] !== null || $filters['q'] !== '' || $hasPrice;
// Ürünler kategori içinde sıralanır; sürükle-bırak yalnızca tek kategori, ek filtre olmadan listelenirken anlamlıdır.
$canReorder = $filters['category'] !== null && $filters['q'] === '' && ! $hasPrice && count($products) > 1;

// Fiyat kaydırıcısının sınırları tam sayıya yuvarlanır; tek fiyat varsa kaydırıcı çizilmez.
$rangeMin  = (int) floor($price_range['min']);
$rangeMax  = (int) ceil($price_range['max']);
$hasSlider = $rangeMax > $rangeMin;

// Fiyat input'unda görünen değer: 129.9 → "129,9", 100.0 → "100".
$priceInput = static fn (?float $v): string => $v === null ? '' : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');

// Mevcut filtreleri koruyarak liste URL'si üretir; null / '' değerler URL'ye girmez.
$filterUrl = static function (array $override) use ($filters, $indexUrl): string {
    $params = array_filter(array_merge([
        'category'  => $filters['category'],
        'q'         => $filters['q'],
        'min_price' => $filters['min_price'],
        'max_price' => $filters['max_price'],
    ], $override), static fn ($v): bool => $v !== null && $v !== '');

    return $indexUrl . ($params === [] ? '' : '?' . http_build_query($params));
};

$activeCount  = count(array_filter($products, static fn (array $p): bool => (int) $p['is_active'] === 1));
$passiveCount = count($products) - $activeCount;
$tabs         = [
    'all'     => ['Tümü', count($products)],
    'active'  => ['Aktif', $activeCount],
    'passive' => ['Pasif', $passiveCount],
];
?>
<?php $this->setVar('title', 'Ürünler') ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Şubeler', 'url' => site_url('panel/branches')],
    ['label' => $branch['name']],
    ['label' => 'Ürünler'],
]) ?>
<?= $this->section('css') ?>
<link rel="stylesheet" href="<?= asset_url('libs/nouislider/nouislider.min.css') ?>">
<style>
    #sortable-rows .drag-handle { cursor: grab; }
    #sortable-rows .sortable-ghost { opacity: .4; }
    .product-filter-list a { color: var(--vz-body-color); }
    .product-filter-list a.active, .product-filter-list a:hover { color: var(--vz-success); }
    #product-list.reorder-off .reorder-col { display: none; }
    /* nouislider.min.css, sayfa CSS'i app.min.css'ten sonra yüklendiği için Velzon'un kaydırıcı stilini ezer; burada geri alınır. */
    #product-price-range.noUi-target { box-shadow: none; background-color: var(--vz-light); border-color: var(--vz-light); }
    #product-price-range.noUi-horizontal { height: 4px; margin: 0 8px; }
    #product-price-range .noUi-connect { background: var(--vz-success); }
    #product-price-range .noUi-handle { height: 16px; width: 16px; border-radius: 50%; right: -8px !important; top: -7px;
        background: var(--vz-success); border: 2px solid var(--vz-card-bg); box-shadow: none; cursor: pointer; }
    #product-price-range .noUi-handle::before, #product-price-range .noUi-handle::after { display: none; }
    #product-price-range .noUi-handle:focus { outline: 0; }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-3 col-lg-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <h5 class="fs-16 mb-0 flex-grow-1">Filtreler</h5>
                    <?php if ($isFiltered): ?>
                        <a href="<?= $indexUrl ?>" class="text-decoration-underline">Temizle</a>
                    <?php endif ?>
                </div>
            </div>

            <form id="product-filter" method="get" action="<?= $indexUrl ?>">
                <?php if ($filters['category'] !== null): ?>
                    <input type="hidden" name="category" value="<?= esc((string) $filters['category'], 'attr') ?>">
                <?php endif ?>

                <div class="card-body border-bottom">
                    <p class="text-muted text-uppercase fs-12 fw-medium mb-2">Kategori</p>
                    <?php if ($categories === []): ?>
                        <p class="text-muted mb-0">Henüz kategori yok. <a href="<?= site_url($base . '/categories') ?>">Kategori ekleyin</a>.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 product-filter-list">
                            <?php
                            $categoryLinks = [['id' => null, 'name' => 'Tüm kategoriler']];
                            foreach ($categories as $category) {
                                $categoryLinks[] = ['id' => (int) $category['id'], 'name' => $category['name']];
                            }
                            ?>
                            <?php foreach ($categoryLinks as $link): ?>
                                <?php $isCurrent = $filters['category'] === $link['id'] ?>
                                <li>
                                    <a href="<?= esc($filterUrl(['category' => $link['id']]), 'attr') ?>" class="d-flex py-1 align-items-center<?= $isCurrent ? ' active' : '' ?>"<?= $isCurrent ? ' aria-current="true"' : '' ?>>
                                        <div class="flex-grow-1">
                                            <h5 class="fs-13 mb-0 <?= $isCurrent ? 'fw-semibold text-success' : 'fw-normal' ?>"><?= esc($link['name']) ?></h5>
                                        </div>
                                        <?php if ($isCurrent): ?>
                                            <div class="flex-shrink-0 ms-2 text-success"><i class="ri-check-line"></i></div>
                                        <?php endif ?>
                                    </a>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    <?php endif ?>
                </div>

                <div class="card-body">
                    <p class="text-muted text-uppercase fs-12 fw-medium mb-4">Fiyat <span class="badge bg-light text-muted ms-1"><?= esc($currency) ?></span></p>
                    <?php if ($hasSlider): ?>
                        <div id="product-price-range" class="mb-3"
                             data-min="<?= esc((string) $rangeMin, 'attr') ?>" data-max="<?= esc((string) $rangeMax, 'attr') ?>"></div>
                    <?php endif ?>
                    <div class="d-flex gap-2 align-items-center">
                        <input class="form-control form-control-sm" type="text" inputmode="decimal" id="min_price" name="min_price" maxlength="12"
                               value="<?= esc($priceInput($filters['min_price']), 'attr') ?>" placeholder="<?= esc((string) $rangeMin, 'attr') ?>" aria-label="En düşük fiyat">
                        <span class="fw-semibold text-muted">–</span>
                        <input class="form-control form-control-sm" type="text" inputmode="decimal" id="max_price" name="max_price" maxlength="12"
                               value="<?= esc($priceInput($filters['max_price']), 'attr') ?>" placeholder="<?= esc((string) $rangeMax, 'attr') ?>" aria-label="En yüksek fiyat">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-3"><i class="ri-equalizer-fill align-bottom me-1"></i> Uygula</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-9 col-lg-8">
        <div class="card" id="product-list">
            <div class="card-header border-0">
                <div class="row g-3 align-items-center">
                    <div class="col-sm-auto d-flex gap-2">
                        <a href="<?= site_url($base . '/products/new') ?>" class="btn btn-success"><i class="ri-add-line align-bottom me-1"></i> Yeni ürün</a>
                        <a href="<?= site_url($base . '/categories') ?>" class="btn btn-soft-secondary"><i class="ri-folder-2-line align-bottom me-1"></i> Kategoriler</a>
                    </div>
                    <div class="col-sm">
                        <div class="d-flex justify-content-sm-end">
                            <div class="search-box">
                                <input type="search" class="form-control" name="q" form="product-filter" value="<?= esc($filters['q'], 'attr') ?>"
                                       maxlength="100" placeholder="Ürün adı ara…" aria-label="Ürün adı ara">
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-header">
                <div class="row align-items-center g-2">
                    <div class="col-12 col-md">
                        <ul class="nav nav-tabs-custom card-header-tabs border-bottom-0" role="tablist" id="product-tabs">
                            <?php foreach ($tabs as $key => [$label, $count]): ?>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link fw-semibold<?= $key === 'all' ? ' active' : '' ?>" href="#" role="tab" data-filter="<?= $key ?>"
                                       aria-selected="<?= $key === 'all' ? 'true' : 'false' ?>">
                                        <?= esc($label) ?> <span class="badge bg-<?= $key === 'passive' ? 'danger' : 'success' ?>-subtle text-<?= $key === 'passive' ? 'danger' : 'success' ?> align-middle rounded-pill ms-1"><?= esc((string) $count) ?></span>
                                    </a>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    </div>
                    <?php if (count($products) > 1): ?>
                        <div class="col-12 col-md-auto order-first order-md-last">
                            <p class="text-muted small mb-2 mb-md-0 pt-2 pt-md-0" id="reorder-hint">
                                <?php if ($canReorder): ?>
                                    <i class="ri-drag-move-2-line align-bottom me-1"></i>Sırayı değiştirmek için satırları tutamaktan sürükleyin.
                                <?php else: ?>
                                    <i class="ri-information-line align-bottom me-1"></i>Sıralamak için yalnızca bir kategori seçin.
                                <?php endif ?>
                            </p>
                        </div>
                    <?php endif ?>
                </div>
            </div>

            <div class="card-body">
                <?php if ($products === []): ?>
                    <div class="text-center py-5">
                        <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-success rounded-circle fs-24"><i class="<?= $isFiltered ? 'ri-search-line' : 'ri-restaurant-line' ?>"></i></div></div>
                        <?php if ($isFiltered): ?>
                            <h5 class="mb-1">Sonuç bulunamadı</h5>
                            <p class="text-muted mb-0">Filtreye uyan ürün yok. <a href="<?= $indexUrl ?>">Filtreyi temizleyin</a>.</p>
                        <?php else: ?>
                            <h5 class="mb-1">Henüz ürün yok</h5>
                            <p class="text-muted mb-0">İlk ürünü eklemek için "Yeni ürün"e tıklayın.</p>
                        <?php endif ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive table-card">
                        <table class="table align-middle table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <?php if ($canReorder): ?><th class="reorder-col" style="width:40px"></th><?php endif ?>
                                    <th>Ürün</th>
                                    <th>Fiyat</th>
                                    <th>Durum</th>
                                    <th class="text-end">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody<?= $canReorder ? ' id="sortable-rows"' : '' ?>>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $rowBase  = $base . '/products/' . $product['id'];
                                $isActive = (int) $product['is_active'] === 1;
                                ?>
                                <tr data-id="<?= esc((string) $product['id'], 'attr') ?>" data-status="<?= $isActive ? 'active' : 'passive' ?>">
                                    <?php if ($canReorder): ?>
                                        <td class="reorder-col">
                                            <span class="drag-handle text-muted fs-18" data-bs-toggle="tooltip" title="Sürükleyerek sırala" aria-label="Sürükleyerek sırala"><i class="ri-drag-move-2-line"></i></span>
                                        </td>
                                    <?php endif ?>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-sm bg-light rounded p-1">
                                                    <?php if ($product['thumb_url'] !== null): ?>
                                                        <img src="<?= esc($product['thumb_url'], 'attr') ?>" alt="" class="img-fluid d-block w-100 h-100 rounded object-fit-cover">
                                                    <?php else: ?>
                                                        <div class="avatar-title bg-transparent text-muted fs-20"><i class="ri-image-line"></i></div>
                                                    <?php endif ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="fs-14 mb-1"><a href="<?= site_url($rowBase . '/edit') ?>" class="text-body"><?= esc($product['name']) ?></a></h5>
                                                <p class="text-muted mb-0">Kategori:
                                                    <a href="<?= esc($filterUrl(['category' => (int) $product['category_id'], 'q' => null, 'min_price' => null, 'max_price' => null]), 'attr') ?>"
                                                       class="fw-medium text-muted"><?= esc($product['category_name']) ?></a>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-medium"><?= esc(pix_money($product['price'], $currency)) ?></td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success-subtle text-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">Pasif</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="<?= site_url($rowBase . '/edit') ?>" class="btn btn-sm btn-icon btn-soft-primary"
                                               data-bs-toggle="tooltip" title="Düzenle" aria-label="Düzenle"><i class="ri-pencil-line"></i></a>
                                            <?= form_open($rowBase . '/toggle', ['class' => 'd-inline']) ?>
                                                <?php $label = $isActive ? 'Pasifleştir' : 'Aktifleştir' ?>
                                                <button type="submit" class="btn btn-sm btn-icon btn-soft-<?= $isActive ? 'warning' : 'success' ?>"
                                                        data-bs-toggle="tooltip" title="<?= $label ?>" aria-label="<?= $label ?>"><i class="ri-shut-down-line"></i></button>
                                            <?= form_close() ?>
                                            <?= form_open($rowBase . '/delete', [
                                                'class'               => 'd-inline',
                                                'data-confirm'        => $product['name'] . ' silinecek. Bu işlem geri alınamaz.',
                                                'data-confirm-button' => 'Evet, sil',
                                            ]) ?>
                                                <button type="submit" class="btn btn-sm btn-icon btn-soft-danger"
                                                        data-bs-toggle="tooltip" title="Sil" aria-label="Sil"><i class="ri-delete-bin-line"></i></button>
                                            <?= form_close() ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center py-5 d-none" id="tab-empty">
                        <div class="avatar-md mx-auto mb-3"><div class="avatar-title bg-light text-success rounded-circle fs-24"><i class="ri-filter-off-line"></i></div></div>
                        <h5 class="mb-0">Bu sekmede ürün yok</h5>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script src="<?= asset_url('libs/nouislider/nouislider.min.js') ?>"></script>
<script src="<?= asset_url('libs/wnumb/wNumb.min.js') ?>"></script>
<?php if ($canReorder): ?>
<script src="<?= asset_url('libs/sortablejs/Sortable.min.js') ?>"></script>
<?php endif ?>
<script>
(function () {
    var form = document.getElementById('product-filter');
    var minInput = document.getElementById('min_price');
    var maxInput = document.getElementById('max_price');
    var sliderEl = document.getElementById('product-price-range');
    var sortable = null;

    // --- Fiyat kaydırıcısı: sürükleyince input'ları doldurur; sınırdaki değer boş bırakılır (filtre yok).
    if (sliderEl && window.noUiSlider) {
        var lo = +sliderEl.dataset.min, hi = +sliderEl.dataset.max;
        var parse = function (input, fallback) {
            var v = parseFloat(String(input.value).replace(',', '.'));
            return isNaN(v) ? fallback : v;
        };
        noUiSlider.create(sliderEl, {
            start: [parse(minInput, lo), parse(maxInput, hi)],
            connect: true,
            step: 1,
            range: { min: lo, max: hi },
            format: wNumb({ decimals: 0 })
        });
        sliderEl.noUiSlider.on('slide', function (values, handle) {
            var v = +values[handle];
            if (handle === 0) minInput.value = v <= lo ? '' : v;
            else maxInput.value = v >= hi ? '' : v;
        });
        [minInput, maxInput].forEach(function (input, i) {
            input.addEventListener('change', function () {
                var vals = [null, null];
                vals[i] = parse(input, i === 0 ? lo : hi);
                sliderEl.noUiSlider.set(vals);
            });
        });
    }

    // Boş alanlar URL'ye girmesin.
    if (form) {
        form.addEventListener('submit', function () {
            document.querySelectorAll('[name][form="product-filter"], #product-filter [name]').forEach(function (el) {
                if (el.value === '') el.disabled = true;
            });
        });
    }

    // --- Sekmeler: satırları duruma göre süzer (istemci tarafı).
    var card = document.getElementById('product-list');
    var tabs = document.querySelectorAll('#product-tabs [data-filter]');
    var empty = document.getElementById('tab-empty');
    var table = card ? card.querySelector('.table-card') : null;
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            var filter = tab.dataset.filter, visible = 0;
            tabs.forEach(function (t) {
                var on = t === tab;
                t.classList.toggle('active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            card.querySelectorAll('tbody tr[data-status]').forEach(function (row) {
                var show = filter === 'all' || row.dataset.status === filter;
                row.classList.toggle('d-none', !show);
                if (show) visible++;
            });
            if (table) table.classList.toggle('d-none', visible === 0);
            if (empty) empty.classList.toggle('d-none', visible !== 0);
            // Alt kümede sürüklemek gizli satırlara göre sırayı bozar: yalnız "Tümü"nde açık.
            card.classList.toggle('reorder-off', filter !== 'all');
            if (sortable) sortable.option('disabled', filter !== 'all');
            var hint = document.getElementById('reorder-hint');
            if (hint && sortable) hint.classList.toggle('invisible', filter !== 'all');
        });
    });

<?php if ($canReorder): ?>
    // --- Sürükle-bırak sıralama
    var meta = document.querySelector('meta[name="csrf-token"]');
    var list = document.getElementById('sortable-rows');
    if (list && meta && window.Sortable) {
        sortable = Sortable.create(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function (evt) {
                if (evt.oldIndex === evt.newIndex) return;
                var ids = Array.from(list.querySelectorAll('[data-id]')).map(function (el) { return +el.dataset.id; });
                fetch(<?= json_encode(site_url($base . '/products/reorder')) ?>, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta.content },
                    body: JSON.stringify({ ids: ids })
                }).then(function (res) {
                    var t = res.headers.get('X-CSRF-TOKEN'); if (t) meta.content = t;
                    if (!res.ok) location.reload();
                }).catch(function () { location.reload(); });
            }
        });
    }
<?php endif ?>
})();
</script>
<?= $this->endSection() ?>
