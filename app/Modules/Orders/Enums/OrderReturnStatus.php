<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * An order return (roadmap S27, owner 2026-11-19): a draft until approved (registered with Tipax), or cancelled.
 */
enum OrderReturnStatus: string implements HasLabel
{
    use LabelledEnum;

    case Draft = 'draft';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Approved => 'تأیید شده',
            self::Cancelled => 'لغو شده',
        };
    }

    /**
     * Badge colour (Filament names).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved => 'success',
            self::Cancelled => 'danger',
        };
    }
}
