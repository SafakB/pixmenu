<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLogModel;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Admin ve panel kullanıcılarının işlem kaydı — istek başına en fazla TEK insert.
 *
 * Akış: controller başarılı bir yazmada succeed() çağırır (BaseController::done() /
 * jsonSuccess() bunu kendisi yapar); ActivityLogFilter yanıt hazırlandıktan sonra
 * flush() ile kaydı yazar. succeed() çağrılmayan istek (doğrulama hatası, iş kuralı
 * reddi, 403, 404) hiç kaydedilmez.
 *
 * detail(): yalnızca çağıranın AÇIKÇA verdiği küçük alanlar. Parola, token veya
 * ham istek gövdesi buraya ASLA verilmez.
 *
 * record(): rotadan çıkarılamayan akışlar için açık eylem kodu + alan (ör. Google ile giriş
 * GET callback'i, /kayit). Açık kayıt GET isteğinde de yazılır.
 */
final class ActivityLogger
{
    private const AREAS = ['admin', 'panel'];

    private bool $marked = false;

    /** @var array<string, mixed> */
    private array $detail = [];

    private ?int $restaurantId = null;

    /** @var array<string, mixed>|null */
    private ?array $actor = null;

    private ?string $area = null;

    private ?string $actionCode = null;

    /**
     * Bu istek başarılı bir işlemdir; yanıt sonrası kaydedilecek.
     */
    public function succeed(): void
    {
        $this->marked = true;
    }

    /**
     * Rotadan çıkarılamayan başarılı işlem: eylem kodu (`nesne.fiil`) ve alan açıkça verilir.
     * Aktör yanıt sonrası alanın auth servisinden okunur (giriş/kayıttan sonra dolu olur).
     */
    public function record(string $action, string $area = 'panel'): void
    {
        $this->marked     = true;
        $this->actionCode = $action;
        $this->area       = in_array($area, self::AREAS, true) ? $area : 'panel';
    }

    /**
     * record() ile açık kayıt istendi mi? (ActivityLogFilter GET'te yalnızca bunu yazar.)
     */
    public function hasExplicitRecord(): bool
    {
        return $this->actionCode !== null;
    }

    /**
     * Kayda eklenecek ek alanlar (kritik işlemler). Tekrar çağrılırsa birleştirilir.
     *
     * @param array<string, mixed> $data
     */
    public function detail(array $data): void
    {
        $this->detail = $data + $this->detail;
    }

    /**
     * Admin işlemlerinde URL'den çıkarılamayan restoran (ör. fatura işlemleri).
     */
    public function restaurant(int $restaurantId): void
    {
        $this->restaurantId = $restaurantId > 0 ? $restaurantId : null;
    }

    /**
     * Aktörü elle verir — yalnızca logout'ta, oturum silinmeden ÖNCE.
     *
     * @param array<string, mixed>|null $user
     */
    public function actor(?array $user): void
    {
        $this->actor = $user;
    }

    /**
     * İşaretliyse kaydı yazar; her durumda durumu sıfırlar.
     * Kayıt hatası kullanıcının isteğini ASLA bozmaz (yalnızca hata günlüğüne düşer).
     */
    public function flush(IncomingRequest $request): void
    {
        [$marked, $detail, $restaurantId, $actor, $explicitArea, $actionCode]
            = [$this->marked, $this->detail, $this->restaurantId, $this->actor, $this->area, $this->actionCode];

        $this->marked       = false;
        $this->detail       = [];
        $this->restaurantId = null;
        $this->actor        = null;
        $this->area         = null;
        $this->actionCode   = null;

        if (! $marked) {
            return;
        }

        try {
            $path = trim($request->getPath(), '/');
            $area = $explicitArea ?? explode('/', $path)[0];

            if (! in_array($area, self::AREAS, true)) {
                return;
            }

            $actor ??= service($area === 'admin' ? 'adminAuth' : 'panelAuth')->user();

            if ($actor === null) {
                return;
            }

            if ($area === 'panel') {
                $restaurantId = (int) $actor['restaurant_id'];
            } elseif ($restaurantId === null && preg_match('#^admin/restaurants/(\d+)#', $path, $m) === 1) {
                $restaurantId = (int) $m[1];
            }

            (new ActivityLogModel())->insert([
                'created_at'    => date('Y-m-d H:i:s'),
                'area'          => $area,
                'actor_id'      => (int) $actor['id'],
                'actor_label'   => mb_substr((string) ($actor['email'] ?? ''), 0, 190),
                'restaurant_id' => $restaurantId,
                'action'        => mb_substr($actionCode ?? $this->action(), 0, 60),
                'path'          => mb_substr($path, 0, 190),
                'ip'            => mb_substr($request->getIPAddress(), 0, 45),
                'detail'        => $detail === [] ? null : json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'İşlem kaydı yazılamadı: {message}', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Eşleşen rotadan eylem kodu: `InvoiceController::pay` → `invoice.pay`.
     * Giriş için `auth.login` (controller metodu `attempt`).
     */
    private function action(): string
    {
        $router = service('router');
        $class  = basename(str_replace('\\', '/', (string) $router->controllerName()));
        $class  = strtolower((string) preg_replace('/Controller$/', '', $class));
        $method = (string) $router->methodName();

        if ($class === 'auth' && $method === 'attempt') {
            $method = 'login';
        }

        return $class . '.' . $method;
    }
}
