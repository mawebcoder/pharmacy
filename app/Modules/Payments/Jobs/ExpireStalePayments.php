<?php

declare(strict_types=1);

namespace App\Modules\Payments\Jobs;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Closes payment attempts whose window passed without a callback (roadmap D7, S25). Runs every minute.
 */
final class ExpireStalePayments implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Payment::query()
            ->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Pending)
            ->where(Payment::COLUMN_EXPIRES_AT, '<=', now())
            ->update([Payment::COLUMN_STATUS => PaymentAttemptStatus::Expired, 'updated_at' => now()]);
    }
}
