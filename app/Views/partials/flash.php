<?php
/**
 * Flash mesajları: success, error, errors (dizi).
 * Minimal işlevsel view — frontend ekibi üzerine yazacak.
 */
$flashErrors = session('errors');
?>
<?php if (session('success')): ?>
    <p class="flash flash-success" role="status"><?= esc(session('success')) ?></p>
<?php endif ?>
<?php if (session('error')): ?>
    <p class="flash flash-error" role="alert"><?= esc(session('error')) ?></p>
<?php endif ?>
<?php if (is_array($flashErrors) && $flashErrors !== []): ?>
    <ul class="flash flash-errors" role="alert">
        <?php foreach ($flashErrors as $field => $message): ?>
            <li data-field="<?= esc((string) $field, 'attr') ?>"><?= esc((string) $message) ?></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
