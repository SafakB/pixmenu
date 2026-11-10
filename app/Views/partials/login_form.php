<?php
/**
 * @var string      $action login POST yolu
 * @var string|null $error  429 gibi doğrudan render edilen hata
 */
?>
<?php if (! empty($error)): ?>
    <p class="flash flash-error" role="alert"><?= esc($error) ?></p>
<?php endif ?>
<?= form_open($action) ?>
    <label>E-posta
        <input type="email" name="email" value="<?= esc((string) old('email'), 'attr') ?>" required autocomplete="username">
    </label>
    <label>Şifre
        <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button type="submit">Giriş yap</button>
<?= form_close() ?>
