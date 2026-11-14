<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\ActivityLogModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Saklama süresini aşan işlem kayıtlarını siler. İşletim sistemi cron'u ile günde bir kez:
 *
 *   30 3 * * * cd /var/www/pixmenu && php spark activity:prune >> writable/logs/activity-prune.log 2>&1
 */
final class ActivityPrune extends BaseCommand
{
    protected $group       = 'PixMenu';
    protected $name        = 'activity:prune';
    protected $description = 'Saklama süresini aşan işlem kayıtlarını (activity_logs) siler.';
    protected $usage       = 'activity:prune [--days=N]';
    protected $options     = [
        '--days' => 'Saklanacak gün sayısı (varsayılan: Config\Pixmenu::$activityLogRetentionDays).',
    ];

    public function run(array $params)
    {
        $days = $params['days'] ?? CLI::getOption('days');

        // `--days=N` biçimi de kabul edilir.
        foreach (array_keys($params) as $key) {
            if (is_string($key) && str_starts_with($key, 'days=')) {
                $days = substr($key, 5);
            }
        }

        $days = $days === null || $days === true ? config('Pixmenu')->activityLogRetentionDays : $days;

        if (! (is_int($days) || (is_string($days) && ctype_digit($days))) || (int) $days < 1) {
            CLI::error('Geçersiz gün sayısı (beklenen: 1 veya daha büyük bir tam sayı).');

            return EXIT_USER_INPUT;
        }

        $before = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));
        $model  = new ActivityLogModel();

        $model->where('created_at <', $before)->delete();

        CLI::write(sprintf(
            '[%s] activity:prune — %s öncesi silindi: %d kayıt',
            date('Y-m-d H:i:s'),
            $before,
            $model->db->affectedRows(),
        ), 'yellow');

        return EXIT_SUCCESS;
    }
}
