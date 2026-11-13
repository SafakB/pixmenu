<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `admin:*` komutlarının ortak tabanı. Admin hesapları web arayüzünden değil,
 * yalnızca bu komutlarla yönetilir (Config\AdminRoles, App\Services\AdminAccountService).
 */
abstract class AdminCommand extends BaseCommand
{
    protected $group = 'PixMenu';

    /**
     * `--ad değer` ve `--ad=değer` biçimlerinin ikisini de okur.
     *
     * @param array<int|string, mixed> $params
     *
     * @return string|true|null null = verilmedi, true = değersiz bayrak
     */
    protected function option(array $params, string $name): string|true|null
    {
        if (array_key_exists($name, $params)) {
            return $params[$name] === null || $params[$name] === true ? true : (string) $params[$name];
        }

        foreach (array_keys($params) as $key) {
            if (is_string($key) && str_starts_with($key, $name . '=')) {
                return substr($key, strlen($name) + 1);
            }
        }

        $value = CLI::getOption($name);

        return $value === null ? null : ($value === true ? true : (string) $value);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    protected function stringOption(array $params, string $name): ?string
    {
        $value = $this->option($params, $name);

        return is_string($value) ? $value : null;
    }

    protected function printPassword(string $password): void
    {
        CLI::newLine();
        CLI::write('Parola (yalnızca bir kez gösterilir, güvenli bir yere kaydedin):', 'yellow');
        CLI::write('  ' . $password, 'green');
        CLI::newLine();
    }
}
