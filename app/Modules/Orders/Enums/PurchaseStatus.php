<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * An order's purchase document (roadmap S25, owner 2026-11-16): being filled until every unit of every item has a
 * supplier row, then registered, then received when every row has arrived. Worked out by PurchaseProgress.
 */
enum PurchaseStatus: string implements HasLabel
{
    use LabelledEnum;

    case Draft = 'draft';
    case Registered = 'registered';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Registered => 'ثبت‌شده',
            self::Received => 'دریافت کامل',
        };
    }

    /**
     * Badge colour (Filament names).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Registered => 'warning',
            self::Received => 'success',
        };
    }
}
