<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Exceptions;

use App\Modules\Notifications\Data\SmsResultData;
use RuntimeException;

/**
 * A provider failure worth another try (timeout, 5xx, provider busy); thrown so the queue retries SendSmsJob with
 * its backoff. The message never contains the SMS variables.
 */
final class SmsNotSent extends RuntimeException
{
    public static function retryable(SmsResultData $result): self
    {
        return new self('SMS not sent: '.$result->code.' '.$result->error);
    }
}
