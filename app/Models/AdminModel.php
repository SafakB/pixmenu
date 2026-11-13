<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class AdminModel extends Model
{
    protected $table         = 'admins';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'email', 'password_hash', 'role', 'is_active', 'last_login_at', 'password_changed_at'];
}
