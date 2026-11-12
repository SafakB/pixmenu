<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Billing\Period;
use App\Services\BillingRunner;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Günlük faturalama işi. İşletim sistemi cron'u ile günde bir kez çalıştırılır:
 *
 *   0 3 * * * cd /var/www/pixmenu && php spark billing:run >> writable/logs/billing.log 2>&1
 */
final class BillingRun extends BaseCommand
{
    protected $group       = 'PixMenu';
    protected $name        = 'billing:run';
    protected $description = 'Abonelik dönemlerini ilerletir, fatura keser, hatırlatma gönderir, gecikenleri askıya alır.';
    protected $usage       = 'billing:run [--dry-run] [--date=YYYY-MM-DD]';
    protected $options     = [
        '--dry-run' => 'Değişiklikleri geri alır ve e-posta göndermez; yalnızca ne olacağını raporlar.',
        '--date'    => 'Bugün yerine kullanılacak tarih (test / geriye dönük çalıştırma).',
    ];

    public function run(array $params)
    {
        $dryRun = array_key_exists('dry-run', $params) || CLI::getOption('dry-run') !== null;
        $date   = $params['date'] ?? CLI::getOption('date');

        // `--date=YYYY-MM-DD` biçimi de kabul edilir.
        foreach (array_keys($params) as $key) {
            if (is_string($key) && str_starts_with($key, 'date=')) {
                $date = substr($key, 5);
            }
        }
        $today  = is_string($date) && $date !== '' ? $date : Period::today();

        if (! Period::isValidDate($today)) {
            CLI::error('Geçersiz tarih: ' . $today . ' (beklenen: YYYY-MM-DD)');

            return EXIT_USER_INPUT;
        }

        CLI::write(sprintf('[%s] billing:run — tarih: %s%s', date('Y-m-d H:i:s'), $today, $dryRun ? ' (DRY RUN — değişiklik yazılmaz)' : ''), 'yellow');

        $stats = (new BillingRunner())->run($today, $dryRun, static function (string $message): void {
            CLI::error($message);
        });

        $labels = [
            'restaurants'      => 'İşlenen restoran',
            'periods_advanced' => 'İlerletilen dönem',
            'ended'            => 'Sonlanan abonelik',
            'invoices_issued'  => 'Kesilen fatura',
            'issued_emails'    => 'Fatura bildirimi',
            'reminders'        => 'Vade hatırlatması',
            'trial_reminders'  => 'Deneme hatırlatması',
            'overdue'          => 'Gecikme bildirimi',
            'suspended'        => 'Askıya alınan',
            'resumed'          => 'Askıdan çıkan',
            'errors'           => 'Hata',
        ];

        $rows = [];

        foreach ($labels as $key => $label) {
            $rows[] = [$label, (string) ($stats[$key] ?? 0)];
        }

        CLI::table($rows, ['Adım', 'Adet']);

        return ($stats['errors'] ?? 0) > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
