<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Actions\AlertLowSmsCredit;
use App\Modules\Notifications\Support\SmsManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Every morning (roadmap D11.6, S37; owner 2026-11-27): asks the SMS provider for its credit and alerts super admins
 * when it is below the threshold of «تنظیمات پیامک» (else SMS_LOW_CREDIT_BELOW). Nothing in pretend mode or when the
 * provider does not answer.
 */
final class CheckSmsCredit implements ShouldQueue
{
    use Queueable;

    public function handle(SmsManager $sms, AlertLowSmsCredit $alert): void
    {
        if ($sms->isPretending()) {
            return;
        }

        $credit = $sms->sender()->credit();

        if ($credit !== null && $credit < $sms->lowCreditBelow()) {
            $alert($sms->provider(), $credit);
        }
    }
}
