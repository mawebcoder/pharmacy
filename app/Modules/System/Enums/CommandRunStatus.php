<?php

declare(strict_types=1);

namespace App\Modules\System\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a command run from «فرمان‌ها» is: waiting for the deploy-user worker, running, finished (exit code 0) or not.
 */
enum CommandRunStatus: string implements HasLabel
{
    use LabelledEnum;

    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'در صف',
            self::Running => 'در حال اجرا',
            self::Done => 'انجام شد',
            self::Failed => 'ناموفق',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued, self::Running => 'gray',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
