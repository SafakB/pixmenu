<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Diller</h1>
<p><a href="<?= site_url('admin/languages/new') ?>">Yeni dil</a></p>
<table>
    <thead><tr><th>Sıra</th><th>Kod</th><th>Ad</th><th>Yerel ad</th><th>Yön</th><th>Durum</th><th></th></tr></thead>
    <tbody data-reorder-url="<?= site_url('admin/languages/reorder') ?>">
    <?php foreach ($languages as $language): ?>
        <tr data-id="<?= esc((string) $language['id'], 'attr') ?>">
            <td><?= esc((string) $language['sort_order']) ?></td>
            <td><?= esc($language['code']) ?></td>
            <td><?= esc($language['name']) ?></td>
            <td><?= esc($language['native_name']) ?></td>
            <td><?= esc($language['direction']) ?></td>
            <td><?= (int) $language['is_active'] === 1 ? 'aktif' : 'pasif' ?></td>
            <td>
                <a href="<?= site_url('admin/languages/' . $language['id'] . '/edit') ?>">Düzenle</a>
                <?= form_open('admin/languages/' . $language['id'] . '/toggle', ['style' => 'display:inline']) ?>
                    <button type="submit"><?= (int) $language['is_active'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?></button>
                <?= form_close() ?>
                <?= form_open('admin/languages/' . $language['id'] . '/delete', ['style' => 'display:inline']) ?>
                    <button type="submit">Sil</button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
