<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where an order was placed (owner, 2026-11-19): by the customer on the site, or by staff in the panel.
 */
enum OrderSource: string implements HasLabel
{
    use LabelledEnum;

    case Site = 'site';
    case Panel = 'panel';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'سایت',
            self::Panel => 'پنل مدیریت',
        };
    }

    /**
     * Badge colour (Filament names).
     */
    public function color(): string
    {
        return match ($this) {
            self::Site => 'gray',
            self::Panel => 'info',
        };
    }
}
