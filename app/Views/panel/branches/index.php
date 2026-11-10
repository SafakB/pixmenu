<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<h1>Şubeler</h1>
<?php if ($isOwner): ?>
    <p><a href="<?= site_url('panel/branches/new') ?>">Yeni şube</a></p>
<?php endif ?>
<table>
    <thead><tr><th>Ad</th><th>Menü</th><th>Durum</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($branches as $branch): ?>
        <tr>
            <td><?= esc($branch['name']) ?></td>
            <td><a href="<?= esc($branch['menu_url'], 'attr') ?>"><?= esc($branch['public_code']) ?></a></td>
            <td><?= $branch['is_active'] ? 'aktif' : 'pasif' ?></td>
            <td>
                <a href="<?= site_url('panel/branches/' . $branch['id'] . '/categories') ?>">Kategoriler</a>
                <a href="<?= site_url('panel/branches/' . $branch['id'] . '/products') ?>">Ürünler</a>
                <?php if ($isOwner): ?>
                    <a href="<?= site_url('panel/branches/' . $branch['id'] . '/edit') ?>">Düzenle</a>
                    <?= form_open('panel/branches/' . $branch['id'] . '/toggle', ['style' => 'display:inline']) ?>
                        <button type="submit"><?= $branch['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
                    <?= form_close() ?>
                    <?= form_open('panel/branches/' . $branch['id'] . '/delete', ['style' => 'display:inline']) ?>
                        <button type="submit">Sil</button>
                    <?= form_close() ?>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
