<?php

declare(strict_types=1);

namespace App\Modules\Locations\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

enum CityType: string implements HasLabel
{
    use LabelledEnum;

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
