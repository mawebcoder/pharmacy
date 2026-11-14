<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Data;

use App\Modules\Notifications\Enums\SmsTemplate;

/**
 * One SMS to send: the recipient (09xxxxxxxxx), the template and its variables in pattern order.
 */
final readonly class SmsMessageData
{
    /**
     * @param  list<string>  $args
     */
    public function __construct(
        public string $mobile,
        public SmsTemplate $template,
        public array $args,
    ) {}
}
