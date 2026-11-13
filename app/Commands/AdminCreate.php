<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AdminAccountService;
use CodeIgniter\CLI\CLI;
use InvalidArgumentException;

/**
 *   php spark admin:create --name "Ayşe Yılmaz" --email ayse@ornek.com --role accountant
 *
 * Parola verilmezse güçlü bir parola üretilir ve bir kez ekrana yazılır.
 */
final class AdminCreate extends AdminCommand
{
    protected $name        = 'admin:create';
    protected $description = 'Yeni admin hesabı oluşturur (roller: Config\AdminRoles).';
    protected $usage       = 'admin:create --name <ad> --email <e-posta> --role <rol> [--password <parola>]';
    protected $options     = [
        '--name'     => 'Ad soyad',
        '--email'    => 'Giriş e-postası (tekil)',
        '--role'     => 'Rol: super_admin | manager | accountant (Config\AdminRoles)',
        '--password' => 'Parola (en az 10 karakter). Verilmezse rastgele üretilir. Kabuk geçmişine düşmemesi için vermemeniz önerilir.',
    ];

    public function run(array $params)
    {
        $name     = $this->stringOption($params, 'name') ?? '';
        $email    = $this->stringOption($params, 'email') ?? '';
        $role     = $this->stringOption($params, 'role') ?? '';
        $password = $this->stringOption($params, 'password');

        if ($name === '' || $email === '' || $role === '') {
            CLI::error('--name, --email ve --role zorunludur.');
            CLI::write('Kullanım: php spark ' . $this->usage);
            CLI::write('Roller: ' . implode(', ', array_keys(config('AdminRoles')->roles)));

            return EXIT_USER_INPUT;
        }

        try {
            $result = (new AdminAccountService())->create($name, $email, $role, $password);
        } catch (InvalidArgumentException $e) {
            CLI::error($e->getMessage());

            return EXIT_USER_INPUT;
        }

        CLI::write(sprintf('Admin oluşturuldu: #%d %s (%s)', $result['id'], $email, config('AdminRoles')->label($role)), 'green');

        if ($result['generated']) {
            $this->printPassword($result['password']);
        }

        return EXIT_SUCCESS;
    }
}
