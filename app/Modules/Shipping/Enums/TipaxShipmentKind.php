<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Which way a Tipax shipment goes (roadmap S35, owner 2026-11-19).
 */
enum TipaxShipmentKind: string implements HasLabel
{
    use LabelledEnum;

    case Outbound = 'outbound';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'ارسال به مشتری',
            self::Return => 'مرجوعی به انبار',
        };
    }
}
