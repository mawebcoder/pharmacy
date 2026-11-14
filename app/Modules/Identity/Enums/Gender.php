<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * A customer's gender, optional on the profile (roadmap D3.3).
 */
enum Gender: string implements HasLabel
{
    use LabelledEnum;

    case Female = 'female';

    case Male = 'male';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'خانم',
            self::Male => 'آقا',
        };
    }
}
