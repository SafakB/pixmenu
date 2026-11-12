<?php
/**
 * Kullanıcı formu gövdesi (admin + panel ortak): iki kolonlu düzen + kaydet kartı.
 * Çağıran view yalnızca form_open/form_close ile sarar.
 *
 * @var array<string, mixed>|null         $user
 * @var list<array{id:int,name:string}>   $branches
 * @var list<int>                         $assigned
 * @var bool                              $allowRole  admin: rol seçilebilir; panel: her zaman manager
 * @var string                            $cancel_url "Vazgeç" bağlantısı
 */
$errors       = session('errors') ?? [];
$hasOld       = old('email', null, false) !== null; // e-posta her gönderimde gelir → hata sonrası dönüş işareti
$oldBranchIds = old('branch_ids', null, false);
$checked      = is_array($oldBranchIds) ? array_map('intval', $oldBranchIds) : ($hasOld ? [] : array_map('intval', $assigned));
$role         = (string) old('role', $user['role'] ?? 'manager', false);
$isActive     = $hasOld ? old('is_active', null, false) === '1' : (int) ($user['is_active'] ?? 1) === 1;
$isNew        = $user === null;
$invalid      = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback     = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
?>
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Hesap bilgileri</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="name">Ad soyad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="100" required
                               value="<?= esc((string) old('name', $user['name'] ?? '', false), 'attr') ?>">
                        <?= $feedback('name') ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="email">E-posta <span class="text-danger">*</span></label>
                        <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email" maxlength="190" required autocomplete="off"
                               value="<?= esc((string) old('email', $user['email'] ?? '', false), 'attr') ?>">
                        <?= $feedback('email') ?>
                        <div class="form-text">Giriş için kullanılır.</div>
                    </div>
                    <div class="col-md-6 mb-0">
                        <label class="form-label" for="password">Şifre<?= $isNew ? ' <span class="text-danger">*</span>' : '' ?></label>
                        <div class="position-relative auth-pass-inputgroup">
                            <input type="password" class="form-control pe-5 password-input<?= $invalid('password') ?>" id="password" name="password" autocomplete="new-password"<?= $isNew ? ' required' : '' ?><?= isset($errors['password']) ? ' style="background-image:none"' : '' ?>
                                   placeholder="<?= $isNew ? '' : 'Değiştirmek için yeni şifre girin' ?>">
                            <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button" aria-label="Şifreyi göster"><i class="ri-eye-fill align-middle"></i></button>
                            <?= $feedback('password') ?>
                        </div>
                        <div class="form-text">En az 10 karakter, harf ve rakam içermeli.<?= $isNew ? '' : ' Boş bırakılırsa değişmez.' ?></div>
                    </div>
                    <?php if ($allowRole): ?>
                        <div class="col-md-6 mb-0">
                            <label class="form-label" for="role">Rol</label>
                            <select class="form-select<?= $invalid('role') ?>" id="role" name="role">
                                <option value="owner"<?= $role === 'owner' ? ' selected' : '' ?>>Restoran sahibi (owner)</option>
                                <option value="manager"<?= $role === 'manager' ? ' selected' : '' ?>>Şube yöneticisi (manager)</option>
                            </select>
                            <?= $feedback('role') ?>
                            <div class="form-text">Restoran sahibi tüm şubeleri yönetir; şube seçimi yalnızca yönetici için geçerlidir.</div>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Yetkili şubeler</h5>
            </div>
            <div class="card-body">
                <?php if ($branches === []): ?>
                    <p class="text-muted mb-0">Henüz şube yok. Şube yöneticisi atamak için önce bir şube oluşturun.</p>
                <?php else: ?>
                    <p class="text-muted small mb-3">Şube yöneticisi yalnızca işaretlenen şubelerin menüsünü yönetebilir. En az bir şube seçilmeli.</p>
                    <div class="row g-2<?= isset($errors['branch_ids']) ? ' is-invalid' : '' ?>">
                        <?php foreach ($branches as $branch): ?>
                            <?php $id = (int) $branch['id']; ?>
                            <div class="col-sm-6 col-xl-4">
                                <div class="form-check card-radio">
                                    <input class="form-check-input" type="checkbox" id="branch_<?= $id ?>" name="branch_ids[]" value="<?= esc((string) $id, 'attr') ?>"<?= in_array($id, $checked, true) ? ' checked' : '' ?>>
                                    <label class="form-check-label text-truncate" for="branch_<?= $id ?>">
                                        <i class="ri-store-2-line align-middle text-muted me-1"></i> <?= esc($branch['name']) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?= $feedback('branch_ids') ?>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Durum</h5></div>
            <div class="card-body">
                <div class="form-check form-switch form-switch-success mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>
                <p class="text-muted small mb-0">Pasif kullanıcı panele giriş yapamaz; açık oturumu kapanır.</p>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <a href="<?= esc($cancel_url, 'attr') ?>" class="btn btn-light w-100">Vazgeç</a>
                    <button type="submit" class="btn btn-success w-100"><i class="ri-save-line align-bottom me-1"></i> Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</div>
