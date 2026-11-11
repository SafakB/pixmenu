<?php
/**
 * Kullanıcı formu alanları (admin + panel ortak).
 *
 * @var array<string, mixed>|null         $user
 * @var list<array{id:int,name:string}>   $branches
 * @var list<int>                         $assigned
 * @var bool                              $allowRole  admin: rol seçilebilir; panel: her zaman manager
 */
$oldBranchIds = old('branch_ids', null, false);
$checked      = is_array($oldBranchIds) ? array_map('intval', $oldBranchIds) : $assigned;
$role         = (string) old('role', $user['role'] ?? 'manager', false);
?>
<label>Ad
    <input type="text" name="name" value="<?= esc((string) old('name', $user['name'] ?? '', false), 'attr') ?>" maxlength="100" required>
</label>
<label>E-posta
    <input type="email" name="email" value="<?= esc((string) old('email', $user['email'] ?? '', false), 'attr') ?>" maxlength="190" required>
</label>
<label>Şifre (en az 10 karakter, harf + rakam)<?= $user === null ? '' : ' — boş bırakılırsa değişmez' ?>
    <input type="password" name="password" autocomplete="new-password"<?= $user === null ? ' required' : '' ?>>
</label>
<?php if ($allowRole): ?>
    <label>Rol
        <select name="role">
            <option value="owner"<?= $role === 'owner' ? ' selected' : '' ?>>owner (restoran sahibi)</option>
            <option value="manager"<?= $role === 'manager' ? ' selected' : '' ?>>manager (şube yöneticisi)</option>
        </select>
    </label>
<?php endif ?>
<fieldset>
    <legend>Şubeler (manager için en az bir)</legend>
    <?php foreach ($branches as $branch): ?>
        <label>
            <input type="checkbox" name="branch_ids[]" value="<?= esc((string) $branch['id'], 'attr') ?>"<?= in_array($branch['id'], $checked, true) ? ' checked' : '' ?>>
            <?= esc($branch['name']) ?>
        </label>
    <?php endforeach ?>
</fieldset>
<label>
    <input type="checkbox" name="is_active" value="1"<?= (int) ($user['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif
</label>
