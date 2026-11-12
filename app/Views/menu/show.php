<?php
/**
 * Herkese açık menü — minimal işlevsel view (frontend ekibi üzerine yazacak).
 *
 * @var array<string, mixed> $menu    spec §11.3 `data` yapısı
 * @var string               $menuUrl dil bağlantılarının taban adresi (/m/{kod} veya özel alan adında / , /{şube})
 */
$menuUrl ??= site_url('m/' . $menu['branch']['code']);
$allergensById = array_column($menu['allergens'], null, 'id');
$levelLabels   = ['contains' => 'içerir', 'may_contain' => 'eser miktarda içerebilir'];
?>
<!doctype html>
<html lang="<?= esc($menu['language']['code'], 'attr') ?>" dir="<?= esc($menu['language']['direction'], 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($menu['restaurant']['name']) ?> — <?= esc($menu['branch']['name']) ?></title>
</head>
<body>
<header>
    <?php if ($menu['restaurant']['logo_url'] !== null): ?>
        <img src="<?= esc($menu['restaurant']['logo_url'], 'attr') ?>" alt="" width="96">
    <?php endif ?>
    <h1><?= esc($menu['restaurant']['name']) ?></h1>
    <p><?= esc($menu['branch']['name']) ?><?= $menu['branch']['address'] !== null ? ' — ' . esc($menu['branch']['address']) : '' ?></p>
    <nav aria-label="Dil">
        <?php foreach ($menu['languages'] as $language): ?>
            <a href="<?= esc($menuUrl . '?lang=' . rawurlencode($language['code']), 'attr') ?>"<?= $language['code'] === $menu['language']['code'] ? ' aria-current="true"' : '' ?>><?= esc($language['native_name']) ?></a>
        <?php endforeach ?>
    </nav>
</header>
<main>
    <?php foreach ($menu['categories'] as $category): ?>
        <section>
            <h2><?= esc($category['name']) ?></h2>
            <?php if ($category['description'] !== null): ?><p><?= esc($category['description']) ?></p><?php endif ?>
            <ul>
                <?php foreach ($category['products'] as $product): ?>
                    <li>
                        <?php if ($product['thumb_url'] !== null): ?><img src="<?= esc($product['thumb_url'], 'attr') ?>" alt="" width="96" loading="lazy"><?php endif ?>
                        <strong><?= esc($product['name']) ?></strong>
                        <span data-price="<?= esc($product['price'], 'attr') ?>" data-currency="<?= esc($menu['restaurant']['currency'], 'attr') ?>"><?= esc($product['price']) ?> <?= esc($menu['restaurant']['currency']) ?></span>
                        <?php if ($product['description'] !== null): ?><p><?= esc($product['description']) ?></p><?php endif ?>
                        <?php if ($product['allergens'] !== []): ?>
                            <small>
                                <?php foreach ($product['allergens'] as $allergen): ?>
                                    <span><?= esc($allergensById[$allergen['id']]['name'] ?? '') ?> (<?= esc($levelLabels[$allergen['level']] ?? $allergen['level']) ?>)</span>
                                <?php endforeach ?>
                            </small>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>
    <?php endforeach ?>
</main>
</body>
</html>
