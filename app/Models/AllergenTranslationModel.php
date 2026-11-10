<?php

declare(strict_types=1);

namespace App\Models;

final class AllergenTranslationModel extends TranslationModel
{
    public const FK          = 'allergen_id';
    public const TEXT_FIELDS = ['name' => 100, 'description' => 500];

    protected $table = 'allergen_translations';
}
