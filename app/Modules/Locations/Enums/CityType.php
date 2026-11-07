<?php

declare(strict_types=1);

namespace App\Modules\Locations\Enums;

enum CityType: string
{
    case City = 'city';
    case Village = 'village';

    public function label(): string
    {
        return match ($this) {
            self::City => 'شهر',
            self::Village => 'روستا',
        };
    }
}
