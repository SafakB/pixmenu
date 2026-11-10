<?php

declare(strict_types=1);

namespace App\Libraries\Auth;

/**
 * Admin ve restoran paneli kimlik doğrulamasının ortak mantığı (spec §7).
 *
 * Her alt sınıfın KENDİ oturum anahtarı vardır; biri diğerinin anahtarını
 * asla okumaz veya silmez (spec §2.2).
 */
abstract class BaseAuth
{
    /**
     * Kullanıcı bulunamadığında da password_verify çağrılabilsin diye (zamanlama eşitliği).
     */
    private const DUMMY_HASH = '$2y$12$SA8jaBVEKPZ69yjNDJhJHeYwRP3vNX4lgi4ibtt2gaS2rE4nYZvt6';

    /** @var array<string, mixed>|null */
    private ?array $user   = null;
    private bool $resolved = false;

    abstract protected function sessionKey(): string;

    /**
     * @return array<string, mixed>|null
     */
    abstract protected function findByEmail(string $email): ?array;

    /**
     * @return array<string, mixed>|null
     */
    abstract protected function findById(int $id): ?array;

    /**
     * Kayıt şu an giriş yapabilir/oturumu sürdürebilir durumda mı?
     *
     * @param array<string, mixed>      $user
     * @param array<string, mixed>|null $session oturum verisi (login denemesinde null)
     */
    abstract protected function isUsable(array $user, ?array $session): bool;

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    abstract protected function sessionPayload(array $user): array;

    /**
     * @param array<string, mixed> $fields
     */
    abstract protected function updateUser(int $id, array $fields): void;

    /**
     * E-posta + parola ile giriş. Başarısızlık nedenini ASLA ayırt etmez.
     */
    public function attempt(string $email, string $password): bool
    {
        $user = $this->findByEmail(trim($email));
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;

        // Kullanıcı olmasa da doğrulama maliyeti aynı kalır.
        $passwordOk = password_verify($password, (string) $hash);

        if ($user === null || ! $passwordOk || ! $this->isUsable($user, null)) {
            return false;
        }

        $id     = (int) $user['id'];
        $fields = ['last_login_at' => date('Y-m-d H:i:s')];

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->updateUser($id, $fields);

        session()->regenerate(true);
        session()->set($this->sessionKey(), $this->sessionPayload($user) + ['login_at' => time()]);

        $this->resolved = false;
        $this->user     = null;

        return true;
    }

    /**
     * Oturumdaki kullanıcı — her istekte VERİTABANINDAN yeniden doğrulanır.
     * Geçersizse yalnızca kendi oturum anahtarını siler.
     *
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $this->user     = null;

        $data = session()->get($this->sessionKey());

        if (! is_array($data) || ! isset($data['id'], $data['login_at'])) {
            if ($data !== null) {
                session()->remove($this->sessionKey());
            }

            return null;
        }

        $user = $this->findById((int) $data['id']);

        if ($user === null || ! $this->isUsable($user, $data) || $this->passwordChangedAfterLogin($user, (int) $data['login_at'])) {
            session()->remove($this->sessionKey());

            return null;
        }

        unset($user['password_hash']);

        return $this->user = $user;
    }

    /**
     * İstek içi önbelleği atar; sonraki user() çağrısı veritabanından yeniden doğrular.
     * Auth filtreleri her istekte çağırır (uzun ömürlü süreçlerde bayat veri kalmasın).
     */
    public function forget(): void
    {
        $this->resolved = false;
        $this->user     = null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Yalnızca kendi anahtarını siler; diğer panelin oturumu etkilenmez.
     */
    public function logout(): void
    {
        session()->remove($this->sessionKey());

        $this->resolved = true;
        $this->user     = null;
    }

    /**
     * Parola değişiminden sonra BU oturumu geçerli tutar (diğer oturumlar düşer).
     */
    public function refreshLoginTime(): void
    {
        $data = session()->get($this->sessionKey());

        if (is_array($data)) {
            $data['login_at'] = time();
            session()->set($this->sessionKey(), $data);
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    private function passwordChangedAfterLogin(array $user, int $loginAt): bool
    {
        if (empty($user['password_changed_at'])) {
            return false;
        }

        $changedAt = strtotime((string) $user['password_changed_at']);

        return $changedAt !== false && $changedAt > $loginAt;
    }
}
