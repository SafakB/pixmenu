<?php
/**
 * Şube formu alanları (admin + panel ortak).
 *
 * @var array<string, mixed>|null                  $branch
 * @var list<array<string, mixed>>                 $languages    restoranın aktif dilleri (is_default = zorunlu dil)
 * @var array<string, array<string, string|null>>  $translations
 */
$default = (string) old('default_language_id', $branch['default_language_id'] ?? '', false);
?>
<label>Slug (boşsa addan üretilir)
    <input type="text" name="slug" value="<?= esc((string) old('slug', $branch['slug'] ?? '', false), 'attr') ?>" maxlength="100">
</label>
<label>Şubenin varsayılan menü dili
    <select name="default_language_id" required>
        <?php foreach ($languages as $language): ?>
            <option value="<?= esc((string) $language['id'], 'attr') ?>"<?= $default === (string) $language['id'] ? ' selected' : '' ?>><?= esc($language['native_name']) ?></option>
        <?php endforeach ?>
    </select>
</label>
<label>Telefon
    <input type="text" name="phone" value="<?= esc((string) old('phone', $branch['phone'] ?? '', false), 'attr') ?>" maxlength="30">
</label>
<label>Sıra
    <input type="number" name="sort_order" min="0" max="65535" value="<?= esc((string) old('sort_order', $branch['sort_order'] ?? 0, false), 'attr') ?>">
</label>
<label>
    <input type="checkbox" name="is_active" value="1"<?= (int) ($branch['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif
</label>
<?= view('partials/translations', [
    'languages'    => $languages,
    'translations' => $translations,
    'fields'       => ['name' => 'text', 'address' => 'text', 'description' => 'textarea'],
]) ?>
