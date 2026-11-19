<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a Tipax registration stands (roadmap S35): registered (tracking codes back), confirmed by Tipax's success
 * callback, or failed (refused, or Tipax's failure callback).
 */
enum TipaxShipmentStatus: string implements HasLabel
{
    use LabelledEnum;

    case Registered = 'registered';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'ثبت شد',
            self::Confirmed => 'تأیید تیپاکس',
            self::Failed => 'ناموفق',
            self::Cancelled => 'لغو شد',
        };
    }
}
