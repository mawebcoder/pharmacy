<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Kind of tag (roadmap D3.1): a nutrient («آهن»), a need («بی‌خوابی») or anything else.
 */
enum TagType: string implements HasLabel
{
    use LabelledEnum;

    case Nutrient = 'nutrient';
    case Need = 'need';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Nutrient => 'ماده مغذی',
            self::Need => 'نیاز',
            self::General => 'عمومی',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Nutrient => 'success',
            self::Need => 'info',
            self::General => 'gray',
        };
    }
}
