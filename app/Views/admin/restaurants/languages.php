<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Restoran dilleri</h1>
<p><a href="<?= site_url('admin/restaurants/' . $restaurant['id']) ?>">← Restoran</a></p>
<p>Çıkarılan dilin çevirileri silinmez; dil geri eklenirse veriler geri gelir.</p>
<?= form_open('admin/restaurants/' . $restaurant['id'] . '/languages') ?>
    <?php foreach ($languages as $language): ?>
        <label>
            <input type="checkbox" name="language_ids[]" value="<?= esc((string) $language['id'], 'attr') ?>"<?= $language['selected'] ? ' checked' : '' ?><?= (! $language['is_active'] && ! $language['selected']) ? ' disabled' : '' ?>>
            <?= esc($language['native_name']) ?> (<?= esc($language['code']) ?>)<?= $language['is_default'] ? ' — varsayılan' : '' ?><?= $language['is_active'] ? '' : ' — global olarak pasif' ?>
        </label>
    <?php endforeach ?>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?= $this->endSection() ?>
