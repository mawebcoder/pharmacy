<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a supplier price import is (roadmap D13.9): read and checked in the background, previewed by the admin,
 * then applied in the background.
 */
enum PriceImportStatus: string implements HasLabel
{
    use LabelledEnum;

    case Pending = 'pending';
    case Processing = 'processing';
    case Previewed = 'previewed';
    case Applying = 'applying';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در صف بررسی',
            self::Processing => 'در حال بررسی',
            self::Previewed => 'آماده اعمال',
            self::Applying => 'در حال اعمال',
            self::Done => 'اعمال شد',
            self::Failed => 'ناموفق',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Processing, self::Applying => 'gray',
            self::Previewed => 'warning',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }
}
