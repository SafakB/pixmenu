<?php
/**
 * Çeviri alanları: translations[{code}][{field}] (spec §8.2), dil sekmeli kart.
 *
 * Kendi kartını basar (card-header-tabs bir card-header ister); çağıran view onu kart içine sarmaz.
 *
 * @var list<array{id:int,code:string,native_name:string,direction:string,is_default:bool}> $languages
 * @var array<string, array<string, string|null>>                                           $translations
 * @var array<string, string>                                                               $fields       alan => 'text' | 'textarea'
 */
$oldTranslations = old('translations', null, false);
$errors          = session('errors') ?? [];
$fieldLabels     = ['name' => 'Ad', 'description' => 'Açıklama', 'address' => 'Adres'];
$codes           = array_column($languages, 'code');
$hasError        = static fn (string $code): bool => preg_grep('/^translations\.' . preg_quote($code, '/') . '\./', array_keys($errors)) !== [];
$errorCodes      = array_values(array_filter($codes, $hasError));
$defaultCode     = array_values(array_filter($languages, static fn ($l) => ! empty($l['is_default'])))[0]['code'] ?? ($codes[0] ?? null);
$activeCode      = $errorCodes[0] ?? $defaultCode;
?>
<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <h5 class="card-title mb-0 flex-grow-1">Çeviriler</h5>
        </div>
        <ul class="nav nav-tabs-custom card-header-tabs border-bottom-0 mt-2" role="tablist">
            <?php foreach ($languages as $language): ?>
                <?php $code = $language['code']; ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link<?= $code === $activeCode ? ' active' : '' ?>" data-bs-toggle="tab" href="#lang-<?= esc($code, 'attr') ?>" role="tab">
                        <?= esc($language['native_name']) ?><?= ! empty($language['is_default']) ? ' <span class="text-danger">*</span>' : '' ?>
                        <?php if (in_array($code, $errorCodes, true)): ?><i class="ri-error-warning-fill text-danger align-middle ms-1"></i><?php endif ?>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <?php foreach ($languages as $language): ?>
                <?php $code = $language['code']; ?>
                <div class="tab-pane<?= $code === $activeCode ? ' active' : '' ?>" id="lang-<?= esc($code, 'attr') ?>" role="tabpanel">
                    <?php foreach ($fields as $field => $type): ?>
                        <?php
                        $value    = is_array($oldTranslations)
                            ? (string) ($oldTranslations[$code][$field] ?? '')
                            : (string) ($translations[$code][$field] ?? '');
                        $name     = "translations[{$code}][{$field}]";
                        $id       = "tr-{$code}-{$field}";
                        $error    = $errors["translations.{$code}.{$field}"] ?? null;
                        $required = ! empty($language['is_default']) && $field === 'name';
                        $class    = 'form-control' . ($error !== null ? ' is-invalid' : '');
                        ?>
                        <div class="mb-3">
                            <label class="form-label" for="<?= esc($id, 'attr') ?>">
                                <?= esc($fieldLabels[$field] ?? $field) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?>
                            </label>
                            <?php if ($type === 'textarea'): ?>
                                <textarea class="<?= $class ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" rows="3" dir="<?= esc($language['direction'], 'attr') ?>"><?= esc($value) ?></textarea>
                            <?php else: ?>
                                <input type="text" class="<?= $class ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>" dir="<?= esc($language['direction'], 'attr') ?>"<?= $required ? ' required' : '' ?>>
                            <?php endif ?>
                            <?php if ($error !== null): ?><div class="invalid-feedback"><?= esc($error) ?></div><?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
<script>
// Zorunlu alan kapalı bir sekmede boş kalırsa tarayıcı uyarısı görünmez; ilgili sekmeyi aç.
if (!window.pixTranslationTabs) {
    window.pixTranslationTabs = true;
    document.addEventListener('invalid', function (e) {
        var pane = e.target.closest && e.target.closest('.tab-pane');
        if (pane && !pane.classList.contains('active') && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(document.querySelector('[href="#' + pane.id + '"]')).show();
        }
    }, true);
}
</script>
