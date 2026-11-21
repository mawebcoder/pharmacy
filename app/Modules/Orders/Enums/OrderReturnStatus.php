<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * An order return (roadmap S27, owner 2026-11-19): a draft until approved (registered with Tipax when it is collected
 * from the customer), then picked up (Tipax), then received at the warehouse (counted by staff), then refunded; or cancelled before
 * pickup. Items never sent need no pickup: approved, then refunded at once.
 */
enum OrderReturnStatus: string implements HasLabel
{
    use LabelledEnum;

    case Draft = 'draft';
    case Approved = 'approved';
    case PickedUp = 'picked_up';
    case Received = 'received';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Approved => 'تأیید شده',
            self::PickedUp => 'جمع‌آوری شد',
            self::Received => 'دریافت در انبار',
            self::Refunded => 'بازگشت وجه شد',
            self::Cancelled => 'لغو شده',
        };
    }

    /**
     * What the customer reads on their order page.
     */
    public function customerLabel(): string
    {
        return match ($this) {
            self::Draft => 'در انتظار بررسی',
            self::Approved => 'تأیید شد',
            self::PickedUp => 'از شما تحویل گرفته شد',
            self::Received => 'به انبار رسید',
            self::Refunded => 'وجه بازگردانده شد',
            self::Cancelled => 'لغو شد',
        };
    }

    /**
     * Badge colour (Filament names).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved => 'warning',
            self::PickedUp => 'info',
            self::Received => 'primary',
            self::Refunded => 'success',
            self::Cancelled => 'danger',
        };
    }
}
