<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where an order stands against one checkpoint of its tracker (owner, 2026-11-16): passed (ticked), the one it is in
 * now, or still to come (a waiting clock).
 */
enum CheckpointState: string implements HasLabel
{
    use LabelledEnum;

    case Passed = 'passed';
    case Current = 'current';
    case Upcoming = 'upcoming';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'انجام شد',
            self::Current => 'در جریان',
            self::Upcoming => 'در انتظار',
        };
    }
}
