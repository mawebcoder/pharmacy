<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * What happened to a supplier's price row of a SKU (provider_price_changes).
 */
enum ProviderPriceEvent: string implements HasLabel
{
    use LabelledEnum;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'افزوده شد',
            self::Updated => 'تغییر کرد',
            self::Deleted => 'حذف شد',
        };
    }
}
