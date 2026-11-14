<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Admin rolleri ve izinleri. Yeni rol eklemek için buraya bir girdi eklemek yeterlidir
 * (ör. 'support' => ['label' => 'Destek', 'badge' => 'secondary',
 *                    'permissions' => ['dashboard.view', 'restaurants.view', 'billing.view']]).
 *
 * Rotalar `adminperm:<izin>` filtresiyle, görünümler `admin_can('<izin>')` ile korunur.
 * Burada tanımlı olmayan bir role sahip admin giriş yapamaz (fail-closed).
 *
 * İzinler:
 *   dashboard.view        Gösterge paneli
 *   restaurants.view      Restoran listesi ve detay (salt okunur)
 *   restaurants.manage    Restoran ekle/düzenle/aktif-pasif, şube, restoran kullanıcısı, restoran dilleri, alan adı
 *   restaurants.delete    Restoran silme
 *   definitions.manage    Diller, alerjenler, menü şablonları
 *   billing.view          Faturalar, abonelik, paketler (salt okunur) + panodaki finans kartları
 *   invoices.manage       Faturayı ödendi işaretle, fatura indirimi
 *   invoices.void         Fatura iptali
 *   subscriptions.manage  Paket değiştir, ek süre, restoran indirimi
 *   subscriptions.control Abonelik iptal, askıya al, askıdan çıkar
 *   plans.manage          Paket ekle/düzenle/sırala/sil
 *   logs.view             İşlem kayıtları (admin ve panel kullanıcılarının yaptıkları; varsayılan: yalnızca süper admin)
 */
class AdminRoles extends BaseConfig
{
    /**
     * @var array<string, array{label: string, badge: string, permissions: list<string>}>
     */
    public array $roles = [
        'super_admin' => [
            'label'       => 'Süper Admin',
            'badge'       => 'danger',
            'permissions' => ['*'],
        ],
        'manager' => [
            'label'       => 'Yönetici',
            'badge'       => 'primary',
            'permissions' => [
                'dashboard.view',
                'restaurants.view',
                'restaurants.manage',
                'definitions.manage',
                'billing.view',
            ],
        ],
        'accountant' => [
            'label'       => 'Muhasebe',
            'badge'       => 'success',
            'permissions' => [
                'dashboard.view',
                'restaurants.view',
                'billing.view',
                'invoices.manage',
                'subscriptions.manage',
            ],
        ],
    ];

    /**
     * Yalnızca bu rol "tüm yetkiler" sayılır; son aktif hesabı korunur.
     */
    public string $superRole = 'super_admin';

    public function exists(string $role): bool
    {
        return isset($this->roles[$role]);
    }

    public function label(string $role): string
    {
        return $this->roles[$role]['label'] ?? $role;
    }

    public function allows(string $role, string $permission): bool
    {
        $permissions = $this->roles[$role]['permissions'] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
