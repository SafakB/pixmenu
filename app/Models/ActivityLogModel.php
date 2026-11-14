<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * İşlem kayıtları. Tenant modeli DEĞİLDİR: yalnızca ActivityLogger yazar,
 * yalnızca admin log ekranı okur. Panel kodunda kullanılmaz.
 */
final class ActivityLogModel extends Model
{
    protected $table         = 'activity_logs';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'created_at', 'area', 'actor_id', 'actor_label', 'restaurant_id', 'action', 'path', 'ip', 'detail',
    ];
}
