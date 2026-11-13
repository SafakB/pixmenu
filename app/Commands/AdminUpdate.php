<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AdminAccountService;
use CodeIgniter\CLI\CLI;
use InvalidArgumentException;

/**
 *   php spark admin:update ayse@ornek.com --role manager
 *   php spark admin:update ayse@ornek.com --active 0
 *   php spark admin:update ayse@ornek.com --password          (rastgele parola üretir)
 *
 * Rol ve aktiflik değişikliği adminin bir sonraki isteğinde etkili olur;
 * parola değişikliği açık oturumlarını kapatır. Son aktif süper admin korunur.
 */
final class AdminUpdate extends AdminCommand
{
    protected $name        = 'admin:update';
    protected $description = 'Admin hesabının rolünü, aktifliğini veya parolasını değiştirir.';
    protected $usage       = 'admin:update <e-posta> [--role <rol>] [--active 0|1] [--password [<parola>]]';
    protected $arguments   = [
        'e-posta' => 'Değiştirilecek adminin e-postası',
    ];
    protected $options = [
        '--role'     => 'Yeni rol: super_admin | manager | accountant (Config\AdminRoles)',
        '--active'   => '1 = aktif, 0 = pasif (pasif admin giriş yapamaz, açık oturumu düşer)',
        '--password' => 'Yeni parola; değer verilmezse rastgele üretilir',
    ];

    public function run(array $params)
    {
        $email = isset($params[0]) && is_string($params[0]) ? $params[0] : '';

        if ($email === '') {
            CLI::error('E-posta zorunludur.');
            CLI::write('Kullanım: php spark ' . $this->usage);

            return EXIT_USER_INPUT;
        }

        $changes = [];

        if (($role = $this->stringOption($params, 'role')) !== null) {
            $changes['role'] = $role;
        }

        $active = $this->option($params, 'active');

        if ($active !== null) {
            if (! in_array($active, ['0', '1'], true)) {
                CLI::error('--active yalnızca 0 veya 1 olabilir.');

                return EXIT_USER_INPUT;
            }
            $changes['is_active'] = $active === '1';
        }

        $password = $this->option($params, 'password');

        if ($password !== null) {
            $changes['password'] = $password === true ? null : $password;
        }

        try {
            $result = (new AdminAccountService())->update($email, $changes);
        } catch (InvalidArgumentException $e) {
            CLI::error($e->getMessage());

            return EXIT_USER_INPUT;
        }

        CLI::write(sprintf('Admin #%d güncellendi.', $result['id']), 'green');

        if ($result['generated'] && $result['password'] !== null) {
            $this->printPassword($result['password']);
        }

        return EXIT_SUCCESS;
    }
}
