<?php
/**
 * Çeviri alanları: translations[{code}][{field}] (spec §8.2).
 *
 * @var list<array{id:int,code:string,native_name:string,direction:string,is_default:bool}> $languages
 * @var array<string, array<string, string|null>>                                           $translations
 * @var array<string, string>                                                               $fields       alan => 'text' | 'textarea'
 */
$oldTranslations = old('translations', null, false);
?>
<?php foreach ($languages as $language): ?>
    <?php $code = $language['code']; ?>
    <fieldset dir="<?= esc($language['direction'], 'attr') ?>">
        <legend><?= esc($language['native_name']) ?> (<?= esc($code) ?>)<?= $language['is_default'] ? ' *' : '' ?></legend>
        <?php foreach ($fields as $field => $type): ?>
            <?php
            $value = is_array($oldTranslations)
                ? (string) ($oldTranslations[$code][$field] ?? '')
                : (string) ($translations[$code][$field] ?? '');
            $name = "translations[{$code}][{$field}]";
            ?>
            <label>
                <?= esc($field) ?>
                <?php if ($type === 'textarea'): ?>
                    <textarea name="<?= esc($name, 'attr') ?>"><?= esc($value) ?></textarea>
                <?php else: ?>
                    <input type="text" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>"<?= ($language['is_default'] && $field === 'name') ? ' required' : '' ?>>
                <?php endif ?>
            </label>
        <?php endforeach ?>
    </fieldset>
<?php endforeach ?>
