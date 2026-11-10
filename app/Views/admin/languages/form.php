<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1><?= $language === null ? 'Yeni dil' : 'Dili düzenle' ?></h1>
<?= form_open($action) ?>
    <label>Kod (ör. tr, en, pt-BR)
        <?php if ($language === null): ?>
            <input type="text" name="code" value="<?= esc((string) old('code'), 'attr') ?>" maxlength="10" required>
        <?php else: ?>
            <input type="text" value="<?= esc($language['code'], 'attr') ?>" disabled>
        <?php endif ?>
    </label>
    <label>Ad (İngilizce)
        <input type="text" name="name" value="<?= esc((string) old('name', $language['name'] ?? ''), 'attr') ?>" maxlength="60" required>
    </label>
    <label>Yerel ad
        <input type="text" name="native_name" value="<?= esc((string) old('native_name', $language['native_name'] ?? ''), 'attr') ?>" maxlength="60" required>
    </label>
    <label>Yön
        <?php $direction = (string) old('direction', $language['direction'] ?? 'ltr'); ?>
        <select name="direction">
            <option value="ltr"<?= $direction === 'ltr' ? ' selected' : '' ?>>ltr</option>
            <option value="rtl"<?= $direction === 'rtl' ? ' selected' : '' ?>>rtl</option>
        </select>
    </label>
    <label>Sıra
        <input type="number" name="sort_order" min="0" max="65535" value="<?= esc((string) old('sort_order', $language['sort_order'] ?? 0), 'attr') ?>">
    </label>
    <label>
        <input type="checkbox" name="is_active" value="1"<?= (int) old('is_active', $language['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif
    </label>
    <button type="submit">Kaydet</button>
<?= form_close() ?>
<?= $this->endSection() ?>
