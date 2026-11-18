<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * How a package reaches the receiver (`distributionType`, roadmap D6.6). We deliver to the door.
 */
enum TipaxDistributionType: int implements HasLabel
{
    use LabelledEnum;

    case ToReceiverAddress = 10;
    case CollectedAtBranch = 20;

    public function label(): string
    {
        return match ($this) {
            self::ToReceiverAddress => 'تحویل در آدرس گیرنده',
            self::CollectedAtBranch => 'تحویل در نمایندگی',
        };
    }
}
