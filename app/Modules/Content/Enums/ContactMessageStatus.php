<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a contact message is (roadmap S34): new until staff open it, then read, then answered once staff say so.
 */
enum ContactMessageStatus: string implements HasLabel
{
    use LabelledEnum;

    case New = 'new';
    case Read = 'read';
    case Answered = 'answered';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جدید',
            self::Read => 'خوانده‌شده',
            self::Answered => 'پاسخ داده شد',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Read => 'info',
            self::Answered => 'success',
        };
    }
}
