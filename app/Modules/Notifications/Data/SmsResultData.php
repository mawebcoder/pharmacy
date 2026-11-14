<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Data;

/**
 * What the SMS provider answered: the message id on success, else its code and a Persian error, and whether trying
 * again later may work.
 */
final readonly class SmsResultData
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId,
        public string $code,
        public ?string $error = null,
        public bool $retryable = false,
    ) {}
}
