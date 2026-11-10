<?php

declare(strict_types=1);

namespace App\Models;

final class BranchTranslationModel extends TranslationModel
{
    public const FK          = 'branch_id';
    public const TEXT_FIELDS = ['name' => 150, 'address' => 500, 'description' => null];

    protected $table = 'branch_translations';
}
