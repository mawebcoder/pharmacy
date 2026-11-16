<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * How far an order's goods have been bought (roadmap S30, owner 2026-11-16), for the admin only: the order keeps its
 * seven statuses (D3.5). An order without a purchase document has none («بدون سند خرید»). Set by PurchaseProgress.
 */
enum SourcingStatus: string implements HasLabel
{
    use LabelledEnum;

    case AwaitingReceipt = 'awaiting_receipt';
    case PartiallyReceived = 'partially_received';
    case ReadyToPack = 'ready_to_pack';

    public const string NONE_LABEL = 'بدون سند خرید';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingReceipt => 'در انتظار دریافت',
            self::PartiallyReceived => 'دریافت ناقص',
            self::ReadyToPack => 'آماده بسته‌بندی',
        };
    }

    /**
     * Badge colour (Filament names).
     */
    public function color(): string
    {
        return match ($this) {
            self::AwaitingReceipt => 'warning',
            self::PartiallyReceived => 'danger',
            self::ReadyToPack => 'success',
        };
    }
}
