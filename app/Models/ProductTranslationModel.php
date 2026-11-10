<?php

declare(strict_types=1);

namespace App\Models;

final class ProductTranslationModel extends TranslationModel
{
    public const FK          = 'product_id';
    public const TEXT_FIELDS = ['name' => 150, 'description' => null];

    protected $table = 'product_translations';
}
