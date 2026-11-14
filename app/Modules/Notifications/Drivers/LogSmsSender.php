<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Drivers;

use App\Modules\Notifications\Contracts\SmsSender;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Data\SmsResultData;
use Illuminate\Support\Facades\Log;

/**
 * The pretend sender (SMS_PRETEND, local and testing; roadmap D11.6): nothing is sent. The message goes to the
 * application log so a developer can read a sign-in code there; it is never used in production.
 */
final class LogSmsSender implements SmsSender
{
    public function send(SmsMessageData $message, string $patternId): SmsResultData
    {
        Log::info('SMS (pretend, not sent)', [
            'mobile' => $message->mobile,
            'template' => $message->template->value,
            'pattern_id' => $patternId,
            'args' => $message->args,
        ]);

        return new SmsResultData(success: true, providerMessageId: 'pretend-'.bin2hex(random_bytes(6)), code: 'pretend');
    }
}
