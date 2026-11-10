<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Şubeler</h1>
<p>
    <a href="<?= site_url('admin/restaurants/' . $restaurant['id']) ?>">← Restoran</a> |
    <a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/branches/new') ?>">Yeni şube</a>
</p>
<table>
    <thead><tr><th>Sıra</th><th>Ad</th><th>Slug</th><th>Menü</th><th>Durum</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($branches as $branch): ?>
        <?php $base = 'admin/restaurants/' . $restaurant['id'] . '/branches/' . $branch['id']; ?>
        <tr>
            <td><?= esc((string) $branch['sort_order']) ?></td>
            <td><?= esc($branch['name']) ?></td>
            <td><?= esc($branch['slug']) ?></td>
            <td><a href="<?= esc($branch['menu_url'], 'attr') ?>"><?= esc($branch['public_code']) ?></a></td>
            <td><?= $branch['is_active'] ? 'aktif' : 'pasif' ?></td>
            <td>
                <a href="<?= site_url($base . '/edit') ?>">Düzenle</a>
                <?= form_open($base . '/toggle', ['style' => 'display:inline']) ?>
                    <button type="submit"><?= $branch['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
                <?= form_close() ?>
                <?= form_open($base . '/regenerate-code', ['style' => 'display:inline']) ?>
                    <button type="submit" title="Eski QR kodları geçersiz olur">Menü kodunu yenile</button>
                <?= form_close() ?>
                <?= form_open($base . '/delete', ['style' => 'display:inline']) ?>
                    <button type="submit">Sil</button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
