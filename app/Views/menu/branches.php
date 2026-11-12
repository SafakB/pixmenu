<?php
/**
 * Özel alan adında şube seçimi (birden fazla aktif şube) — minimal işlevsel view.
 *
 * @var array{name: string, logo_url: string|null}                                   $restaurant
 * @var list<array{slug: string, name: string, address: string|null, url: string}> $branches
 */
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($restaurant['name']) ?></title>
</head>
<body>
<header>
    <?php if ($restaurant['logo_url'] !== null): ?>
        <img src="<?= esc($restaurant['logo_url'], 'attr') ?>" alt="" width="96">
    <?php endif ?>
    <h1><?= esc($restaurant['name']) ?></h1>
    <p>Menüsünü görmek istediğiniz şubeyi seçin.</p>
</header>
<main>
    <ul>
        <?php foreach ($branches as $branch): ?>
            <li>
                <a href="<?= esc($branch['url'], 'attr') ?>"><?= esc($branch['name']) ?></a>
                <?php if ($branch['address'] !== null && $branch['address'] !== ''): ?>
                    <small><?= esc($branch['address']) ?></small>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
</main>
</body>
</html>
