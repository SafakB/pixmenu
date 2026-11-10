<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<h1>Kullanıcılar</h1>
<p><a href="<?= site_url('panel/users/new') ?>">Yeni şube yöneticisi</a></p>
<table>
    <thead><tr><th>Ad</th><th>E-posta</th><th>Rol</th><th>Şube sayısı</th><th>Durum</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= esc($user['name']) ?></td>
            <td><?= esc($user['email']) ?></td>
            <td><?= esc($user['role']) ?></td>
            <td><?= $user['role'] === 'owner' ? 'tümü' : esc((string) count($user['branch_ids'])) ?></td>
            <td><?= $user['is_active'] ? 'aktif' : 'pasif' ?></td>
            <td>
                <?php if ($user['editable']): ?>
                    <a href="<?= site_url('panel/users/' . $user['id'] . '/edit') ?>">Düzenle</a>
                    <?= form_open('panel/users/' . $user['id'] . '/toggle', ['style' => 'display:inline']) ?>
                        <button type="submit"><?= $user['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
                    <?= form_close() ?>
                    <?= form_open('panel/users/' . $user['id'] . '/delete', ['style' => 'display:inline']) ?>
                        <button type="submit">Sil</button>
                    <?= form_close() ?>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
