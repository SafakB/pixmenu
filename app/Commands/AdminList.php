<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AdminAccountService;
use CodeIgniter\CLI\CLI;

/**
 *   php spark admin:list
 */
final class AdminList extends AdminCommand
{
    protected $name        = 'admin:list';
    protected $description = 'Admin hesaplarını rolleriyle listeler.';
    protected $usage       = 'admin:list';

    public function run(array $params)
    {
        $roles  = config('AdminRoles');
        $admins = (new AdminAccountService())->all();

        if ($admins === []) {
            CLI::write('Hiç admin yok. Oluşturmak için: php spark admin:create', 'yellow');

            return EXIT_SUCCESS;
        }

        $rows = array_map(static fn (array $a): array => [
            (string) $a['id'],
            (string) $a['name'],
            (string) $a['email'],
            $roles->exists((string) $a['role']) ? $roles->label((string) $a['role']) . ' (' . $a['role'] . ')' : $a['role'] . ' (TANIMSIZ — giriş yapamaz)',
            (int) $a['is_active'] === 1 ? 'Aktif' : 'Pasif',
            (string) ($a['last_login_at'] ?? '—'),
        ], $admins);

        CLI::table($rows, ['ID', 'Ad', 'E-posta', 'Rol', 'Durum', 'Son giriş']);

        return EXIT_SUCCESS;
    }
}
