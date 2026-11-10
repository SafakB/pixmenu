<?php

declare(strict_types=1);

namespace App\Models;

final class CategoryTranslationModel extends TranslationModel
{
    public const FK          = 'category_id';
    public const TEXT_FIELDS = ['name' => 150, 'description' => null];

    protected $table = 'category_translations';
}
