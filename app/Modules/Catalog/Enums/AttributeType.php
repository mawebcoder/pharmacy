<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * How many values of an attribute a product takes (roadmap D3.1). Attributes are chosen from lists only; free text
 * belongs in the product description (decided 2026-11-07 in S11).
 */
enum AttributeType: string implements HasLabel
{
    use LabelledEnum;

    case Select = 'select';
    case MultiSelect = 'multi_select';

    public function label(): string
    {
        return match ($this) {
            self::Select => 'تک‌انتخابی',
            self::MultiSelect => 'چندانتخابی',
        };
    }
}
