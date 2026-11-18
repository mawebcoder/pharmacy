<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * What Tipax's `finalPrice` is in ([CONFIRM] with a test call, roadmap D6 note); we keep rial.
 */
enum TipaxPriceUnit: string implements HasLabel
{
    use LabelledEnum;

    case Rial = 'rial';
    case Toman = 'toman';

    public function label(): string
    {
        return match ($this) {
            self::Rial => 'ریال',
            self::Toman => 'تومان',
        };
    }

    /**
     * A price in this unit, in rial.
     */
    public function toRial(float $price): int
    {
        return (int) round($this === self::Toman ? $price * 10 : $price);
    }
}
