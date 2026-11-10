<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Alerjenler</h1>
<p><a href="<?= site_url('admin/allergens/new') ?>">Yeni alerjen</a></p>
<table>
    <thead><tr><th>Sıra</th><th>İkon</th><th>Kod</th><th>Ad</th><th>Durum</th><th></th></tr></thead>
    <tbody data-reorder-url="<?= site_url('admin/allergens/reorder') ?>">
    <?php foreach ($allergens as $allergen): ?>
        <tr data-id="<?= esc((string) $allergen['id'], 'attr') ?>">
            <td><?= esc((string) $allergen['sort_order']) ?></td>
            <td><?php if ($allergen['icon_url'] !== null): ?><img src="<?= esc($allergen['icon_url'], 'attr') ?>" alt="" width="32" height="32"><?php endif ?></td>
            <td><?= esc($allergen['code']) ?></td>
            <td><?= esc($allergen['name']) ?></td>
            <td><?= $allergen['is_active'] ? 'aktif' : 'pasif' ?></td>
            <td>
                <a href="<?= site_url('admin/allergens/' . $allergen['id'] . '/edit') ?>">Düzenle</a>
                <?= form_open('admin/allergens/' . $allergen['id'] . '/toggle', ['style' => 'display:inline']) ?>
                    <button type="submit"><?= $allergen['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
                <?= form_close() ?>
                <?= form_open('admin/allergens/' . $allergen['id'] . '/delete', ['style' => 'display:inline']) ?>
                    <button type="submit">Sil</button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
