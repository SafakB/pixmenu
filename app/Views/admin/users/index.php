<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Restoran kullanıcıları</h1>
<p>
    <a href="<?= site_url('admin/restaurants/' . $restaurant['id']) ?>">← Restoran</a> |
    <a href="<?= site_url('admin/restaurants/' . $restaurant['id'] . '/users/new') ?>">Yeni kullanıcı</a>
</p>
<table>
    <thead><tr><th>Ad</th><th>E-posta</th><th>Rol</th><th>Şube sayısı</th><th>Durum</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <?php $base = 'admin/restaurants/' . $restaurant['id'] . '/users/' . $user['id']; ?>
        <tr>
            <td><?= esc($user['name']) ?></td>
            <td><?= esc($user['email']) ?></td>
            <td><?= esc($user['role']) ?></td>
            <td><?= $user['role'] === 'owner' ? 'tümü' : esc((string) count($user['branch_ids'])) ?></td>
            <td><?= $user['is_active'] ? 'aktif' : 'pasif' ?></td>
            <td>
                <a href="<?= site_url($base . '/edit') ?>">Düzenle</a>
                <?= form_open($base . '/toggle', ['style' => 'display:inline']) ?>
                    <button type="submit"><?= $user['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
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
