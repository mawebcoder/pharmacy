<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * How a product is linked to another one (roadmap D3.1 product_relations).
 */
enum ProductRelationType: string implements HasLabel
{
    use LabelledEnum;

    case Related = 'related';
    case Alternative = 'alternative';
    case FrequentlyBought = 'frequently_bought';

    public function label(): string
    {
        return match ($this) {
            self::Related => 'مرتبط',
            self::Alternative => 'جایگزین',
            self::FrequentlyBought => 'اغلب با هم خریده می‌شوند',
        };
    }
}
