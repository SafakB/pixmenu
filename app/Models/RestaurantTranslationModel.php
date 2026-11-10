<?php

declare(strict_types=1);

namespace App\Models;

final class RestaurantTranslationModel extends TranslationModel
{
    public const FK          = 'restaurant_id';
    public const TEXT_FIELDS = ['name' => 150, 'description' => null];

    protected $table = 'restaurant_translations';
}
