<?= $this->extend('layouts/panel') ?>
<?php
$errors   = session('errors') ?? [];
$hasOld   = old('translations', null, false) !== null;
$isActive = $hasOld ? old('is_active', null, false) === '1' : (int) ($category['is_active'] ?? 1) === 1;
$indexUrl = site_url('panel/branches/' . $branch['id'] . '/categories');
$heading  = $category === null ? 'Yeni kategori' : 'Kategoriyi düzenle';
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';

// Emoji: listeden sonradan çıkarılmış kayıtlı değer de seçilebilir kalır (servis değişmeyen değeri kabul eder).
$storedEmoji   = (string) ($category['emoji'] ?? '');
$selectedEmoji = $hasOld ? (string) old('emoji', '', false) : $storedEmoji;
$emojiChoices  = $emojiGroups;
if ($storedEmoji !== '' && ! in_array($storedEmoji, array_merge(...array_values($emojiGroups)), true)) {
    $emojiChoices = ['Mevcut seçim' => [$storedEmoji]] + $emojiGroups;
}
if (! in_array($selectedEmoji, array_merge(...array_values($emojiChoices)), true)) {
    $selectedEmoji = '';
}
$showEmojiPreview = $image_url === null && $selectedEmoji !== '';
?>
<?php $this->setVar('title', $heading) ?>
<?php $this->setVar('breadcrumbs', [
    ['label' => 'Şubeler', 'url' => site_url('panel/branches')],
    ['label' => $branch['name']],
    ['label' => 'Kategoriler', 'url' => $indexUrl],
    ['label' => $category === null ? 'Yeni' : 'Düzenle'],
]) ?>
<?= $this->section('css') ?>
<style>
    .emoji-picker { max-height: 260px; overflow-y: auto; }
    .emoji-option { width: 2.5rem; height: 2.5rem; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.375rem; line-height: 1; }
    .btn-check:checked + .emoji-option,
    .btn-check:checked + .emoji-none { background-color: var(--vz-success-bg-subtle); border-color: var(--vz-success); color: var(--vz-success); }
    .btn-check:focus-visible + .emoji-option { box-shadow: 0 0 0 .2rem var(--vz-success-border-subtle); }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= form_open_multipart($action) ?>
<div class="row">
    <div class="col-lg-8">
        <?= view('partials/translations', [
            'languages'    => $languages,
            'translations' => $translations,
            'fields'       => ['name' => 'text', 'description' => 'textarea'],
        ]) ?>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Görsel / Emoji</h5></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <img id="image-preview" src="<?= esc($image_url ?? '', 'attr') ?>" alt="" class="img-thumbnail<?= $image_url === null ? ' d-none' : '' ?>" style="max-height:160px">
                    <?php if ($image_url === null): ?>
                        <div id="image-placeholder" class="avatar-lg mx-auto">
                            <div class="avatar-title bg-light text-muted rounded fs-1">
                                <span id="emoji-preview"<?= $showEmojiPreview ? '' : ' class="d-none"' ?>><?= esc($selectedEmoji) ?></span>
                                <i id="emoji-preview-empty" class="ri-image-line<?= $showEmojiPreview ? ' d-none' : '' ?>"></i>
                            </div>
                        </div>
                    <?php endif ?>
                </div>
                <label class="form-label" for="image"><?= $image_url === null ? 'Görsel yükle' : 'Yeni görsel yükle' ?></label>
                <input class="form-control<?= $invalid('image') ?>" type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"
                       data-preview="#image-preview" data-preview-placeholder="#image-placeholder">
                <?php if (isset($errors['image'])): ?><div class="invalid-feedback"><?= esc($errors['image']) ?></div><?php endif ?>
                <div class="form-text">jpg, png, webp — en fazla 3 MB.</div>
                <?php if ($image_url !== null): ?>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1"<?= old('remove_image', null, false) === '1' ? ' checked' : '' ?>>
                        <label class="form-check-label" for="remove_image">Görseli kaldır</label>
                    </div>
                <?php endif ?>

                <div class="border-top mt-3 pt-3">
                    <label class="form-label mb-1" id="emoji-label">Ya da bir emoji seçin</label>
                    <div class="form-text mt-0 mb-2">Görsel hazırlayamıyorsanız menüde kategori görseli yerine emoji gösterilir. İkisi de varsa görsel öncelikli.</div>
                    <div class="emoji-picker border rounded p-2" role="radiogroup" aria-labelledby="emoji-label">
                        <div class="mb-2">
                            <input type="radio" class="btn-check" name="emoji" id="emoji-none" value="" autocomplete="off"<?= $selectedEmoji === '' ? ' checked' : '' ?>>
                            <label class="btn btn-light btn-sm emoji-none" for="emoji-none"><i class="ri-close-line align-bottom me-1"></i>Emoji yok</label>
                        </div>
                        <?php $emojiIndex = 0 ?>
                        <?php foreach ($emojiChoices as $group => $emojis): ?>
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1"><?= esc($group) ?></div>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <?php foreach ($emojis as $emoji): ?>
                                    <?php $emojiId = 'emoji-' . $emojiIndex++ ?>
                                    <input type="radio" class="btn-check" name="emoji" id="<?= $emojiId ?>" value="<?= esc($emoji, 'attr') ?>" autocomplete="off"<?= $emoji === $selectedEmoji ? ' checked' : '' ?>>
                                    <label class="btn btn-light emoji-option" for="<?= $emojiId ?>"><?= esc($emoji) ?></label>
                                <?php endforeach ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?php if (isset($errors['emoji'])): ?><div class="invalid-feedback d-block"><?= esc($errors['emoji']) ?></div><?php endif ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Yayın</h5></div>
            <div class="card-body">
                <div class="form-check form-switch form-switch-success mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
                <label class="form-label" for="sort_order">Sıra</label>
                <input type="number" class="form-control<?= $invalid('sort_order') ?>" id="sort_order" name="sort_order" min="0" max="65535"
                       value="<?= esc((string) old('sort_order', $category['sort_order'] ?? '', false), 'attr') ?>">
                <?php if (isset($errors['sort_order'])): ?><div class="invalid-feedback"><?= esc($errors['sort_order']) ?></div><?php endif ?>
                <div class="form-text">Boş bırakılırsa sona eklenir. Listede sürükleyerek de sıralayabilirsiniz.</div>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <a href="<?= $indexUrl ?>" class="btn btn-light w-100">Vazgeç</a>
                    <button type="submit" class="btn btn-success w-100"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?= form_close() ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function () {
    // Görsel yokken yer tutucuda seçili emojiyi önizle.
    var emoji = document.getElementById('emoji-preview');
    var empty = document.getElementById('emoji-preview-empty');
    if (!emoji || !empty) return;
    document.querySelectorAll('input[name="emoji"]').forEach(function (input) {
        input.addEventListener('change', function () {
            emoji.textContent = input.value;
            emoji.classList.toggle('d-none', input.value === '');
            empty.classList.toggle('d-none', input.value !== '');
        });
    });
})();
</script>
<?= $this->endSection() ?>
