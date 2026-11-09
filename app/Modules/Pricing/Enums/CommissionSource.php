<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a SKU's commission came from (roadmap D3.2): the product's own, or its brand's.
 */
enum CommissionSource: string implements HasLabel
{
    use LabelledEnum;

    case Product = 'product';
    case Brand = 'brand';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'کمیسیون محصول',
            self::Brand => 'کمیسیون برند',
        };
    }
}
