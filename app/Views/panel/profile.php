<?= $this->extend('layouts/panel') ?>
<?= $this->section('content') ?>
<h1>Profil</h1>
<?= form_open('panel/profile') ?>
    <label>Ad
        <input type="text" name="name" value="<?= esc((string) old('name', $user['name'], false), 'attr') ?>" maxlength="100" required>
    </label>
    <label>E-posta
        <input type="email" name="email" value="<?= esc((string) old('email', $user['email'], false), 'attr') ?>" maxlength="190" required>
    </label>
    <button type="submit">Kaydet</button>
<?= form_close() ?>

<h2>Şifre değiştir</h2>
<?= form_open('panel/profile/password') ?>
    <label>Mevcut şifre
        <input type="password" name="current_password" required autocomplete="current-password">
    </label>
    <label>Yeni şifre (en az 10 karakter, harf + rakam)
        <input type="password" name="password" required autocomplete="new-password">
    </label>
    <label>Yeni şifre (tekrar)
        <input type="password" name="password_confirm" required autocomplete="new-password">
    </label>
    <button type="submit">Şifreyi değiştir</button>
<?= form_close() ?>
<?= $this->endSection() ?>
