<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Availability of a variant (roadmap D3.1). We hold no stock: this reflects the providers' availability (S14).
 */
enum StockStatus: string implements HasLabel
{
    use LabelledEnum;

    case Available = 'available';
    case OnRequest = 'on_request';
    case OutOfStock = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'موجود',
            self::OnRequest => 'تأمین با سفارش',
            self::OutOfStock => 'ناموجود',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::OnRequest => 'warning',
            self::OutOfStock => 'gray',
        };
    }
}
