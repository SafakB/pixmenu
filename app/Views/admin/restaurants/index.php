<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Restoranlar</h1>
<p><a href="<?= site_url('admin/restaurants/new') ?>">Yeni restoran</a></p>
<table>
    <thead><tr><th>ID</th><th>Ad</th><th>Slug</th><th>Para birimi</th><th>Durum</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($restaurants as $restaurant): ?>
        <tr>
            <td><?= esc((string) $restaurant['id']) ?></td>
            <td><a href="<?= site_url('admin/restaurants/' . $restaurant['id']) ?>"><?= esc($restaurant['name']) ?></a></td>
            <td><?= esc($restaurant['slug']) ?></td>
            <td><?= esc($restaurant['currency_code']) ?></td>
            <td><?= $restaurant['is_active'] ? 'aktif' : 'pasif' ?></td>
            <td>
                <a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/edit') ?>">Düzenle</a>
                <?= form_open('admin/restaurants/' . $restaurant['id'] . '/toggle', ['style' => 'display:inline']) ?>
                    <button type="submit"><?= $restaurant['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
                <?= form_close() ?>
                <?= form_open('admin/restaurants/' . $restaurant['id'] . '/delete', ['style' => 'display:inline']) ?>
                    <button type="submit">Sil</button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
