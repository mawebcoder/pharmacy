<?php

declare(strict_types=1);

namespace App\Modules\Media\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where an uploaded image is in the pipeline (roadmap D13.10).
 */
enum MediaStatus: string implements HasLabel
{
    use LabelledEnum;

    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'در حال پردازش',
            self::Ready => 'آماده',
            self::Failed => 'ناموفق',
        };
    }
}
