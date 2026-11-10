<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Yönetim Paneli</h1>
<ul>
    <?php foreach ($stats as $key => $count): ?>
        <li><?= esc($key) ?>: <?= esc((string) $count) ?></li>
    <?php endforeach ?>
</ul>
<?= $this->endSection() ?>
