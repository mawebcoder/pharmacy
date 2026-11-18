<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * How a package reaches Tipax (`pickupType`, roadmap D6.6). We drop parcels at a branch (owner, 2026-11-18).
 */
enum TipaxPickupType: int implements HasLabel
{
    use LabelledEnum;

    case CollectedFromSender = 10;
    case DroppedAtBranch = 20;

    public function label(): string
    {
        return match ($this) {
            self::CollectedFromSender => 'جمع‌آوری از محل فرستنده',
            self::DroppedAtBranch => 'تحویل در نمایندگی تیپاکس',
        };
    }
}
