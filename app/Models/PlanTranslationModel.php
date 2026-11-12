<?php

declare(strict_types=1);

namespace App\Models;

final class PlanTranslationModel extends TranslationModel
{
    public const FK          = 'plan_id';
    public const TEXT_FIELDS = ['name' => 100, 'description' => null];

    protected $table = 'plan_translations';
}
