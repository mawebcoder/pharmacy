<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Actions\SendTemplatedSms;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Exceptions\SmsNotSent;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one template SMS on the «sms» queue (roadmap D11.6). The payload is encrypted (it may carry a sign-in code).
 * Each try writes an sms_logs row; a retryable failure (timeout, 5xx, provider busy) is tried again after the
 * backoff, up to 3 tries and never later than 60 seconds after dispatch (a late sign-in code is useless).
 */
final class SendSmsJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'sms';

    public int $tries = 3;

    public int $timeout = 30;

    public const int EXPIRES_AFTER_SECONDS = 60;

    public function __construct(
        public readonly SmsMessageData $message,
    ) {
        $this->onQueue(self::QUEUE);
    }

    /**
     * Seconds to wait before the second and third tries.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    /**
     * Set when the job is queued: no try starts after this.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addSeconds(self::EXPIRES_AFTER_SECONDS);
    }

    public function handle(SendTemplatedSms $send): void
    {
        $result = $send($this->message);

        // retryUntil() makes the queue ignore $tries, so the limit is kept here.
        if (! $result->success && $result->retryable && $this->attempts() < $this->tries) {
            throw SmsNotSent::retryable($result);
        }
    }
}
