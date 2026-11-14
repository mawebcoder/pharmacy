<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Data\SmsResultData;

/**
 * One SMS provider's way of sending a pattern SMS (roadmap D11.6, strategy): SmsManager picks the sender for an
 * SmsProvider, so callers never depend on a provider.
 */
interface SmsSender
{
    public function send(SmsMessageData $message, string $patternId): SmsResultData;
}
